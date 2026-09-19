import React from 'react';
import { RefreshControl, ScrollView, StyleSheet, Text, View } from 'react-native';
import type { NativeStackScreenProps } from '@react-navigation/native-stack';
import type { CabinetStackParamList } from '../navigation/types';
import { cabinetApi } from '../api/endpoints';
import { useAuth } from '../context/AuthContext';
import { useApi } from '../utils/useApi';
import { Card, EmptyState, ErrorBanner, LoadingView, PressableCard } from '../components/ui';
import { colors, radius, spacing } from '../theme/colors';
import { plural, ruDate } from '../utils/date';

type Props = NativeStackScreenProps<CabinetStackParamList, 'CabinetHome'>;

export default function CabinetHomeScreen({ navigation }: Props) {
  const { user } = useAuth();
  const { data, loading, refreshing, error, refresh } = useApi(() => cabinetApi.overview());

  if (loading) return <LoadingView />;

  return (
    <ScrollView style={styles.screen} refreshControl={<RefreshControl refreshing={refreshing} onRefresh={refresh} />}>
      {error ? <ErrorBanner message={error} /> : null}

      <Card>
        <Text style={styles.hello}>Здравствуйте, {user?.firstName}!</Text>
        <View style={styles.levelRow}>
          <Text style={styles.levelName}>{data?.level.current.name}</Text>
          <Text style={styles.points}>{user?.points} {plural(user?.points ?? 0, 'балл', 'балла', 'баллов')}</Text>
        </View>
        {data?.level.next ? (
          <>
            <View style={styles.progressTrack}>
              <View style={[styles.progressFill, { width: `${data.level.progress}%` }]} />
            </View>
            <Text style={styles.progressHint}>
              До уровня «{data.level.next.name}» осталось {data.level.to_next} {plural(data.level.to_next, 'балл', 'балла', 'баллов')}
            </Text>
          </>
        ) : (
          <Text style={styles.progressHint}>Максимальный уровень достигнут</Text>
        )}
      </Card>

      <View style={styles.kpiGrid}>
        <PressableCard style={styles.kpi} onPress={() => navigation.navigate('Rating')}>
          <Text style={styles.kpiLabel}>Место в рейтинге</Text>
          <Text style={styles.kpiValue}>{data?.myPlace} <Text style={styles.kpiOf}>из {data?.totalVolunteers}</Text></Text>
        </PressableCard>
        <PressableCard style={styles.kpi} onPress={() => navigation.navigate('MyEvents')}>
          <Text style={styles.kpiLabel}>Мероприятий посещено</Text>
          <Text style={styles.kpiValue}>{data?.attended}</Text>
        </PressableCard>
        <PressableCard style={styles.kpi} onPress={() => navigation.navigate('Badges')}>
          <Text style={styles.kpiLabel}>Бейджей получено</Text>
          <Text style={styles.kpiValue}>{data?.badgesEarned}</Text>
        </PressableCard>
        <View style={[styles.kpi, styles.card]}>
          <Text style={styles.kpiLabel}>В организации с</Text>
          <Text style={styles.kpiValueSm}>{ruDate(user?.memberSince)}</Text>
        </View>
      </View>

      <Text style={styles.sectionTitle}>Записан на мероприятия</Text>
      {data?.upcoming.length ? (
        data.upcoming.map((ev) => (
          <PressableCard key={ev.id} style={styles.eventRow} onPress={() => navigation.navigate('EventDetail', { id: ev.id })}>
            <Text style={styles.eventDate}>{ruDate(ev.startsAt, true)}</Text>
            <Text style={styles.eventTitle}>{ev.title}</Text>
            {ev.location ? <Text style={styles.eventMeta}>{ev.location}</Text> : null}
          </PressableCard>
        ))
      ) : (
        <EmptyState title="Пока никуда не записаны" text="Выберите мероприятие из афиши ниже." />
      )}

      {data?.openEvents.length ? (
        <>
          <Text style={styles.sectionTitle}>Открыта запись</Text>
          {data.openEvents.map((ev) => (
            <PressableCard key={ev.id} style={styles.eventRow} onPress={() => navigation.navigate('EventDetail', { id: ev.id })}>
              <Text style={styles.eventDate}>{ruDate(ev.startsAt, true)}</Text>
              <Text style={styles.eventTitle}>{ev.title}</Text>
              {ev.location ? <Text style={styles.eventMeta}>{ev.location}</Text> : null}
            </PressableCard>
          ))}
        </>
      ) : null}

      <Text style={styles.sectionTitle}>История начислений</Text>
      {data?.history.length ? (
        <Card>
          {data.history.map((h, i) => (
            <View key={i} style={[styles.historyRow, i > 0 && styles.historyRowBorder]}>
              <Text style={[styles.historyPoints, { color: h.points >= 0 ? colors.ok : colors.danger }]}>
                {h.points > 0 ? '+' : ''}{h.points}
              </Text>
              <View style={{ flex: 1 }}>
                <Text style={styles.historyReason}>{h.reason}</Text>
                <Text style={styles.historyDate}>{ruDate(h.createdAt)}</Text>
              </View>
            </View>
          ))}
        </Card>
      ) : (
        <EmptyState title="Начислений пока нет" text="Баллы появятся после первого мероприятия." />
      )}
      <View style={{ height: spacing.xl }} />
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: colors.blue025 },
  hello: { fontSize: 18, fontWeight: '800', color: colors.text, marginBottom: spacing.sm },
  levelRow: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'baseline', marginBottom: spacing.sm },
  levelName: { fontSize: 16, fontWeight: '700', color: colors.blue700 },
  points: { fontSize: 20, fontWeight: '800', color: colors.text },
  progressTrack: { height: 8, borderRadius: 4, backgroundColor: colors.border, overflow: 'hidden' },
  progressFill: { height: '100%', backgroundColor: colors.accent, borderRadius: 4 },
  progressHint: { fontSize: 12, color: colors.muted, marginTop: 6 },
  kpiGrid: { flexDirection: 'row', flexWrap: 'wrap', paddingHorizontal: spacing.md, gap: spacing.sm },
  kpi: { width: '47%', marginHorizontal: 0 },
  card: { backgroundColor: colors.white, borderRadius: radius.lg, padding: spacing.md, borderWidth: 1, borderColor: colors.border },
  kpiLabel: { fontSize: 12, color: colors.muted, marginBottom: 6 },
  kpiValue: { fontSize: 22, fontWeight: '800', color: colors.text },
  kpiValueSm: { fontSize: 15, fontWeight: '800', color: colors.text },
  kpiOf: { fontSize: 13, fontWeight: '600', color: colors.muted },
  sectionTitle: { fontSize: 17, fontWeight: '800', color: colors.text, marginHorizontal: spacing.md, marginTop: spacing.lg, marginBottom: spacing.sm },
  eventRow: {},
  eventDate: { fontSize: 12, color: colors.muted, marginBottom: 2 },
  eventTitle: { fontSize: 15, fontWeight: '700', color: colors.text },
  eventMeta: { fontSize: 13, color: colors.muted, marginTop: 2 },
  historyRow: { flexDirection: 'row', gap: spacing.md, paddingVertical: spacing.sm },
  historyRowBorder: { borderTopWidth: 1, borderTopColor: colors.border },
  historyPoints: { fontSize: 15, fontWeight: '800', width: 44 },
  historyReason: { fontSize: 14, color: colors.text },
  historyDate: { fontSize: 12, color: colors.muted, marginTop: 2 },
});
