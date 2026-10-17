import React, { useEffect, useMemo, useRef } from 'react';
import { StyleProp, View, ViewStyle } from 'react-native';
import { useSession } from '../state/session';
import { mapHtml, type MapData } from './mapHtml';

export type { MapData, MapPoint } from './mapHtml';

/** Browser preview of the map: the same Leaflet page as the phones, in an iframe. */
export function MapView({ data, style, interactive = true, center }: { data: MapData; style?: StyleProp<ViewStyle>; interactive?: boolean; center?: { lat: number; lng: number } }) {
  const ref = useRef<HTMLIFrameElement>(null);
  const { config } = useSession();
  const tiles = config?.maps?.tile_url || 'https://tile.openstreetmap.org/{z}/{x}/{y}.png';
  const c = center ?? (config ? { lat: config.region.lat, lng: config.region.lng } : { lat: -33.9249, lng: 18.4241 });
  const html = useMemo(() => mapHtml(tiles, interactive, c).replace('window.ReactNativeWebView&&', 'parent.postMessage("ready","*");0&&'), [tiles, interactive, c.lat, c.lng]);
  const send = () => ref.current?.contentWindow?.postMessage(JSON.stringify({ type: 'update', data }), '*');
  useEffect(() => { send(); }, [JSON.stringify(data)]);
  useEffect(() => {
    const on = (e: MessageEvent) => { if (e.data === 'ready') send(); };
    window.addEventListener('message', on);
    return () => window.removeEventListener('message', on);
  });
  return (
    <View style={[{ backgroundColor: '#121211', overflow: 'hidden' }, style]}>
      <iframe ref={ref} srcDoc={html} style={{ border: 0, width: '100%', height: '100%' }} title="Map" />
    </View>
  );
}
