# Going live

Phase 1 (1 November): website, admin panel and advance bookings live on blackandwhiteops.com.
The apps follow once the store accounts exist.

## 1. Accounts to open

| Service | Why | What we need from it |
| --- | --- | --- |
| **Hosting in South Africa** (a VPS, e.g. in Cape Town or Johannesburg) | Website, admin, apps' server. Local hosting keeps client data in South Africa (POPIA). | Ubuntu 24.04 server, 2 CPU / 4 GB RAM to start, SSH access |
| **Paystack** (paystack.com, South African business account) | Saving cards and charging after the job | Live public key and secret key; set the webhook URL (below) |
| **Twilio** | Sign-in codes by SMS, alerts, masked calls between client and operator, on-call escalation calls | Account SID, auth token, a number that can send SMS to South Africa |
| **Google Cloud** (optional but recommended) | Accurate address search and driving times | Maps API key with Places API (New) and Routes API enabled, restricted to the server's IP |
| **Email** (the domain's mailbox host) | Booking confirmations, receipts, invoices | SMTP host, port, username, password for bookings@blackandwhiteops.com |
| **Expo** (expo.dev, free) | Push notifications to the apps | Project ID (for the app builds) and an access token (for the server) |
| **Google Play Console** (once-off fee) | Publishing the Android apps | Developer account; we create an upload key |
| **Apple Developer Program** (yearly fee) | Publishing the iPhone apps, push notifications, TestFlight | Team ID; an App Store Connect API key |
| **Cloudflare** (free, optional) | DNS, HTTPS and protection for the website | Domain moved to Cloudflare |

## 2. Before launch: content and legal

- **Admin > System > Settings > Company**: replace every `[placeholder]` (registered name, CIPC number, VAT number or "Not VAT registered", PSIRA number, addresses, directors, Information Officer). These fill the legal pages, footer, receipts and invoices automatically.
- **Admin > System > Settings > Company > Bank details**: for EFT invoices.
- **Legal pages** (Terms, Privacy, Cancellation): drafted for South African law (CPA, ECTA, POPIA, PSIRA). Have an attorney review them; edit the text in `backend/resources/content/legal/` or ask us to update.
- **Register the Information Officer** with the Information Regulator (POPIA requirement).
- **Rates** (Admin > Services & pricing > Rate cards): the current prices are placeholders. Set call-out fees, hourly and per-km rates, minimums, surcharges and add-ons.
- **Website text and images** (Admin > Website > Pages and FAQs, and Services & pricing > Services): review and replace.
- **Operators and vehicles**: add real operators, their documents with expiry dates, and vehicles. Remove demo data (we can give you a clean database).
- **Staff accounts**: create logins for dispatchers and managers with the right roles; change the admin password.

## 3. Server setup (we can do this for you)

1. Install Nginx, PHP 8.4 (fpm, with curl, intl, mbstring, gd, zip, pdo_mysql, bcmath, exif), MySQL 8, Supervisor, Certbot.
2. Deploy the `backend` folder, then:
   ```bash
   composer install --no-dev --optimize-autoloader
   npm ci && npm run build
   cp .env.example .env   # fill in as below
   php artisan key:generate
   php artisan migrate --force --seed
   php artisan storage:link
   php artisan optimize
   ```
3. `.env` for production:
   ```
   APP_ENV=production
   APP_DEBUG=false
   APP_URL=https://blackandwhiteops.com
   DB_CONNECTION=mysql   (and DB_HOST, DB_DATABASE, DB_USERNAME, DB_PASSWORD)
   MAIL_MAILER=smtp      (and MAIL_HOST, MAIL_PORT, MAIL_USERNAME, MAIL_PASSWORD)
   BW_SMS_DRIVER=twilio  (and TWILIO_ACCOUNT_SID, TWILIO_AUTH_TOKEN, TWILIO_FROM_NUMBER)
   PAYSTACK_PUBLIC_KEY=pk_live_...
   PAYSTACK_SECRET_KEY=sk_live_...
   GOOGLE_MAPS_API_KEY=...
   EXPO_ACCESS_TOKEN=...
   REVERB_APP_SECRET=(a long random string)
   BW_PUBLIC_WS_HOST=blackandwhiteops.com
   BW_PUBLIC_WS_PORT=443
   BW_PUBLIC_WS_SCHEME=https
   ```
4. Nginx: serve `backend/public`, and proxy `/app` and `/apps` to Reverb on port 8080 (websockets).
5. Supervisor: `php artisan queue:work --tries=3`, and `php artisan reverb:start`.
6. Cron: `* * * * * cd /path/backend && php artisan schedule:run >> /dev/null 2>&1`.
7. Paystack dashboard > Settings > API Keys & Webhooks: webhook URL `https://blackandwhiteops.com/payments/webhook`.
8. Backups: nightly database dump and `storage/app` copied off the server.
9. Sign in to `/admin`: two-factor sign-in is required in production. Set it up with an authenticator app.

## 4. The apps in the stores

1. Run **Mobile builds** with *Default server address* `https://blackandwhiteops.com`.
2. **Google Play**: create two apps (`com.blackandwhiteops.client`, `com.blackandwhiteops.crew`), add the upload key secrets to GitHub (see INSTALL-APPS.md), upload the `.aab` files. Fill in the Data safety form (location, phone number, name, email, photos for ID verification) and the background location declaration for the Crew app (with a short video of the online toggle).
3. **App Store**: create both apps in App Store Connect, then we switch the iOS build to signed builds and upload through TestFlight. Apple reviews the client app's account deletion (built in) and the Crew app's background location use.
4. Privacy policy URL for both stores: `https://blackandwhiteops.com/legal/privacy`.

## 5. Launch-day checklist

- [ ] Test booking end to end on the live site with a real card (then refund it in Paystack)
- [ ] SMS sign-in code arrives on a South African number
- [ ] Control room open on a screen; on-call roster filled in (Admin > Operations > On-call roster)
- [ ] SOS test: raise and cancel from the client app; the on-call phone receives the escalation
- [ ] Admin dashboard *Launch checklist* widget shows all green
