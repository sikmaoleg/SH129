import React, { useState } from 'react';
import { FlatList, Image, Pressable, StyleSheet, Text, View } from 'react-native';
import { ratingApi } from '../api/endpoints';
import { useApi } from '../utils/useApi';
import { EmptyState, ErrorBanner, LoadingView, Screen } from '../components/ui';
import { colors, radius, spacing } from '../theme/colors';

export default function RatingScreen() {
  const [period, setPeriod] = useState<'all' | 'month'>('all');
  const { data, loading, error } = useApi(() => ratingApi.list(period), [period]);

  return (
    <Screen>
      <View style={styles.tabs}>
        <Pressable style={[styles.tab, period === 'all' && styles.tabActive]} onPress={() => setPeriod('all')}>
          <Text style={[styles.tabText, period === 'all' && styles.tabTextActive]}>За всё время</Text>
        </Pressable>
        <Pressable style={[styles.tab, period === 'month' && styles.tabActive]} onPress={() => setPeriod('month')}>
          <Text style={[styles.tabText, period === 'month' && styles.tabTextActive]}>За месяц</Text>
        </Pressable>
      </View>

      {error ? <ErrorBanner message={error} /> : null}
      {loading ? (
        <LoadingView />
      ) : (
        <FlatList
          data={data?.items ?? []}
          keyExtractor={(r) => String(r.id)}
          contentContainerStyle={{ paddingVertical: spacing.sm }}
          ListEmptyComponent={<EmptyState title="Рейтинг пока пуст" />}
          renderItem={({ item: r }) => (
            <View style={[styles.row, r.isMe && styles.rowMe]}>
              <Text style={[styles.place, r.place <= 3 && styles.placeTop]}>{r.place}</Text>
              {r.avatar ? (
                <Image source={{ uri: r.avatar }} style={styles.avatar} />
              ) : (
                <View style={[styles.avatar, styles.avatarPlaceholder]}>
                  <Text style={styles.avatarInitial}>{r.name[0]}</Text>
                </View>
              )}
              <View style={{ flex: 1 }}>
                <Text style={styles.name}>{r.name}{r.isMe ? '  ·  это вы' : ''}</Text>
                <Text style={styles.level}>{r.levelName}</Text>
              </View>
              <Text style={styles.points}>{r.points}</Text>
            </View>
          )}
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
  row: {
    flexDirection: 'row', alignItems: 'center', gap: spacing.sm, backgroundColor: colors.white,
    borderRadius: radius.md, padding: spacing.sm, marginHorizontal: spacing.md, marginTop: spacing.sm,
    borderWidth: 1, borderColor: colors.border,
  },
  rowMe: { borderColor: colors.accent, borderWidth: 1.5 },
  place: { width: 28, textAlign: 'center', fontWeight: '800', color: colors.muted },
  placeTop: { color: colors.accent, fontSize: 16 },
  avatar: { width: 40, height: 40, borderRadius: 20 },
  avatarPlaceholder: { backgroundColor: colors.blue100, alignItems: 'center', justifyContent: 'center' },
  avatarInitial: { fontWeight: '800', color: colors.blue700 },
  name: { fontSize: 14, fontWeight: '700', color: colors.text },
  level: { fontSize: 12, color: colors.muted },
  points: { fontSize: 16, fontWeight: '800', color: colors.text },
});
