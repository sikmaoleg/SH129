import React from 'react';
import { FlatList, Image, RefreshControl, StyleSheet, Text } from 'react-native';
import type { NativeStackScreenProps } from '@react-navigation/native-stack';
import type { NewsStackParamList } from '../navigation/types';
import { newsApi } from '../api/endpoints';
import { useApi } from '../utils/useApi';
import { EmptyState, ErrorBanner, LoadingView, PressableCard, Screen } from '../components/ui';
import { colors, radius, spacing } from '../theme/colors';
import { ruDate } from '../utils/date';

type Props = NativeStackScreenProps<NewsStackParamList, 'NewsList'>;

export default function NewsListScreen({ navigation }: Props) {
  const { data, loading, refreshing, error, refresh } = useApi(() => newsApi.list());

  if (loading) return <LoadingView />;

  return (
    <Screen>
      {error ? <ErrorBanner message={error} /> : null}
      <FlatList
        data={data?.items ?? []}
        keyExtractor={(n) => String(n.id)}
        contentContainerStyle={{ paddingVertical: spacing.md }}
        refreshControl={<RefreshControl refreshing={refreshing} onRefresh={refresh} />}
        ListEmptyComponent={<EmptyState title="Новостей пока нет" text="Публикации появятся здесь после добавления в панели управления." />}
        renderItem={({ item }) => (
          <PressableCard onPress={() => navigation.navigate('NewsDetail', { id: item.id })}>
            {item.cover ? <Image source={{ uri: item.cover }} style={styles.cover} /> : null}
            <Text style={styles.date}>{ruDate(item.publishedAt)}</Text>
            <Text style={styles.title}>{item.title}</Text>
            {item.excerpt ? <Text style={styles.excerpt} numberOfLines={2}>{item.excerpt}</Text> : null}
          </PressableCard>
        )}
      />
    </Screen>
  );
}

const styles = StyleSheet.create({
  cover: { width: '100%', height: 160, borderRadius: radius.md, marginBottom: spacing.sm },
  date: { fontSize: 12, fontWeight: '700', color: colors.accent, marginBottom: 4 },
  title: { fontSize: 16, fontWeight: '800', color: colors.text, marginBottom: 4 },
  excerpt: { fontSize: 14, color: colors.muted },
});
