// CI helper: after `expo prebuild`, sign Android release builds with your own upload key when the
// ANDROID_KEYSTORE_* secrets are set. Without them, builds stay signed with the template's debug key,
// which is fine for installing test APKs by hand but not for the Play Store.
import { existsSync, readFileSync, writeFileSync } from 'node:fs';

const gradle = 'android/app/build.gradle';
const { ANDROID_KEYSTORE_PATH, ANDROID_KEYSTORE_PASSWORD, ANDROID_KEY_ALIAS, ANDROID_KEY_PASSWORD } = process.env;
if (!ANDROID_KEYSTORE_PATH || !existsSync(ANDROID_KEYSTORE_PATH)) {
  console.log('No upload keystore provided: release builds use the debug signing key (fine for test installs).');
  process.exit(0);
}
let src = readFileSync(gradle, 'utf8');
const block = `
        upload {
            storeFile file(${JSON.stringify(ANDROID_KEYSTORE_PATH)})
            storePassword ${JSON.stringify(ANDROID_KEYSTORE_PASSWORD ?? '')}
            keyAlias ${JSON.stringify(ANDROID_KEY_ALIAS ?? '')}
            keyPassword ${JSON.stringify(ANDROID_KEY_PASSWORD ?? ANDROID_KEYSTORE_PASSWORD ?? '')}
        }`;
src = src.replace(/signingConfigs\s*\{/, (m) => `${m}${block}`);
src = src.replace(/(release\s*\{[^}]*?)signingConfig\s+signingConfigs\.debug/s, '$1signingConfig signingConfigs.upload');
writeFileSync(gradle, src);
console.log('Release builds will be signed with the upload keystore.');
