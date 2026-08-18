<?php

namespace App\Jobs;

use App\Enums\ConversionFileStatus;
use App\Models\ConversionFile;
use App\Models\Setting;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

class ConvertVideoFile implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, SerializesModels;

    public int $tries = 1;

    public int $timeout = 0;

    public function __construct(public int $conversionFileId) {}

    public function handle(): void
    {
        if ($this->batch()?->cancelled()) {
            ConversionFile::whereKey($this->conversionFileId)->update([
                'status' => ConversionFileStatus::Cancelled,
            ]);

            return;
        }

        $file = ConversionFile::findOrFail($this->conversionFileId);
        $run = $file->run;
        $settings = Setting::current();

        $source = $file->source_path;
        $dir = dirname($source);
        $base = pathinfo($source, PATHINFO_FILENAME);
        $target = "{$dir}/{$base}.mp4";
        // ffmpeg infers the output container from the extension, so the temp
        // file must still end in .mp4 rather than a generic .tmp suffix.
        $tmp = "{$dir}/.{$base}.converting.mp4";

        $file->update(['status' => ConversionFileStatus::Converting, 'started_at' => now()]);
        $run->appendLog("{$source}:");
        $run->appendLog(' - Converting to MP4');

        try {
            if (! is_file($source)) {
                throw new RuntimeException('Source file no longer exists.');
            }

            if (file_exists($target)) {
                throw new RuntimeException("Target already exists, refusing to overwrite: {$target}");
            }

            $file->update(['source_size_bytes' => filesize($source)]);

            $process = new Process($this->buildFfmpegCommand($source, $tmp, $file->extension, $settings));
            $process->setTimeout(null);
            $process->run();

            if (! $process->isSuccessful()) {
                throw new RuntimeException('ffmpeg failed: '.trim($process->getErrorOutput()) ?: 'unknown error');
            }

            if (! file_exists($tmp)) {
                throw new RuntimeException('ffmpeg reported success but produced no output file.');
            }

            $run->appendLog(" - Setting LastWriteTime to {$file->source_mtime}");
            touch($tmp, $file->source_mtime->getTimestamp());

            $file->update(['status' => ConversionFileStatus::Moving]);
            $run->appendLog(" - Moving MP4 to {$dir}");

            if (file_exists($target) || ! rename($tmp, $target)) {
                throw new RuntimeException("Failed to move converted file into place: {$target}");
            }

            $convertedSize = filesize($target);

            $run->appendLog(' - Removing original '.strtoupper($file->extension));
            unlink($source);

            $file->update([
                'status' => ConversionFileStatus::Done,
                'converted_size_bytes' => $convertedSize,
                'finished_at' => now(),
            ]);
        } catch (Throwable $e) {
            if (file_exists($tmp)) {
                @unlink($tmp);
            }

            $file->update([
                'status' => ConversionFileStatus::Failed,
                'error_message' => $e->getMessage(),
                'finished_at' => now(),
            ]);

            $run->appendLog(' - FAILED: '.$e->getMessage());

            throw $e;
        }
    }

    /**
     * @return string[]
     */
    private function buildFfmpegCommand(string $source, string $tmpTarget, string $extension, Setting $settings): array
    {
        if ($settings->usesRemux($extension)) {
            return ['ffmpeg', '-y', '-i', $source, '-codec', 'copy', $tmpTarget, '-loglevel', 'error', '-nostats'];
        }

        return [
            'ffmpeg', '-y', '-i', $source,
            '-c:v', $settings->video_codec,
            '-crf', (string) $settings->crf,
            '-preset', $settings->preset,
            '-tune', $settings->tune,
            '-c:a', $settings->audio_codec,
            '-b:a', $settings->audio_bitrate,
            $tmpTarget, '-loglevel', 'error', '-nostats',
        ];
    }
}
