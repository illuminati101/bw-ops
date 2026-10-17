# Building and installing the test apps

There are two apps, built from the same code in `mobile/`:

| App | For | Android file | iPhone file |
| --- | --- | --- | --- |
| **Black & White Ops** | clients | `bw-ops-android-N.apk` | `bw-ops-ios-N-unsigned.ipa` |
| **BW Ops Crew** | chauffeurs and protection officers | `bw-ops-crew-android-N.apk` | `bw-ops-crew-ios-N-unsigned.ipa` |

(`N` is the build number. The `.aab` files are for the Play Store later and cannot be installed directly.)

## 1. Build them on GitHub (free)

The project includes a GitHub Actions workflow that builds all four files on GitHub's computers, so no Mac or
Android Studio is needed.

1. Create a **private** repository on GitHub (for example `bw-ops`) and upload this project to it
   (GitHub Desktop: *File* > *Add local repository* > *Publish repository*, keep *Private* ticked).
2. On GitHub open the repository > **Actions** > **Mobile builds** > **Run workflow**.
   - *Default server address*: the address the apps start with. Use your permanent test address if you have one; otherwise leave the default and change it inside the app.
   - *Also build the iPhone apps*: tick this when you want the `.ipa` files too (Android is always built).
3. After about 20 to 45 minutes, open **Releases** (right-hand side of the repository's main page) > *Test build N*. The files are attached.

Android builds also run automatically whenever something in `mobile/` changes on the `main` branch.

## 2. Install on Android

1. On the phone, sign in to GitHub in the browser and open the release, or send the `.apk` to the phone (WhatsApp to yourself, email, Google Drive).
2. Tap the `.apk`. Android asks to allow installs from that app (Chrome, Files, WhatsApp): allow it.
3. Open the app, tap **Change server**, paste the address from START.bat, and sign in.

Newer test builds install over the old one and keep you signed in.

## 3. Install on iPhone (Sideloadly, free)

The `.ipa` is unsigned: Sideloadly signs it with your own Apple ID on your computer.

1. On a Windows PC install **iTunes** (from apple.com, not the Microsoft Store version) and **Sideloadly** (sideloadly.io).
2. Connect the iPhone with a cable and tap *Trust this computer* on the phone.
3. Drag the `.ipa` into Sideloadly, enter your Apple ID, click **Start**.
4. On the iPhone: *Settings* > *General* > *VPN & Device Management* > your Apple ID > **Trust**.
5. iOS 16 and later: *Settings* > *Privacy & Security* > **Developer Mode** > on (the phone restarts).

Limits of a free Apple ID: the app stops opening after **7 days** (connect and press Start in Sideloadly again
to renew), and at most 3 sideloaded apps per phone. A paid Apple Developer account (needed for the App Store
anyway) removes these limits and allows TestFlight.

AltStore (altstore.io) works the same way if you prefer it.

## What to test

**Client app**: sign up with a new number, add details, ID verification (photo of an ID and a selfie), add a
card, book ahead, hail now, follow the booking, the arrival PIN, chat, share trip, cancel (the fee is shown first),
reconfirm, SOS with safety and duress PINs, rate a finished job, delete account.

**Crew app**: sign in with `082 000 0101` (or a crew number you add in the admin panel), go online, accept an
offer, *I'm on my way*, *I've arrived*, check the client's PIN, start, complete, documents upload, earnings.

**Admin panel** (`/admin`): watch bookings appear in the Control room, approve bookings, assign operators,
change a rate or a service and see it update in the apps, switch features off and on.

## Notes for the test builds

- **Push notifications** need an Expo account (free) and, for iPhone, an Apple Developer account. Until then the apps refresh on screen and important messages go by SMS once Twilio is connected.
- **Background location** (Crew app): allow location *All the time* (Android) or *Always* (iPhone) so the control room can see operators while the app is in the background. Android shows a "you are online" notification while sharing.
- **Default server**: to bake an address in, run the workflow with *Default server address* set, or set a repository variable `BW_SERVER_URL` (*Settings* > *Secrets and variables* > *Actions* > *Variables*).
- **Store builds**: add an Android upload key as repository secrets (`ANDROID_KEYSTORE_BASE64`, `ANDROID_KEYSTORE_PASSWORD`, `ANDROID_KEY_ALIAS`, `ANDROID_KEY_PASSWORD`) and the workflow signs the `.aab` for Google Play. For the App Store, the iOS app is built and signed with your Apple Developer account (we can set this up when the account exists).
