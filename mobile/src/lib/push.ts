import { Platform } from 'react-native';
import * as Device from 'expo-device';
import * as Notifications from 'expo-notifications';
import { api } from '../api/client';
import { EAS_PROJECT_ID, VARIANT } from '../config';
import { KEYS, storage } from './storage';

Notifications.setNotificationHandler({
  handleNotification: async () => ({ shouldShowBanner: true, shouldShowList: true, shouldPlaySound: true, shouldSetBadge: false }),
});

/**
 * Registers for push notifications. Needs an Expo (EAS) project ID baked into the build; without one the apps
 * still work and fall back to SMS plus in-app refresh.
 */
export async function registerPush(): Promise<string | null> {
  try {
    if (!Device.isDevice || !EAS_PROJECT_ID) return null;
    if (Platform.OS === 'android') {
      await Notifications.setNotificationChannelAsync('default', { name: 'Bookings and alerts', importance: Notifications.AndroidImportance.HIGH, vibrationPattern: [0, 300, 200, 300], lightColor: '#c9a86a' });
    }
    let { status } = await Notifications.getPermissionsAsync();
    if (status !== 'granted') status = (await Notifications.requestPermissionsAsync()).status;
    if (status !== 'granted') return null;
    const token = (await Notifications.getExpoPushTokenAsync({ projectId: EAS_PROJECT_ID })).data;
    await api.post('me/devices', { token, platform: Platform.OS === 'ios' ? 'ios' : 'android', app: VARIANT });
    await storage.set(KEYS.pushToken, token);
    return token;
  } catch {
    return null;
  }
}
