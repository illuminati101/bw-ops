import React, { useState } from 'react';
import { Alert, Image, Pressable, StyleSheet, View } from 'react-native';
import * as ImagePicker from 'expo-image-picker';
import * as WebBrowser from 'expo-web-browser';
import { api, errorMessage } from '../../api/client';
import { useSession } from '../../state/session';
import { colors, radius, space } from '../../theme';
import { Banner, Body, Button, Check, Gap, Icon, Label, Muted, Screen, Small, Title } from '../../components/ui';

type Asset = { uri: string; name: string; type: string };

export default function Verify({ navigation }: any) {
  const { user, setUser, config } = useSession();
  const status = user?.verification?.status ?? 'unverified';
  const [idDoc, setIdDoc] = useState<Asset | null>(null);
  const [selfie, setSelfie] = useState<Asset | null>(null);
  const [consent, setConsent] = useState(false);
  const [busy, setBusy] = useState(false);

  async function pick(kind: 'id' | 'selfie', source: 'camera' | 'library') {
    const perm = source === 'camera' ? await ImagePicker.requestCameraPermissionsAsync() : await ImagePicker.requestMediaLibraryPermissionsAsync();
    if (!perm.granted) {
      Alert.alert('Permission needed', source === 'camera' ? 'Allow camera access in Settings to take the photo.' : 'Allow photo access in Settings to choose a photo.');
      return;
    }
    const opts: ImagePicker.ImagePickerOptions = { mediaTypes: ['images'], quality: 0.7, cameraType: kind === 'selfie' ? ImagePicker.CameraType.front : ImagePicker.CameraType.back };
    const r = source === 'camera' ? await ImagePicker.launchCameraAsync(opts) : await ImagePicker.launchImageLibraryAsync(opts);
    if (r.canceled || !r.assets?.[0]) return;
    const a = r.assets[0];
    const asset = { uri: a.uri, name: a.fileName ?? `${kind}.jpg`, type: a.mimeType ?? 'image/jpeg' };
    if (kind === 'id') setIdDoc(asset);
    else setSelfie(asset);
  }

  function choose(kind: 'id' | 'selfie') {
    if (kind === 'selfie') return pick('selfie', 'camera');
    Alert.alert('ID document', 'Your South African ID, driver\'s licence or passport photo page.', [
      { text: 'Take a photo', onPress: () => pick('id', 'camera') },
      { text: 'Choose from photos', onPress: () => pick('id', 'library') },
      { text: 'Cancel', style: 'cancel' },
    ]);
  }

  async function submit() {
    if (!idDoc || !selfie) return;
    setBusy(true);
    try {
      const form = new FormData();
      form.append('id_document', idDoc as any);
      form.append('selfie', selfie as any);
      const r = await api.upload('me/verification', form);
      setUser(r.user);
    } catch (e) {
      Alert.alert('Not submitted', errorMessage(e));
    } finally {
      setBusy(false);
    }
  }

  if (status === 'verified' || status === 'pending') {
    return (
      <Screen edges={['bottom']}>
        <Icon name={status === 'verified' ? 'check-circle' : 'clock'} size={40} color={colors.gold} />
        <Gap />
        <Title>{status === 'verified' ? 'You are verified' : 'We are reviewing your documents'}</Title>
        <Muted style={{ marginTop: space.sm }}>{status === 'verified' ? 'Thank you. There is nothing more to do.' : 'This usually takes less than an hour during business hours. We will let you know by SMS. You can request bookings in the meantime.'}</Muted>
        <Gap h={space.xl} />
        <Button title="Done" onPress={() => navigation.goBack()} />
      </Screen>
    );
  }

  return (
    <Screen edges={['bottom']}>
      <Title>Verify your identity</Title>
      <Muted style={{ marginTop: space.sm }}>Every client is verified once. It protects our operators, and it means we know exactly who we are protecting.</Muted>
      {status === 'rejected' ? <><Gap /><Banner tone="danger" title="We could not verify your last upload" text={`${user?.verification?.note ?? ''}\nPlease try again with clear, well-lit photos.`.trim()} /></> : null}
      <Gap h={space.xl} />
      <Slot label="ID document" help="ID card, driver's licence or passport" icon="credit-card" asset={idDoc} onPress={() => choose('id')} />
      <Gap h={space.md} />
      <Slot label="Selfie" help="Your face, clearly lit, no sunglasses or hat" icon="camera" asset={selfie} onPress={() => choose('selfie')} />
      <Gap h={space.lg} />
      <Check on={consent} onPress={() => setConsent(!consent)} label={
        <Body>I agree that {config?.brand.name ?? 'Black & White Ops'} may use these images to confirm my identity. They are stored securely, seen only by authorised staff, and deleted when I close my account.{' '}
          <Body color={colors.goldLight} onPress={() => config && WebBrowser.openBrowserAsync(config.legal.privacy_url)}>Privacy policy</Body>
        </Body>
      } />
      <Gap h={space.xl} />
      <Button title="Submit for review" onPress={submit} loading={busy} disabled={!idDoc || !selfie || !consent} />
    </Screen>
  );
}

function Slot({ label, help, icon, asset, onPress }: { label: string; help: string; icon: any; asset: Asset | null; onPress: () => void }) {
  return (
    <Pressable onPress={onPress} style={styles.slot} accessibilityRole="button" accessibilityLabel={`${label}${asset ? ', added' : ''}`}>
      {asset ? <Image source={{ uri: asset.uri }} style={styles.thumb} /> : <View style={[styles.thumb, { alignItems: 'center', justifyContent: 'center' }]}><Icon name={icon} size={24} color={colors.gold} /></View>}
      <View style={{ flex: 1 }}>
        <Label>{label}</Label>
        <Small>{asset ? 'Added. Tap to replace.' : help}</Small>
      </View>
      <Icon name={asset ? 'check' : 'chevron-right'} color={asset ? colors.gold : colors.ash} />
    </Pressable>
  );
}

const styles = StyleSheet.create({
  slot: { flexDirection: 'row', alignItems: 'center', gap: space.md, padding: space.md, borderRadius: radius.lg, borderWidth: 1, borderStyle: 'dashed', borderColor: colors.lineStrong },
  thumb: { width: 64, height: 64, borderRadius: radius.md, backgroundColor: colors.graphite },
});
