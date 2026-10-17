// Browser preview only: background location is a phone feature.
export const LOCATION_TASK = 'bw-crew-location';
export async function startTracking(): Promise<'background' | 'foreground' | 'denied'> { return 'foreground'; }
export async function stopTracking() {}
