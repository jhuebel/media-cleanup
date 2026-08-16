$numToProcess = 250

Start-Transcript -Path $PSScriptRoot\Convert-Videos.log -Force

$creds = Import-Clixml -Path $PSScriptRoot\Credentials\jason-creds.xml
$mediaDrive = New-PSDrive -Name Media -PSProvider FileSystem -Root \\nas02.local\media -Credential $creds
$workFolder = "\\nas.huebel.local\media\Incoming\Converting"

If (Test-Path -Path "$workFolder\*.mp4") {
    Write-Output "Cleaning up $workFolder..."
    Remove-Item -Path "$workFolder\*.mp4" -Force
}

Write-Output "Getting list of files to convert..."
$allFiles = Get-ChildItem -Path Media:\ -Include "*.mkv", "*.avi" -Recurse |
    Where-Object { $_.Directory.FullName -notlike "*incoming*" } |
    Sort-Object -Property FullName
$realCount = $allFiles.Count

If ($realCount -gt 0) {

    $filesToProcess = $allFiles | Select-Object -First $numToProcess
    $fileCount = $filesToProcess.Count

    Write-Output "$fileCount files to process (of $realCount)..."

    $numDone = 0
    ForEach ($file in $filesToProcess) {

        Write-Output "$($file.FullName):"

        # Convert the video to MP4
        Write-Output " - Converting to MP4"
        If ($file.Extension -eq '.mkv') {
            # MKV: remux streams without re-encoding
            & C:\Software\ffmpeg-6.0-full_build\bin\ffmpeg.exe -y -i "$($file.FullName)" -codec copy "$workFolder\$($file.BaseName).mp4" -loglevel error -nostats
        } ElseIf ($file.Extension -eq '.avi') {
            # AVI: re-encode with H.265 CRF 26, medium preset, ssim tune, 128k AAC audio
            & C:\Software\ffmpeg-6.0-full_build\bin\ffmpeg.exe -y -i "$($file.FullName)" -c:v libx265 -crf 26 -preset medium -tune ssim -c:a aac -b:a 128k "$workFolder\$($file.BaseName).mp4" -loglevel error -nostats
        } Else {
            Write-Output " - SKIPPING: unsupported file type ($($file.Extension))"
        }

        # Set new video modified date to date of original file
        Write-Output " - Setting LastWriteTime to $($file.LastWriteTime)"
        (Get-Item "$workFolder\$($file.BaseName).mp4").LastWriteTime = $file.LastWriteTime

        # Move the converted file to the correct location
        Write-Output " - Moving MP4 to $($file.DirectoryName)"
        Move-Item -Path "$workFolder\$($file.BaseName).mp4" -Destination $file.DirectoryName

        # Delete original file if the move was successful
        $ext = $file.Extension.TrimStart('.').ToUpper()
        If (Test-Path -Path "$($file.DirectoryName)\$($file.BaseName).mp4") {
            Write-Output " - Removing original $ext"
            Remove-Item -Path $file.FullName
        } Else {
            Write-Output " - MOVE FAILED! Keeping original $ext."
        }

        $numDone++
        $totalComplete = ($numDone / $fileCount) * 100
        Write-Output "- $numDone of $fileCount, $("{0:n2}" -f $totalComplete)% Complete"

    }
} Else {
    Write-Output "Nothing to process."
}

Stop-Transcript
