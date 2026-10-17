import React, { useState } from 'react';
import { Alert, Image, Pressable, View } from 'react-native';
import * as ImagePicker from 'expo-image-picker';
import { api, ApiError, errorMessage } from '../../api/client';
import { useSession } from '../../state/session';
import { colors, space } from '../../theme';
import { Button, Check, Field, Gap, Muted, Screen, Small, Title } from '../../components/ui';

export default function EditProfile({ navigation }: any) {
  const { user, setUser } = useSession();
  const [name, setName] = useState(user?.name ?? '');
  const [email, setEmail] = useState(user?.email ?? '');
  const [codeName, setCodeName] = useState(user?.code_name ?? '');
  const [marketing, setMarketing] = useState(!!user?.marketing_opt_in);
  const [errors, setErrors] = useState<Record<string, string | undefined>>({});
  const [busy, setBusy] = useState(false);

  async function pickPhoto() {
    const r = await ImagePicker.launchImageLibraryAsync({ mediaTypes: ['images'], allowsEditing: true, aspect: [1, 1], quality: 0.7 });
    if (r.canceled || !r.assets?.[0]) return;
    const a = r.assets[0];
    const form = new FormData();
    form.append('photo', { uri: a.uri, name: a.fileName ?? 'photo.jpg', type: a.mimeType ?? 'image/jpeg' } as any);
    try {
      const res = await api.upload('me/avatar', form);
      setUser(res.user);
    } catch (e) {
      Alert.alert('Photo not saved', errorMessage(e));
    }
  }

  async function save() {
    setBusy(true);
    setErrors({});
    try {
      const r = await api.patch('me', { name, email, code_name: codeName || null, marketing_opt_in: marketing });
      setUser(r.user);
      navigation.goBack();
    } catch (e) {
      if (e instanceof ApiError) setErrors({ name: e.field('name'), email: e.field('email'), code_name: e.field('code_name'), general: Object.keys(e.errors).length ? undefined : e.message });
      else setErrors({ general: errorMessage(e) });
    } finally {
      setBusy(false);
    }
  }

  return (
    <Screen edges={['bottom']}>
      <Pressable onPress={pickPhoto} style={{ alignSelf: 'flex-start', marginBottom: space.xl }} accessibilityRole="button" accessibilityLabel="Change photo">
        {user?.avatar_url ? <Image source={{ uri: user.avatar_url }} style={{ width: 80, height: 80, borderRadius: 40 }} /> : (
          <View style={{ width: 80, height: 80, borderRadius: 40, backgroundColor: colors.graphite, alignItems: 'center', justifyContent: 'center' }}><Title style={{ color: colors.goldLight }}>{user?.name?.[0]}</Title></View>
        )}
        <Small style={{ marginTop: 6 }}>Change photo</Small>
      </Pressable>
      <Field label="Full name" value={name} onChangeText={setName} error={errors.name} />
      <Field label="Email" value={email} onChangeText={setEmail} keyboardType="email-address" autoCapitalize="none" error={errors.email} hint="Receipts and confirmations go here." />
      <Field label="Code name (optional)" value={codeName} onChangeText={setCodeName} placeholder="e.g. Falcon" error={errors.code_name} hint="Operators see this instead of your name on their device." />
      <Check on={marketing} onPress={() => setMarketing(!marketing)} label="Send me occasional news and offers" />
      {errors.general ? <Small color={colors.danger}>{errors.general}</Small> : null}
      <Gap h={space.xl} />
      <Button title="Save" onPress={save} loading={busy} />
      <Gap h={space.lg} />
      <Muted>To change your mobile number, please contact us.</Muted>
    </Screen>
  );
}
