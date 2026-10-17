import React, { useState } from 'react';
import * as WebBrowser from 'expo-web-browser';
import { api, ApiError, errorMessage } from '../../api/client';
import { useSession } from '../../state/session';
import { colors, space } from '../../theme';
import { Body, Button, Check, Field, Gap, Muted, Screen, Title, Small } from '../../components/ui';

export default function ProfileSetup() {
  const { user, setUser, config, signOut } = useSession();
  const [name, setName] = useState(user?.name === 'New client' ? '' : user?.name ?? '');
  const [email, setEmail] = useState(user?.email ?? '');
  const [terms, setTerms] = useState(false);
  const [marketing, setMarketing] = useState(false);
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [busy, setBusy] = useState(false);

  async function save() {
    setErrors({});
    setBusy(true);
    try {
      const r = await api.patch('me', { name, email, accept_terms: terms ? true : undefined, marketing_opt_in: marketing });
      setUser(r.user);
    } catch (e) {
      if (e instanceof ApiError) setErrors({ name: e.field('name') ?? '', email: e.field('email') ?? '', terms: e.field('accept_terms') ?? '', general: Object.keys(e.errors).length ? '' : e.message });
      else setErrors({ general: errorMessage(e) });
    } finally {
      setBusy(false);
    }
  }

  return (
    <Screen edges={['top', 'bottom']}>
      <Gap h={space.xl} />
      <Title>A few details</Title>
      <Muted style={{ marginTop: space.sm }}>So your operator knows who to look for, and so we can email your receipts.</Muted>
      <Gap h={space.xl} />
      <Field label="Full name" value={name} onChangeText={setName} autoComplete="name" textContentType="name" error={errors.name || null} />
      <Field label="Email" value={email} onChangeText={setEmail} autoCapitalize="none" keyboardType="email-address" autoComplete="email" textContentType="emailAddress" error={errors.email || null} />
      <Check on={terms} onPress={() => setTerms(!terms)} label={
        <Body>I accept the{' '}
          <Body color={colors.goldLight} onPress={() => config && WebBrowser.openBrowserAsync(config.legal.terms_url)}>Terms of Service</Body>,{' '}
          <Body color={colors.goldLight} onPress={() => config && WebBrowser.openBrowserAsync(config.legal.cancellation_url)}>Cancellation Policy</Body> and{' '}
          <Body color={colors.goldLight} onPress={() => config && WebBrowser.openBrowserAsync(config.legal.privacy_url)}>Privacy Policy</Body>.
        </Body>
      } />
      {errors.terms ? <Small color={colors.danger}>{errors.terms}</Small> : null}
      <Check on={marketing} onPress={() => setMarketing(!marketing)} label="Send me occasional news and offers. You can unsubscribe at any time." />
      {errors.general ? <Small color={colors.danger}>{errors.general}</Small> : null}
      <Gap h={space.xl} />
      <Button title="Continue" onPress={save} loading={busy} disabled={!name.trim() || !email.trim() || !terms} />
      <Gap h={space.md} />
      <Button variant="ghost" title="Sign out" onPress={signOut} />
    </Screen>
  );
}
