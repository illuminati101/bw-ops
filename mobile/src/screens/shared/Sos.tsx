import React, { useEffect, useRef, useState } from 'react';
import { Linking, Pressable, StyleSheet, Vibration, View } from 'react-native';
import * as Location from 'expo-location';
import { api, errorMessage } from '../../api/client';
import { currentPosition } from '../../lib/location';
import { useSession } from '../../state/session';
import { colors, fonts, space } from '../../theme';
import { Body, Button, Field, Gap, Muted, Screen, Small, Title } from '../../components/ui';

/**
 * Silent alert to the 24/7 control room. Cancelling needs the safety PIN; entering the duress PIN
 * looks identical on screen but keeps the alert live and escalates it.
 */
export default function Sos({ route, navigation }: any) {
  const { config } = useSession();
  const reference: string | undefined = route.params?.reference;
  const [alertId, setAlertId] = useState<number | null>(null);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [pin, setPin] = useState('');
  const [done, setDone] = useState(false);
  const watch = useRef<Location.LocationSubscription | null>(null);

  useEffect(() => () => watch.current?.remove(), []);

  async function raise() {
    setBusy(true);
    setError(null);
    Vibration.vibrate(80);
    const pos = await currentPosition(5000);
    try {
      const r = await api.post<{ id: number }>('sos', { lat: pos?.lat, lng: pos?.lng, booking: reference });
      setAlertId(r.id);
      try {
        watch.current = await Location.watchPositionAsync({ accuracy: Location.Accuracy.High, timeInterval: 15000, distanceInterval: 25 }, (p) => {
          api.patch(`sos/${r.id}`, { lat: p.coords.latitude, lng: p.coords.longitude }).catch(() => {});
        });
      } catch {}
    } catch (e) {
      setError(errorMessage(e));
    } finally {
      setBusy(false);
    }
  }

  async function cancel() {
    if (!alertId) return;
    setBusy(true);
    setError(null);
    try {
      await api.post(`sos/${alertId}/cancel`, { pin: pin || null, reason: 'False alarm (cancelled in app)' });
      watch.current?.remove();
      setDone(true);
    } catch (e) {
      setError(errorMessage(e));
    } finally {
      setBusy(false);
    }
  }

  const control = config?.brand.control_room_phone?.replace(/\s+/g, '');

  if (done) {
    return (
      <Screen edges={['bottom']}>
        <Title>Alert cancelled</Title>
        <Muted style={{ marginTop: space.sm }}>Our control room may still call to check on you.</Muted>
        <Gap h={space.xl} />
        <Button title="Done" onPress={() => navigation.goBack()} />
      </Screen>
    );
  }

  return (
    <Screen edges={['bottom']}>
      {alertId ? (
        <>
          <View style={styles.live}>
            <Title>Our control room has been alerted</Title>
            <Body style={{ marginTop: space.sm }} color={colors.mist}>They can see your booking and your location, and they are responding now. Keep this screen open if you can.</Body>
          </View>
          <Gap h={space.lg} />
          {control ? <Button variant="line" icon="phone" title="Call the control room" onPress={() => Linking.openURL(`tel:${control}`)} /> : null}
          <Gap h={space.sm} />
          <Button variant="line" icon="phone" title="Call 10111" onPress={() => Linking.openURL('tel:10111')} />
          <Gap h={space.xxl} />
          <Small>False alarm? Enter your safety PIN to cancel.</Small>
          <Gap h={space.sm} />
          <Field value={pin} onChangeText={(t) => setPin(t.replace(/\D/g, '').slice(0, 4))} keyboardType="number-pad" secureTextEntry placeholder="Safety PIN" error={error} maxLength={4} />
          <Button variant="ghost" title="Cancel the alert" loading={busy} onPress={cancel} />
        </>
      ) : (
        <>
          <Title>Need help now?</Title>
          <Muted style={{ marginTop: space.sm }}>This sends a silent alert with your location to our 24/7 control room. Your operator is not told on screen.</Muted>
          <Gap h={space.xxl} />
          <Pressable onLongPress={raise} delayLongPress={600} disabled={busy} style={({ pressed }) => [styles.big, pressed && { transform: [{ scale: 0.97 }] }]} accessibilityRole="button" accessibilityLabel="Press and hold to send an SOS alert">
            <Body style={{ fontFamily: fonts.bold, fontSize: 26, color: '#fff', letterSpacing: 2 }}>{busy ? 'Sending…' : 'SOS'}</Body>
            <Small color="rgba(255,255,255,0.8)">Press and hold</Small>
          </Pressable>
          {error ? <Small color={colors.danger} center style={{ marginTop: space.lg }}>{error}</Small> : null}
          <Gap h={space.xxl} />
          <Muted center>In immediate danger, also call 10111.</Muted>
        </>
      )}
    </Screen>
  );
}

const styles = StyleSheet.create({
  live: { borderRadius: 16, borderWidth: 1, borderColor: 'rgba(229,130,111,0.6)', backgroundColor: colors.dangerWash, padding: space.xl },
  big: { alignSelf: 'center', width: 200, height: 200, borderRadius: 100, backgroundColor: colors.dangerStrong, alignItems: 'center', justifyContent: 'center', borderWidth: 8, borderColor: 'rgba(200,68,47,0.35)' },
});
