Start-Transcript -Path $PSScriptRoot\Delete-ExpiredEpisodes.log -Force

$creds = Import-Clixml -Path $PSScriptRoot\Credentials\jason-creds.xml

$mediaDrive = New-PSDrive -Name Media -PSProvider FileSystem -Root \\nas.huebel.local\Media -Credential $creds

$deleteafterlist = Get-ChildItem -Path Media:\ -Filter "deleteafter.txt" -Recurse | Sort-Object -Property FullName

ForEach ( $deleteafterfile in $deleteafterlist ) {

    [int]$deleteafter = Get-Content $deleteafterfile.FullName -TotalCount 1

    If ($deleteafter -gt 0) {

        Write-Output "$($deleteafterfile.DirectoryName): $($deleteafter) Days"

        # Delete all files older than $deleteafter
        $files = Get-ChildItem -Path $deleteafterfile.DirectoryName -Include "*.mp4","*.mkv","*.avi","*.srt","*.sub" -Recurse | Where-Object { $_.LastWriteTime -lt (Get-Date).AddDays(-$deleteafter) }

        # list the files to be deleted
        ForEach ($file in $files) {
            Write-Output "  - $($file.FullName)"
        }

        #delete the files
        $files | Remove-Item -Force -Confirm:$false

    } else {

        Write-Output "$($file.FullName) BAD VALUE`n"

    }

}

Stop-Transcript
