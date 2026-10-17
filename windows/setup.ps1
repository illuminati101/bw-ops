# One-time setup for the Black & White Ops test server on Windows.
# Downloads a portable PHP 8.4 and the Cloudflare tunnel tool into windows\runtime (nothing is installed system-wide),
# then creates the database with demo data.
. "$PSScriptRoot\common.ps1"
[Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12

Title 'PHP'
$php = Get-Php
if (-not $php) {
    Say 'No PHP 8.3 or newer found (XAMPP ships an older PHP). Downloading a portable PHP 8.4 ...'
    $zipName = $null
    try { $zipName = (Invoke-RestMethod 'https://windows.php.net/downloads/releases/releases.json').'8.4'.'nts-vs17-x64'.zip.path } catch {}
    if (-not $zipName) {
        try {
            $page = (Invoke-WebRequest 'https://windows.php.net/downloads/releases/' -UseBasicParsing).Content
            $zipName = ([regex]::Matches($page, 'php-8\.4\.\d+-nts-Win32-vs17-x64\.zip') | ForEach-Object Value | Sort-Object { [version]($_ -replace '^php-([\d.]+)-.*$','$1') } | Select-Object -Last 1)
        } catch {}
    }
    if (-not $zipName) { throw 'Could not find the PHP 8.4 download. Download "VS17 x64 Non Thread Safe" zip from https://windows.php.net/download and unzip it into windows\runtime\php, then run setup again.' }
    $zip = Join-Path $Runtime 'php.zip'
    Invoke-WebRequest "https://windows.php.net/downloads/releases/$zipName" -OutFile $zip -UseBasicParsing
    Expand-Archive $zip -DestinationPath (Join-Path $Runtime 'php') -Force
    Remove-Item $zip
    $ini = Get-Content (Join-Path $Runtime 'php\php.ini-production')
    $ini = $ini -replace '^;\s*extension_dir = "ext"', 'extension_dir = "ext"'
    foreach ($ext in 'curl','fileinfo','gd','intl','mbstring','openssl','pdo_mysql','pdo_sqlite','sqlite3','zip','sodium','exif') {
        $ini = $ini -replace "^;extension=$ext$", "extension=$ext"
    }
    $ini = $ini -replace '^memory_limit = .*', 'memory_limit = 512M'
    $ini = $ini -replace '^upload_max_filesize = .*', 'upload_max_filesize = 20M'
    $ini = $ini -replace '^post_max_size = .*', 'post_max_size = 25M'
    $ini = $ini -replace '^;date.timezone =.*', 'date.timezone = Africa/Johannesburg'
    Set-Content (Join-Path $Runtime 'php\php.ini') $ini -Encoding ASCII
    $php = $LocalPhp
    if (-not (Test-PhpOk $php)) {
        Say 'PHP could not start. It needs the Microsoft Visual C++ Redistributable (x64).' 'Red'
        Say 'Install it from https://aka.ms/vs/17/release/vc_redist.x64.exe and run SETUP.bat again.' 'Red'
        Start-Process 'https://aka.ms/vs/17/release/vc_redist.x64.exe'
        exit 1
    }
}
Say ("Using PHP " + (& $php -r 'echo PHP_VERSION;') + " at $php") 'Green'

Title 'Cloudflare tunnel'
if (-not (Test-Path $Cloudflared)) {
    Say 'Downloading cloudflared (gives the test server a public https address for the phone apps) ...'
    Invoke-WebRequest 'https://github.com/cloudflare/cloudflared/releases/latest/download/cloudflared-windows-amd64.exe' -OutFile $Cloudflared -UseBasicParsing
}
Say 'cloudflared ready' 'Green'

Title 'Application'
Push-Location $Backend
try {
    if (-not (Test-Path '.env')) {
        Copy-Item '.env.example' '.env'
        Set-EnvValue 'APP_ENV' 'local'
        Set-EnvValue 'APP_DEBUG' 'false'
        Set-EnvValue 'BROADCAST_CONNECTION' 'log'   # live updates use polling on the test server
        Set-EnvValue 'BW_FORCE_APP_URL' 'true'
        & $php artisan key:generate --force
    }
    $db = Join-Path $Backend 'database\database.sqlite'
    if (-not (Test-Path $db)) {
        New-Item -ItemType File -Path $db | Out-Null
        & $php artisan migrate --force --seed
    } else {
        & $php artisan migrate --force
    }
    & $php artisan storage:link 2>$null
    & $php artisan optimize:clear | Out-Null
} finally { Pop-Location }

Title 'Done'
Say 'Setup complete. Double-click START.bat to run the server.' 'Green'
Say 'Admin panel login: admin@blackandwhiteops.com / ChangeMe-2026!  (change this password after signing in)'
