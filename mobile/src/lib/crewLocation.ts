import * as Location from 'expo-location';
import * as TaskManager from 'expo-task-manager';
import { DEFAULT_SERVER } from '../config';
import { KEYS, storage } from './storage';

export const LOCATION_TASK = 'bw-crew-location';

/**
 * Runs in the background (Android foreground service / iOS background location) while an operator is online.
 * It must be defined at module load, before the app renders, so it is imported from index.ts.
 */
TaskManager.defineTask<{ locations: Location.LocationObject[] }>(LOCATION_TASK, async ({ data, error }) => {
  if (error || !data?.locations?.length) return;
  await postPoints(data.locations);
});

async function postPoints(locations: Location.LocationObject[]) {
  const [server, token] = await Promise.all([storage.get(KEYS.server), storage.get(KEYS.token)]);
  if (!token) return;
  const points = locations.slice(-50).map((l) => ({
    lat: l.coords.latitude,
    lng: l.coords.longitude,
    heading: l.coords.heading ?? null,
    speed: l.coords.speed ?? null,
    accuracy: l.coords.accuracy ?? null,
    at: new Date(l.timestamp).toISOString(),
  }));
  try {
    await fetch(`${server || DEFAULT_SERVER}/api/v1/crew/location`, {
      method: 'POST',
      headers: { Accept: 'application/json', 'Content-Type': 'application/json', Authorization: `Bearer ${token}`, 'X-App': 'crew' },
      body: JSON.stringify({ points }),
    });
  } catch {
    // Offline for a moment: the next update carries on.
  }
}

let foregroundWatch: Location.LocationSubscription | null = null;

/** Start sharing location. Returns 'background', 'foreground' (app must stay open) or 'denied'. */
export async function startTracking(): Promise<'background' | 'foreground' | 'denied'> {
  const fg = await Location.requestForegroundPermissionsAsync();
  if (fg.status !== 'granted') return 'denied';
  let bg = false;
  try {
    bg = (await Location.requestBackgroundPermissionsAsync()).status === 'granted';
  } catch {
    bg = false;
  }
  if (bg) {
    try {
      if (!(await Location.hasStartedLocationUpdatesAsync(LOCATION_TASK))) {
        await Location.startLocationUpdatesAsync(LOCATION_TASK, {
          accuracy: Location.Accuracy.High,
          timeInterval: 15000,
          distanceInterval: 25,
          deferredUpdatesInterval: 15000,
          pausesUpdatesAutomatically: false,
          activityType: Location.ActivityType.AutomotiveNavigation,
          showsBackgroundLocationIndicator: true,
          foregroundService: {
            notificationTitle: 'BW Ops Crew: you are online',
            notificationBody: 'Sharing your location with the control room. Go offline in the app to stop.',
            notificationColor: '#c9a86a',
            killServiceOnDestroy: false,
          },
        });
      }
      return 'background';
    } catch {
      // Fall through to foreground tracking.
    }
  }
  if (!foregroundWatch) {
    foregroundWatch = await Location.watchPositionAsync({ accuracy: Location.Accuracy.High, timeInterval: 15000, distanceInterval: 25 }, (l) => postPoints([l]));
  }
  return 'foreground';
}

export async function stopTracking() {
  foregroundWatch?.remove();
  foregroundWatch = null;
  try {
    if (await Location.hasStartedLocationUpdatesAsync(LOCATION_TASK)) await Location.stopLocationUpdatesAsync(LOCATION_TASK);
  } catch {}
}
