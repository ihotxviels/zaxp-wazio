$file = 'C:\Users\Admin\Downloads\wazio\app\Views\admin\dashboard.php'
$lines = [System.IO.File]::ReadAllLines($file, [System.Text.Encoding]::UTF8)
Write-Host "Total lines: $($lines.Length)"
$kept = [System.Collections.Generic.List[string]]::new()
for ($i = 0; $i -lt $lines.Length; $i++) {
    # Keep lines 0-198 (the main content) and 839+ (the real modals)
    if ($i -lt 199 -or $i -ge 839) {
        $kept.Add($lines[$i])
    }
}
[System.IO.File]::WriteAllLines($file, $kept, [System.Text.Encoding]::UTF8)
Write-Host "Done. Lines remaining: $($kept.Count)"
Write-Host "Lines removed: $($lines.Length - $kept.Count)"
