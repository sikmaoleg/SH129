import React, { useState } from 'react';
import { FlatList, Pressable, RefreshControl, StyleSheet, Text, View } from 'react-native';
import type { NativeStackScreenProps } from '@react-navigation/native-stack';
import type { EventsStackParamList } from '../navigation/types';
import { eventsApi } from '../api/endpoints';
import { useApi } from '../utils/useApi';
import { EmptyState, ErrorBanner, LoadingView, PressableCard, Screen } from '../components/ui';
import { colors, radius, spacing } from '../theme/colors';
import { ruDay } from '../utils/date';

type Props = NativeStackScreenProps<EventsStackParamList, 'EventsList'>;

export default function EventsListScreen({ navigation }: Props) {
  const [filter, setFilter] = useState<'upcoming' | 'past'>('upcoming');
  const { data, loading, refreshing, error, refresh } = useApi(() => eventsApi.list(filter), [filter]);

  return (
    <Screen>
      <View style={styles.tabs}>
        <Pressable style={[styles.tab, filter === 'upcoming' && styles.tabActive]} onPress={() => setFilter('upcoming')}>
          <Text style={[styles.tabText, filter === 'upcoming' && styles.tabTextActive]}>Ближайшие</Text>
        </Pressable>
        <Pressable style={[styles.tab, filter === 'past' && styles.tabActive]} onPress={() => setFilter('past')}>
          <Text style={[styles.tabText, filter === 'past' && styles.tabTextActive]}>Прошедшие</Text>
        </Pressable>
      </View>

      {error ? <ErrorBanner message={error} /> : null}
      {loading ? (
        <LoadingView />
      ) : (
        <FlatList
          data={data?.items ?? []}
          keyExtractor={(ev) => String(ev.id)}
          contentContainerStyle={{ paddingVertical: spacing.sm }}
          refreshControl={<RefreshControl refreshing={refreshing} onRefresh={refresh} />}
          ListEmptyComponent={
            <EmptyState
              title={filter === 'past' ? 'История пока пуста' : 'Афиша пока пуста'}
              text="Загляните позже."
            />
          }
          renderItem={({ item: ev }) => {
            const { day, month } = ruDay(ev.startsAt);
            return (
              <PressableCard style={styles.row} onPress={() => navigation.navigate('EventDetail', { id: ev.id })}>
                <View style={styles.dateBox}>
                  <Text style={styles.day}>{day}</Text>
                  <Text style={styles.month}>{month}</Text>
                </View>
                <View style={{ flex: 1 }}>
                  <Text style={styles.title}>{ev.title}</Text>
                  <View style={styles.metaRow}>
                    {ev.location ? <Text style={styles.meta}>{ev.location}</Text> : null}
                    {ev.directionTitle ? <Text style={styles.meta}>· {ev.directionTitle}</Text> : null}
                  </View>
                  {ev.freeSlots !== null ? (
                    <Text style={styles.slots}>Свободно мест: {ev.freeSlots}</Text>
                  ) : null}
                </View>
              </PressableCard>
            );
          }}
        />
      )}
    </Screen>
  );
}

const styles = StyleSheet.create({
  tabs: { flexDirection: 'row', gap: spacing.sm, paddingHorizontal: spacing.md, paddingTop: spacing.md },
  tab: { paddingHorizontal: spacing.md, paddingVertical: 8, borderRadius: radius.pill, backgroundColor: colors.white, borderWidth: 1, borderColor: colors.border },
  tabActive: { backgroundColor: colors.accent, borderColor: colors.accent },
  tabText: { fontWeight: '700', color: colors.text, fontSize: 13 },
  tabTextActive: { color: '#fff' },
  row: { flexDirection: 'row', alignItems: 'center', gap: spacing.md },
  dateBox: { width: 52, alignItems: 'center' },
  day: { fontSize: 20, fontWeight: '800', color: colors.blue900 },
  month: { fontSize: 11, fontWeight: '700', color: colors.muted },
  title: { fontSize: 15, fontWeight: '700', color: colors.text },
  metaRow: { flexDirection: 'row', gap: 4, marginTop: 2 },
  meta: { fontSize: 13, color: colors.muted },
  slots: { fontSize: 12, color: colors.ok, marginTop: 4, fontWeight: '600' },
});
