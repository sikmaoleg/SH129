import React from 'react';
import { FlatList, StyleSheet, Text, View } from 'react-native';
import { badgesApi } from '../api/endpoints';
import { useApi } from '../utils/useApi';
import { EmptyState, ErrorBanner, LoadingView, Screen } from '../components/ui';
import { colors, radius, spacing } from '../theme/colors';
import { ruDate } from '../utils/date';

export default function BadgesScreen() {
  const { data, loading, error } = useApi(() => badgesApi.list());

  if (loading) return <LoadingView />;

  const earned = data?.items.filter((b) => b.earned).length ?? 0;
  const total = data?.items.length ?? 0;

  return (
    <Screen>
      {error ? <ErrorBanner message={error} /> : null}
      <Text style={styles.summary}>Получено {earned} из {total}</Text>
      <FlatList
        data={data?.items ?? []}
        keyExtractor={(b) => String(b.id)}
        numColumns={2}
        columnWrapperStyle={{ gap: spacing.sm, paddingHorizontal: spacing.md }}
        contentContainerStyle={{ gap: spacing.sm, paddingBottom: spacing.xl }}
        ListEmptyComponent={<EmptyState title="Бейджи не настроены" />}
        renderItem={({ item: b }) => (
          <View style={[styles.badge, !b.earned && styles.badgeLocked]}>
            <Text style={styles.badgeIcon}>{b.icon || '🏅'}</Text>
            <Text style={styles.badgeTitle}>{b.title}</Text>
            <Text style={styles.badgeDesc}>{b.description}</Text>
            {b.earned && b.awardedAt ? <Text style={styles.badgeDate}>{ruDate(b.awardedAt)}</Text> : null}
          </View>
        )}
      />
    </Screen>
  );
}

const styles = StyleSheet.create({
  summary: { fontSize: 14, color: colors.muted, marginHorizontal: spacing.md, marginVertical: spacing.md },
  badge: {
    flex: 1, backgroundColor: colors.white, borderRadius: radius.lg, padding: spacing.md,
    borderWidth: 1, borderColor: colors.border, alignItems: 'center',
  },
  badgeLocked: { opacity: 0.4 },
  badgeIcon: { fontSize: 28, marginBottom: spacing.xs },
  badgeTitle: { fontSize: 14, fontWeight: '800', color: colors.text, textAlign: 'center' },
  badgeDesc: { fontSize: 12, color: colors.muted, textAlign: 'center', marginTop: 4 },
  badgeDate: { fontSize: 11, color: colors.ok, fontWeight: '700', marginTop: spacing.xs },
});
