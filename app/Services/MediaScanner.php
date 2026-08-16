<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Carbon;
use RuntimeException;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

class MediaScanner
{
    public function root(): string
    {
        return rtrim((string) config('media.root'), '/');
    }

    /**
     * Resolve settings.scan_path against the media root, guarding against
     * path traversal outside of the mounted volume.
     */
    public function resolveScanRoot(Setting $settings): string
    {
        $root = $this->root();
        $realRoot = realpath($root);

        if ($realRoot === false) {
            throw new RuntimeException("Media root does not exist or is not mounted: {$root}");
        }

        $relative = trim((string) $settings->scan_path, "/ \t\n\r\0\x0B");
        $path = $relative === '' ? $realRoot : $realRoot.'/'.$relative;

        $real = realpath($path);

        if ($real === false) {
            throw new RuntimeException("Scan path does not exist: {$path}");
        }

        if ($real !== $realRoot && ! str_starts_with($real.'/', rtrim($realRoot, '/').'/')) {
            throw new RuntimeException("Scan path escapes the media root: {$path}");
        }

        return $real;
    }

    /**
     * Files eligible for conversion: matching extensions, outside excluded
     * paths, sorted by full path (mirrors the original script's behavior).
     *
     * @return SplFileInfo[]
     */
    public function findConvertibleFiles(Setting $settings): array
    {
        $extensions = $settings->convert_extensions ?: [];

        if ($extensions === []) {
            return [];
        }

        $root = $this->resolveScanRoot($settings);
        $excludes = array_filter(array_map('strtolower', $settings->exclude_patterns ?: []));

        $finder = (new Finder)
            ->files()
            ->in($root)
            ->name(array_map(fn ($ext) => "*.{$ext}", $extensions))
            ->sortByName();

        $files = [];
        foreach ($finder as $file) {
            $path = strtolower($file->getPathname());

            foreach ($excludes as $pattern) {
                if (str_contains($path, $pattern)) {
                    continue 2;
                }
            }

            $files[] = $file;
        }

        return $files;
    }

    /**
     * Marker files (e.g. deleteafter.txt) found anywhere under the scan root.
     *
     * @return SplFileInfo[]
     */
    public function findMarkerFiles(Setting $settings): array
    {
        $root = $this->resolveScanRoot($settings);

        $finder = (new Finder)
            ->files()
            ->in($root)
            ->name($settings->delete_marker_filename)
            ->sortByName();

        return iterator_to_array($finder, false);
    }

    /**
     * Files with the given extensions under $directory whose last-modified
     * time is older than $cutoff.
     *
     * @return SplFileInfo[]
     */
    public function findExpiredFiles(string $directory, array $extensions, Carbon $cutoff): array
    {
        if ($extensions === [] || ! is_dir($directory)) {
            return [];
        }

        $finder = (new Finder)
            ->files()
            ->in($directory)
            ->name(array_map(fn ($ext) => "*.{$ext}", $extensions))
            ->date('< '.$cutoff->format('Y-m-d H:i:s'));

        return iterator_to_array($finder, false);
    }
}
