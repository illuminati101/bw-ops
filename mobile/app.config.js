// One codebase, two apps. Build the Crew app with APP_VARIANT=crew; anything else builds the client app.
// BW_SERVER_URL sets the default server baked into the build (it can still be changed inside the app).

const VARIANT = process.env.APP_VARIANT === 'crew' ? 'crew' : 'client';
const VERSION = '1.0.0';
const BUILD = Number(process.env.BUILD_NUMBER || 1);

const variants = {
  client: {
    name: 'Black & White Ops',
    slug: 'bw-ops',
    scheme: 'bwops',
    bundleId: 'com.blackandwhiteops.client',
    icon: './assets/brand/icon-client-1024.png',
    adaptive: './assets/brand/adaptive-client-fg.png',
  },
  crew: {
    name: 'BW Ops Crew',
    slug: 'bw-ops-crew',
    scheme: 'bwopscrew',
    bundleId: 'com.blackandwhiteops.crew',
    icon: './assets/brand/icon-crew-1024.png',
    adaptive: './assets/brand/adaptive-crew-fg.png',
  },
};
const v = variants[VARIANT];

const locationPlugin = VARIANT === 'crew'
  ? ['expo-location', {
      locationWhenInUsePermission: 'BW Ops Crew uses your location to offer you nearby jobs and show clients where you are during a job.',
      locationAlwaysAndWhenInUsePermission: 'While you are online, BW Ops Crew shares your location with the control room so jobs and client tracking keep working when the app is in the background.',
      isIosBackgroundLocationEnabled: true,
      isAndroidBackgroundLocationEnabled: true,
      isAndroidForegroundServiceEnabled: true,
    }]
  : ['expo-location', {
      locationWhenInUsePermission: 'Black & White Ops uses your location to set your pickup point and to send it to our control room if you raise an SOS.',
      locationAlwaysAndWhenInUsePermission: false,
      isIosBackgroundLocationEnabled: false,
      isAndroidBackgroundLocationEnabled: false,
    }];

module.exports = {
  expo: {
    name: v.name,
    slug: v.slug,
    scheme: v.scheme,
    version: VERSION,
    orientation: 'portrait',
    icon: v.icon,
    userInterfaceStyle: 'dark',
    backgroundColor: '#070707',
    ios: {
      bundleIdentifier: v.bundleId,
      buildNumber: String(BUILD),
      supportsTablet: false,
      infoPlist: {
        NSCameraUsageDescription: VARIANT === 'crew'
          ? 'Take photos of your certificates and documents for the operations team.'
          : 'Take a photo of your ID and a selfie to verify your account.',
        NSPhotoLibraryUsageDescription: 'Choose a photo of your document to upload.',
        ITSAppUsesNonExemptEncryption: false,
      },
    },
    android: {
      package: v.bundleId,
      versionCode: BUILD,
      adaptiveIcon: { foregroundImage: v.adaptive, backgroundColor: '#070707', monochromeImage: './assets/brand/mark-white.png' },
      predictiveBackGestureEnabled: false,
      permissions: VARIANT === 'crew'
        ? ['ACCESS_FINE_LOCATION', 'ACCESS_COARSE_LOCATION', 'ACCESS_BACKGROUND_LOCATION', 'FOREGROUND_SERVICE', 'FOREGROUND_SERVICE_LOCATION', 'VIBRATE', 'CAMERA']
        : ['ACCESS_FINE_LOCATION', 'ACCESS_COARSE_LOCATION', 'VIBRATE', 'CAMERA'],
    },
    plugins: [
      ['expo-splash-screen', { image: './assets/brand/mark-gold.png', imageWidth: 140, backgroundColor: '#070707', resizeMode: 'contain' }],
      locationPlugin,
      ['expo-notifications', { icon: './assets/brand/mark-white.png', color: '#c9a86a' }],
      ['expo-image-picker', {
        photosPermission: 'Choose a photo of your document to upload.',
        cameraPermission: VARIANT === 'crew' ? 'Take photos of your certificates for the operations team.' : 'Take a photo of your ID and a selfie to verify your account.',
        microphonePermission: false,
      }],
      'expo-secure-store',
      'expo-font',
      'expo-web-browser',
      // Test builds may point at a computer on the local network over plain http.
      ['expo-build-properties', { android: { usesCleartextTraffic: true } }],
    ],
    web: { favicon: './assets/brand/favicon.png', backgroundColor: '#070707' },
    extra: {
      variant: VARIANT,
      defaultServer: process.env.BW_SERVER_URL || 'https://blackandwhiteops.com',
      eas: process.env.EAS_PROJECT_ID ? { projectId: process.env.EAS_PROJECT_ID } : undefined,
    },
  },
};
