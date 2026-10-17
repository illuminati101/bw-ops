param([switch]$Quiet)
. "$PSScriptRoot\common.ps1"
$pidFile = Join-Path $Runtime 'pids.txt'
if (Test-Path $pidFile) {
    Get-Content $pidFile | ForEach-Object { if ($_ -match '^\d+$') { Stop-Process -Id ([int]$_) -Force -ErrorAction SilentlyContinue } }
    Remove-Item $pidFile -ErrorAction SilentlyContinue
}
# php artisan serve starts a child php.exe; stop any left on port 8000.
Get-NetTCPConnection -LocalPort 8000 -State Listen -ErrorAction SilentlyContinue | ForEach-Object { Stop-Process -Id $_.OwningProcess -Force -ErrorAction SilentlyContinue }
if (-not $Quiet) { Say 'Black & White Ops test server stopped.' 'Green' }
