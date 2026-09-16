$root = 'D:\xampp\htdocs\app-akuntansi-portfolio-master'
$files = Get-ChildItem -Recurse -File "$root\resources\views\manufacturing" -Filter *.blade.php
$out = @()
foreach ($f in $files) {
    $rel = $f.FullName.Substring($root.Length + 1)
    $lines = Get-Content $f.FullName
    for ($i = 0; $i -lt $lines.Count; $i++) {
        $line = $lines[$i]
        # Lompati baris dengan interpolasi variabel blade
        if ($line -match '__\(|@lang|\{\{') { continue }
        # Cari teks dalam placeholder, aria-label, title, atau teks plain di tag
        if ($line -match 'placeholder\s*=\s*"([^"]+)"' -or $line -match 'aria-label\s*=\s*"([^"]+)"') {
            $out += "[$rel L$($i+1)] ATTR: $($Matches[1])"
        }
        elseif ($line -match '>\s*([A-Za-z][^<>{}]*[A-Za-z.])\s*<' -and $line -notmatch '^\s*<(/|!|input|script|link|meta|br|hr|img)') {
            $txt = $Matches[1].Trim()
            if ($txt.Length -gt 2 -and $txt -notmatch '^https?:|^\d+$|^[a-z-]+$') {
                $out += "[$rel L$($i+1)] TEXT: $txt"
            }
        }
    }
}
$out | Set-Content "$root\tmp_scan_result.txt"
Write-Host "Total hits: $($out.Count)"