# Run after replacing the backend folder with a newer version: applies database changes and clears caches.
. "$PSScriptRoot\common.ps1"
$php = Get-Php
Push-Location $Backend
& $php artisan migrate --force
& $php artisan optimize:clear
& $php artisan filament:optimize-clear
Pop-Location
Say 'Updated.' 'Green'
