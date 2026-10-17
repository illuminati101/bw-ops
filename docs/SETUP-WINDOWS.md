# Test server on a Windows PC

This runs the full system (website, admin panel, apps' server) on your own computer, with a temporary public
https address so the phone apps can reach it from anywhere. It is for testing only: when the computer sleeps
or STOP.bat is run, the website and apps stop working.

## What you need

- Windows 10 or 11, 64-bit, with an internet connection.
- About 300 MB of free disk space.
- XAMPP is **not** required. (XAMPP's PHP is older than this system needs, so setup downloads a portable PHP 8.4 into the project folder instead. Nothing is installed system-wide.)

## First time

1. Unzip the project anywhere, for example `D:\BW OPS V1\bw-ops`.
2. Double-click **SETUP.bat**.
   - If Windows SmartScreen asks, choose *More info* > *Run anyway*.
   - If it says PHP needs the *Visual C++ Redistributable*, install it from the page it opens, then run SETUP.bat again.
3. When it says *Setup complete*, setup is done. It does not need to be run again (it is safe to repeat).

## Every time you test

1. Double-click **START.bat**.
2. Wait for the yellow line *Public address*. It looks like `https://word-word-word-word.trycloudflare.com` and is copied to your clipboard.
3. The admin panel opens in your browser. Sign in with `admin@blackandwhiteops.com` / `ChangeMe-2026!`.
4. On each phone: open the app, tap **Change server** on the sign-in screen (or *Account* > *Server* once signed in), paste the address, tap **Save and connect**.
5. When finished, double-click **STOP.bat**.

> The free tunnel address changes every time START.bat runs, so update the phones each time.
> To keep one permanent address, see *Use your own domain* below.

## Test mode

- **SMS**: until Twilio is connected, sign-in codes are shown on screen and logged; no SMS is sent.
- **Payments**: until Paystack keys are added, cards are recorded as "Card on file (record only)" and nothing is charged.
- **Email**: messages are written to `backend\storage\logs\laravel.log` instead of being sent.
- **Maps**: address search uses OpenStreetMap until a Google Maps key is added.
- **Live updates**: pages and apps refresh every few seconds.

Connect the real services in `backend\.env` (see [GO-LIVE.md](GO-LIVE.md)), then run STOP.bat and START.bat.

## Use your own domain (optional, permanent address)

If `blackandwhiteops.com` is on Cloudflare, you can give the test server a fixed address such as
`test.blackandwhiteops.com`:

1. In a Command Prompt in the project folder:
   ```
   windows\runtime\cloudflared.exe tunnel login
   windows\runtime\cloudflared.exe tunnel create bw-ops-test
   windows\runtime\cloudflared.exe tunnel route dns bw-ops-test test.blackandwhiteops.com
   ```
2. In *Start* > *Edit environment variables for your account*, add `BW_TUNNEL_NAME` = `bw-ops-test` and `BW_TUNNEL_HOSTNAME` = `test.blackandwhiteops.com`.
3. Run START.bat. The address now stays the same, and you can bake it into the apps (see INSTALL-APPS.md, *Default server*).

## Updating to a newer version

Replace the `backend` folder with the new one **but keep** `backend\.env` and `backend\database\database.sqlite`
(they hold your settings and data), then double-click **UPDATE.bat**.

## Starting over with fresh demo data

Run STOP.bat, delete `backend\database\database.sqlite`, then run SETUP.bat.

## Troubleshooting

| Problem | Fix |
| --- | --- |
| *Run SETUP.bat first* | SETUP did not finish. Run it again and read the first red line. |
| No public address appears | Your network may block the tunnel. Try another network (e.g. a phone hotspot). Logs: `windows\logs\tunnel.log`. |
| Apps say *Cannot reach the server* | START.bat must be running; check the address in the app matches the one printed; the PC must not be asleep. |
| Port 8000 already in use | Run STOP.bat, or close the other program using port 8000. |
| Anything else | See `windows\logs\` and `backend\storage\logs\laravel.log`. |
