import React, { useEffect, useMemo, useRef } from 'react';
import { StyleProp, View, ViewStyle } from 'react-native';
import { WebView } from 'react-native-webview';
import { api } from '../api/client';
import { useSession } from '../state/session';
import { colors } from '../theme';
import { mapHtml } from './mapHtml';

import type { MapData } from './mapHtml';

export type { MapData, MapPoint } from './mapHtml';

/**
 * Leaflet in a WebView: works on Android and iOS without a Google Maps key, and looks like the website's map.
 * Points update in place (no reload), so live tracking is smooth.
 */
export function MapView({ data, style, interactive = true, center }: { data: MapData; style?: StyleProp<ViewStyle>; interactive?: boolean; center?: { lat: number; lng: number } }) {
  const ref = useRef<WebView>(null);
  const { config } = useSession();
  const tiles = config?.maps?.tile_url || 'https://tile.openstreetmap.org/{z}/{x}/{y}.png';
  const c = center ?? (config ? { lat: config.region.lat, lng: config.region.lng } : { lat: -33.9249, lng: 18.4241 });

  const html = useMemo(() => mapHtml(tiles, interactive, c), [tiles, interactive, c.lat, c.lng]);

  const send = () => ref.current?.postMessage(JSON.stringify({ type: 'update', data }));
  useEffect(() => {
    send();
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [JSON.stringify(data)]);

  return (
    <View style={[{ backgroundColor: '#121211', overflow: 'hidden' }, style]}>
      <WebView
        ref={ref}
        // The server address as base URL gives tile requests a proper Referer (required by OpenStreetMap's tile policy).
        source={{ html, baseUrl: api.server }}
        originWhitelist={['*']}
        onMessage={(e) => e.nativeEvent.data === 'ready' && send()}
        style={{ flex: 1, backgroundColor: '#121211' }}
        scrollEnabled={false}
        overScrollMode="never"
        bounces={false}
        javaScriptEnabled
        setSupportMultipleWindows={false}
        androidLayerType="hardware"
        containerStyle={{ backgroundColor: colors.night }}
      />
    </View>
  );
}
