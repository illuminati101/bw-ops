import * as Location from 'expo-location';

export async function currentPosition(timeoutMs = 8000): Promise<{ lat: number; lng: number } | null> {
  try {
    const perm = await Location.requestForegroundPermissionsAsync();
    if (perm.status !== 'granted') return null;
    const last = await Location.getLastKnownPositionAsync({ maxAge: 60000 });
    const fresh = Location.getCurrentPositionAsync({ accuracy: Location.Accuracy.High });
    const timeout = new Promise<null>((r) => setTimeout(() => r(null), timeoutMs));
    const pos = (await Promise.race([fresh, timeout])) ?? last;
    return pos ? { lat: pos.coords.latitude, lng: pos.coords.longitude } : null;
  } catch {
    return null;
  }
}
