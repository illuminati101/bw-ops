# Shared helpers for the Black & White Ops Windows test server.
$ErrorActionPreference = 'Stop'
$Root     = Split-Path -Parent $PSScriptRoot
$Backend  = Join-Path $Root 'backend'
$Runtime  = Join-Path $PSScriptRoot 'runtime'
$LocalPhp = Join-Path $Runtime 'php\php.exe'
$Cloudflared = Join-Path $Runtime 'cloudflared.exe'
$Logs     = Join-Path $PSScriptRoot 'logs'
New-Item -ItemType Directory -Force -Path $Runtime, $Logs | Out-Null

function Say($text, $color = 'Gray') { Write-Host $text -ForegroundColor $color }
function Title($text) { Write-Host ''; Write-Host "== $text ==" -ForegroundColor Yellow }

function Test-PhpOk([string]$exe) {
    if (-not $exe -or -not (Test-Path $exe)) { return $false }
    try {
        $v = & $exe -r "echo PHP_VERSION;" 2>$null
        return ($v -and [version]($v -replace '[^0-9.].*$','') -ge [version]'8.3.0')
    } catch { return $false }
}

# Prefer the PHP downloaded into runtime\php, then a PHP 8.3+ on PATH, then XAMPP's (usually too old).
function Get-Php {
    if (Test-PhpOk $LocalPhp) { return $LocalPhp }
    $onPath = (Get-Command php -ErrorAction SilentlyContinue).Source
    if (Test-PhpOk $onPath) { return $onPath }
    foreach ($x in @('C:\xampp\php\php.exe', 'D:\xampp\php\php.exe')) { if (Test-PhpOk $x) { return $x } }
    return $null
}

function Set-EnvValue([string]$key, [string]$value) {
    $file = Join-Path $Backend '.env'
    $lines = Get-Content $file
    $found = $false
    $lines = $lines | ForEach-Object { if ($_ -match "^$key=") { $found = $true; "$key=$value" } else { $_ } }
    if (-not $found) { $lines += "$key=$value" }
    [IO.File]::WriteAllLines($file, [string[]]$lines)   # UTF-8 without a byte-order mark
}

# Turn on OPcache in the portable PHP if an older setup left it off (makes pages many times faster).
function Enable-PhpSpeedups {
    $iniPath = Join-Path $Runtime 'php\php.ini'
    if (-not (Test-Path $iniPath)) { return }
    $ini = Get-Content $iniPath
    if ($ini -match '^opcache.enable=1') { return }
    $ini = $ini -replace '^;zend_extension=opcache', 'zend_extension=opcache'
    $ini += @('', '; Black & White Ops: speed', 'opcache.enable=1', 'opcache.enable_cli=1', 'opcache.memory_consumption=256',
        'opcache.interned_strings_buffer=32', 'opcache.max_accelerated_files=40000', 'opcache.validate_timestamps=1',
        'opcache.revalidate_freq=10', 'realpath_cache_size=16M', 'realpath_cache_ttl=600')
    Set-Content $iniPath $ini -Encoding ASCII
}
