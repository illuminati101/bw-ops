import React, { useEffect, useRef, useState } from 'react';
import { FlatList, KeyboardAvoidingView, Platform, Pressable, StyleSheet, TextInput, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { api, errorMessage } from '../../api/client';
import type { ChatMessage } from '../../api/types';
import { IS_CREW } from '../../config';
import { useLoader } from '../../lib/hooks';
import { fmt } from '../../lib/format';
import { colors, fonts, radius, space } from '../../theme';
import { Body, Icon, Muted, Small } from '../../components/ui';

/** Booking chat between client, operator and control room. Phone numbers are never shown. */
export default function Chat({ route, navigation }: any) {
  const insets = useSafeAreaInsets();
  const path = IS_CREW ? `crew/jobs/${route.params.jobId}/messages` : `bookings/${route.params.reference}/messages`;
  const [body, setBody] = useState('');
  const [sending, setSending] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const list = useRef<FlatList>(null);
  const { data, setData } = useLoader(async () => api.get<{ messages: ChatMessage[]; open: boolean }>(path), [path], 4000);

  useEffect(() => {
    navigation.setOptions({ title: IS_CREW ? 'Client chat' : 'Messages' });
  }, [navigation]);

  async function send() {
    const text = body.trim();
    if (!text) return;
    setSending(true);
    setError(null);
    try {
      const r = await api.post<{ message: ChatMessage }>(path, { body: text });
      setBody('');
      setData((d) => (d ? { ...d, messages: [...d.messages, r.message] } : d));
    } catch (e) {
      setError(errorMessage(e));
    } finally {
      setSending(false);
    }
  }

  const who = (role: string) => (role === 'staff' ? 'Control room' : role === 'operator' ? 'Operator' : 'Client');

  return (
    <KeyboardAvoidingView style={{ flex: 1, backgroundColor: colors.night }} behavior={Platform.OS === 'ios' ? 'padding' : undefined} keyboardVerticalOffset={Platform.OS === 'ios' ? 90 : 0}>
      <FlatList
        ref={list}
        data={data?.messages ?? []}
        keyExtractor={(m) => String(m.id)}
        contentContainerStyle={{ padding: space.lg, gap: space.sm, flexGrow: 1 }}
        onContentSizeChange={() => list.current?.scrollToEnd({ animated: false })}
        ListEmptyComponent={<Muted center style={{ marginTop: space.xxl }}>{IS_CREW ? 'Messages go to the client and the control room.' : 'Messages go to your operator and our control room. Your phone number stays private.'}</Muted>}
        renderItem={({ item }) => (
          <View style={[styles.bubble, item.mine ? styles.mine : styles.theirs]}>
            {!item.mine ? <Small>{who(item.sender_role)}</Small> : null}
            <Body color={item.mine ? colors.night : colors.bone}>{item.body}</Body>
            <Small color={item.mine ? 'rgba(7,7,7,0.55)' : colors.ash} style={{ marginTop: 2, alignSelf: 'flex-end' }}>{fmt.time(item.created_at)}</Small>
          </View>
        )}
      />
      {error ? <Small color={colors.danger} style={{ paddingHorizontal: space.lg }}>{error}</Small> : null}
      {data?.open !== false ? (
        <View style={[styles.composer, { paddingBottom: insets.bottom + space.sm }]}>
          <TextInput value={body} onChangeText={setBody} placeholder="Write a message" placeholderTextColor={colors.ash} selectionColor={colors.gold} multiline maxLength={1000} style={styles.input} />
          <Pressable onPress={send} disabled={sending || !body.trim()} style={[styles.send, (!body.trim() || sending) && { opacity: 0.4 }]} accessibilityRole="button" accessibilityLabel="Send">
            <Icon name="send" color={colors.night} />
          </Pressable>
        </View>
      ) : (
        <Muted center style={{ padding: space.lg, paddingBottom: insets.bottom + space.lg }}>Chat has closed for this booking.</Muted>
      )}
    </KeyboardAvoidingView>
  );
}

const styles = StyleSheet.create({
  bubble: { maxWidth: '82%', borderRadius: 18, paddingHorizontal: 14, paddingVertical: 9 },
  mine: { alignSelf: 'flex-end', backgroundColor: colors.gold },
  theirs: { alignSelf: 'flex-start', backgroundColor: colors.graphite },
  composer: { flexDirection: 'row', alignItems: 'flex-end', gap: space.sm, paddingHorizontal: space.md, paddingTop: space.sm, borderTopWidth: StyleSheet.hairlineWidth, borderColor: colors.lineStrong },
  input: { flex: 1, minHeight: 44, maxHeight: 120, borderRadius: radius.md, backgroundColor: colors.graphite, color: colors.bone, paddingHorizontal: 14, paddingVertical: 11, fontFamily: fonts.body, fontSize: 15 },
  send: { width: 44, height: 44, borderRadius: 22, backgroundColor: colors.gold, alignItems: 'center', justifyContent: 'center' },
});
