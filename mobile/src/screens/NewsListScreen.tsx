import React from 'react';
import { Dimensions, FlatList, Image, StyleSheet, Text } from 'react-native';
import type { NativeStackScreenProps } from '@react-navigation/native-stack';
import type { NewsStackParamList } from '../navigation/types';
import { newsApi } from '../api/endpoints';
import { useApiOnFocus } from '../utils/useApi';
import { EmptyState, ErrorBanner, LoadingView, PressableCard, Screen } from '../components/ui';
import { colors, radius, spacing } from '../theme/colors';
import { ruDate } from '../utils/date';

type Props = NativeStackScreenProps<NewsStackParamList, 'NewsList'>;

const CARD_WIDTH = Math.round(Dimensions.get('window').width * 0.82);

export default function NewsListScreen({ navigation }: Props) {
  const { data, loading, error } = useApiOnFocus(() => newsApi.list());

  if (loading) return <LoadingView />;

  return (
    <Screen style={styles.screen}>
      {error ? <ErrorBanner message={error} /> : null}
      <FlatList
        data={data?.items ?? []}
        keyExtractor={(n) => String(n.id)}
        horizontal
        showsHorizontalScrollIndicator={false}
        snapToInterval={CARD_WIDTH + spacing.md}
        decelerationRate="fast"
        contentContainerStyle={{ paddingVertical: spacing.lg, paddingHorizontal: spacing.md, gap: spacing.md }}
        ListEmptyComponent={<EmptyState title="Новостей пока нет" text="Публикации появятся здесь после добавления в панели управления." />}
        renderItem={({ item }) => (
          <PressableCard style={{ width: CARD_WIDTH, marginHorizontal: 0 }} onPress={() => navigation.navigate('NewsDetail', { id: item.id })}>
            {item.cover ? <Image source={{ uri: item.cover }} style={styles.cover} /> : null}
            <Text style={styles.date}>{ruDate(item.publishedAt)}</Text>
            <Text style={styles.title}>{item.title}</Text>
            {item.excerpt ? <Text style={styles.excerpt} numberOfLines={3}>{item.excerpt}</Text> : null}
          </PressableCard>
        )}
      />
    </Screen>
  );
}

const styles = StyleSheet.create({
  screen: { justifyContent: 'center' },
  cover: { width: '100%', height: 160, borderRadius: radius.md, marginBottom: spacing.sm },
  date: { fontSize: 12, fontWeight: '700', color: colors.accent, marginBottom: 4 },
  title: { fontSize: 16, fontWeight: '800', color: colors.text, marginBottom: 4 },
  excerpt: { fontSize: 14, color: colors.muted },
});
