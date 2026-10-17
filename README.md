# Black & White Ops

Armed chauffeur and close protection platform for Cape Town: public website with online booking, an admin
panel for staff, a client app for hailing and booking, and a Crew app for operators.

| Folder | What it is |
| --- | --- |
| `backend/` | Laravel 13 application: website, client portal, admin panel (`/admin`), API for both apps |
| `mobile/` | One Expo (React Native) codebase that builds two apps: **Black & White Ops** (clients) and **BW Ops Crew** |
| `windows/` + `SETUP.bat` / `START.bat` / `STOP.bat` | Run a test server on a Windows PC with a public https address for the phones |
| `.github/workflows/` | Automatic tests, and builds of the Android APK/AAB and unsigned iOS IPA for both apps |
| `brand/` | Logo, app icons and source artwork |
| `docs/` | Guides: test server, installing the apps, going live |

## Quick start (Windows test server)

1. Double-click **SETUP.bat** once. It downloads a portable PHP and the Cloudflare tunnel tool into `windows\runtime`, then creates the database with demo data.
2. Double-click **START.bat**. It prints a public address such as `https://quiet-river-1234.trycloudflare.com` and opens the admin panel.
3. In each phone app, open **Change server** on the sign-in screen (or Account > Server) and paste that address.

Full details: [docs/SETUP-WINDOWS.md](docs/SETUP-WINDOWS.md). Installing the apps on phones: [docs/INSTALL-APPS.md](docs/INSTALL-APPS.md).

## Demo logins (test data only)

| Who | How |
| --- | --- |
| Admin panel | `admin@blackandwhiteops.com` / `ChangeMe-2026!` (change it after first sign-in) |
| Client | mobile `082 000 0001` (verified, card on file). In test mode the SMS code is shown on screen. |
| New client | any other mobile number signs up |
| Crew | mobile `082 000 0101` to `082 000 0106` in the Crew app |

## What's switchable in the admin panel

System > Settings > Features turns optional parts on and off without a new app build: on-demand hailing, advance
bookings, multi-day and recurring bookings, team bookings, multi-stop trips, ID verification, card holds,
no-show and cancellation fees, strikes, card machine payment, EFT invoicing, chat, masked calls, trip sharing,
ratings, SOS and arrival PINs. Services, rates, surcharges, add-ons, operator capabilities, documents, pages
and FAQs are all edited and published from the admin panel, and the website and both apps update straight away.

## Development

```bash
# backend
cd backend && composer install && npm ci && npm run build
cp .env.example .env && php artisan key:generate && touch database/database.sqlite
php artisan migrate --seed && php artisan serve
php artisan test

# mobile
cd mobile && npm ci
npx expo start                 # client app
APP_VARIANT=crew npx expo start # Crew app
npx tsc --noEmit
```

Native features (background location, maps, camera) need a build from GitHub Actions or `npx expo run:android`;
Expo Go is not enough.
