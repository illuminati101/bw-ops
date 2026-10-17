# Starts the website, admin panel, background workers and a public https tunnel for the phone apps.
. "$PSScriptRoot\common.ps1"
$php = Get-Php
if (-not $php -or -not (Test-Path (Join-Path $Backend '.env'))) { Say 'Run SETUP.bat first.' 'Red'; exit 1 }

& "$PSScriptRoot\stop.ps1" -Quiet

Title 'Starting the server'
$web = Start-Process $php -ArgumentList 'artisan','serve','--host=127.0.0.1','--port=8000' -WorkingDirectory $Backend -WindowStyle Minimized -PassThru -RedirectStandardOutput (Join-Path $Logs 'web.log') -RedirectStandardError (Join-Path $Logs 'web-error.log')
$queue = Start-Process $php -ArgumentList 'artisan','queue:work','--sleep=1','--tries=3' -WorkingDirectory $Backend -WindowStyle Minimized -PassThru -RedirectStandardOutput (Join-Path $Logs 'queue.log') -RedirectStandardError (Join-Path $Logs 'queue-error.log')
$sched = Start-Process $php -ArgumentList 'artisan','schedule:work' -WorkingDirectory $Backend -WindowStyle Minimized -PassThru -RedirectStandardOutput (Join-Path $Logs 'schedule.log') -RedirectStandardError (Join-Path $Logs 'schedule-error.log')
@($web.Id, $queue.Id, $sched.Id) | Set-Content (Join-Path $Runtime 'pids.txt')
Say 'Website running on http://localhost:8000' 'Green'

$public = $null
$named = $env:BW_TUNNEL_HOSTNAME
if (Test-Path $Cloudflared) {
    Title 'Opening the tunnel'
    $tlog = Join-Path $Logs 'tunnel.log'
    Remove-Item $tlog -ErrorAction SilentlyContinue
    if ($named) {
        # Fixed address (see SETUP-WINDOWS.md, "Use your own domain"): BW_TUNNEL_NAME and BW_TUNNEL_HOSTNAME set as Windows environment variables.
        $tunnel = Start-Process $Cloudflared -ArgumentList 'tunnel','--no-autoupdate','run','--url','http://127.0.0.1:8000',$env:BW_TUNNEL_NAME -WindowStyle Minimized -PassThru -RedirectStandardError $tlog
        $public = "https://$named"
    } else {
        $tunnel = Start-Process $Cloudflared -ArgumentList 'tunnel','--no-autoupdate','--url','http://127.0.0.1:8000' -WindowStyle Minimized -PassThru -RedirectStandardError $tlog
        for ($i = 0; $i -lt 60 -and -not $public; $i++) {
            Start-Sleep -Seconds 1
            if (Test-Path $tlog) { $m = Select-String -Path $tlog -Pattern 'https://[a-z0-9-]+\.trycloudflare\.com' | Select-Object -First 1; if ($m) { $public = $m.Matches[0].Value } }
        }
    }
    Add-Content (Join-Path $Runtime 'pids.txt') $tunnel.Id
}

Push-Location $Backend
if ($public) {
    Set-EnvValue 'APP_URL' $public
    Set-EnvValue 'BW_FORCE_APP_URL' 'true'
} else {
    Set-EnvValue 'APP_URL' 'http://localhost:8000'
    Set-EnvValue 'BW_FORCE_APP_URL' 'false'
}
& $php artisan config:clear | Out-Null
Pop-Location

Title 'Ready'
if ($public) {
    Say "Public address (use this in the phone apps under Settings > Server):" 'Yellow'
    Say "    $public" 'Cyan'
    Say "Website:      $public"
    Say "Admin panel:  $public/admin"
    try { Set-Clipboard $public; Say '(Copied to the clipboard.)' 'DarkGray' } catch {}
    Start-Process "$public/admin"
} else {
    Say 'The tunnel did not start, so only this computer can reach the server:' 'Red'
    Say '    http://localhost:8000 (website)   http://localhost:8000/admin (admin panel)'
    Start-Process 'http://localhost:8000/admin'
}
Say ''
Say 'Keep this computer awake while testing. Close everything with STOP.bat.' 'DarkGray'
Say "Logs are in $Logs" 'DarkGray'
