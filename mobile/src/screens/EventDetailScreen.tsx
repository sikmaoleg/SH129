import React, { useState } from 'react';
import { Image, ScrollView, StyleSheet, Text, View } from 'react-native';
import type { NativeStackScreenProps } from '@react-navigation/native-stack';
import type { EventsStackParamList } from '../navigation/types';
import { eventsApi } from '../api/endpoints';
import { useApiOnFocus } from '../utils/useApi';
import { useAuth } from '../context/AuthContext';
import { Button, ErrorBanner, LoadingView, Tag } from '../components/ui';
import { colors, radius, spacing } from '../theme/colors';
import { ruDate } from '../utils/date';
import { ApiError } from '../api/client';

type Props = NativeStackScreenProps<EventsStackParamList, 'EventDetail'>;

const STATUS_LABELS: Record<string, { label: string; tone: 'blue' | 'ok' | 'danger' | 'muted' }> = {
  registered: { label: 'Вы записаны', tone: 'blue' },
  attended: { label: 'Участие принято', tone: 'ok' },
  no_show: { label: 'Не пришли', tone: 'danger' },
  cancelled: { label: 'Отменено', tone: 'muted' },
};

export default function EventDetailScreen({ route }: Props) {
  const { id } = route.params;
  const { user } = useAuth();
  const { data, loading, error, reload } = useApiOnFocus(() => eventsApi.detail(id), [id]);
  const [actionLoading, setActionLoading] = useState(false);
  const [actionError, setActionError] = useState('');

  if (loading) return <LoadingView />;
  if (error) return <ErrorBanner message={error} />;
  if (!data) return null;

  const ev = data.item;
  const paragraphs = (ev.description ?? '').split(/\n{2,}/).map((p) => p.trim()).filter(Boolean);

  const runAction = async (action: 'signup' | 'cancel') => {
    setActionLoading(true);
    setActionError('');
    try {
      if (action === 'signup') await eventsApi.signup(id);
      else await eventsApi.cancel(id);
      await reload();
    } catch (e) {
      setActionError(e instanceof ApiError ? e.message : 'Не удалось выполнить действие.');
    } finally {
      setActionLoading(false);
    }
  };

  return (
    <ScrollView style={styles.screen} contentContainerStyle={{ padding: spacing.md }}>
      <Text style={styles.title}>{ev.title}</Text>
      <Text style={styles.date}>{ruDate(ev.startsAt, true)}</Text>
      <View style={styles.metaRow}>
        {ev.location ? <Tag label={ev.location} tone="muted" /> : null}
        {ev.directionTitle ? <Tag label={ev.directionTitle} tone="blue" /> : null}
        {ev.freeSlots !== null ? <Tag label={`Свободно: ${ev.freeSlots} из ${ev.capacity}`} tone="ok" /> : null}
      </View>

      {ev.cover ? <Image source={{ uri: ev.cover }} style={styles.cover} /> : null}

      {paragraphs.map((p, i) => (
        <Text key={i} style={styles.paragraph}>{p}</Text>
      ))}

      {actionError ? <ErrorBanner message={actionError} /> : null}

      <View style={styles.actions}>
        {!user ? (
          <Text style={styles.hint}>Войдите, чтобы записаться на мероприятие.</Text>
        ) : ev.isPast ? (
          <Tag label="Мероприятие уже прошло" tone="muted" />
        ) : ev.myStatus === 'registered' ? (
          <>
            <Tag label={STATUS_LABELS.registered.label} tone={STATUS_LABELS.registered.tone} />
            <Button title="Отменить запись" variant="outline" onPress={() => runAction('cancel')} loading={actionLoading} style={{ marginTop: spacing.sm }} />
          </>
        ) : ev.myStatus && STATUS_LABELS[ev.myStatus] ? (
          <Tag label={STATUS_LABELS[ev.myStatus].label} tone={STATUS_LABELS[ev.myStatus].tone} />
        ) : ev.freeSlots !== null && ev.freeSlots <= 0 ? (
          <Tag label="Свободных мест не осталось" tone="warn" />
        ) : (
          <Button title="Записаться на мероприятие" onPress={() => runAction('signup')} loading={actionLoading} />
        )}
      </View>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: colors.white },
  title: { fontSize: 22, fontWeight: '800', color: colors.text, marginBottom: 4 },
  date: { fontSize: 14, color: colors.muted, marginBottom: spacing.sm },
  metaRow: { flexDirection: 'row', flexWrap: 'wrap', gap: spacing.xs, marginBottom: spacing.md },
  cover: { width: '100%', height: 180, borderRadius: radius.md, marginBottom: spacing.md },
  paragraph: { fontSize: 15, color: colors.text, lineHeight: 22, marginBottom: spacing.sm },
  actions: { marginTop: spacing.md },
  hint: { fontSize: 14, color: colors.muted },
});
