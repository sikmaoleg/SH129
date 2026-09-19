import React from 'react';
import { FlatList, Image, RefreshControl, ScrollView, StyleSheet, Text, View } from 'react-native';
import type { NativeStackScreenProps } from '@react-navigation/native-stack';
import type { HomeStackParamList } from '../navigation/types';
import { homeApi } from '../api/endpoints';
import { useApi } from '../utils/useApi';
import { Card, EmptyState, ErrorBanner, LoadingView, PressableCard } from '../components/ui';
import { colors, radius, spacing } from '../theme/colors';
import { ruDate, ruDay, plural } from '../utils/date';

type Props = NativeStackScreenProps<HomeStackParamList, 'HomeScreen'>;

export default function HomeScreen({ navigation }: Props) {
  const { data, loading, refreshing, error, refresh } = useApi(() => homeApi.get());

  if (loading) return <LoadingView />;

  return (
    <ScrollView
      style={styles.screen}
      refreshControl={<RefreshControl refreshing={refreshing} onRefresh={refresh} />}
    >
      {error ? <ErrorBanner message={error} /> : null}

      {data?.heroPhotos?.length ? (
        <FlatList
          data={data.heroPhotos}
          horizontal
          pagingEnabled
          showsHorizontalScrollIndicator={false}
          keyExtractor={(_, i) => String(i)}
          renderItem={({ item }) => (
            <View style={styles.heroSlide}>
              <Image source={{ uri: item.image }} style={styles.heroImage} />
              {item.caption ? <Text style={styles.heroCaption}>{item.caption}</Text> : null}
            </View>
          )}
        />
      ) : null}

      <View style={styles.statsRow}>
        <View style={styles.stat}>
          <Text style={styles.statNum}>{data?.stats.volunteers}+</Text>
          <Text style={styles.statLabel}>{plural(data?.stats.volunteers ?? 0, 'волонтёр', 'волонтёра', 'волонтёров')}</Text>
        </View>
        <View style={styles.stat}>
          <Text style={styles.statNum}>{data?.stats.events}</Text>
          <Text style={styles.statLabel}>{plural(data?.stats.events ?? 0, 'мероприятие', 'мероприятия', 'мероприятий')}</Text>
        </View>
        <View style={styles.stat}>
          <Text style={styles.statNum}>{data?.stats.hours}+</Text>
          <Text style={styles.statLabel}>{plural(data?.stats.hours ?? 0, 'час', 'часа', 'часов')}</Text>
        </View>
      </View>

      {data?.honor ? (
        <Card style={styles.honorCard}>
          {data.honor.avatar ? (
            <Image source={{ uri: data.honor.avatar }} style={styles.honorAvatar} />
          ) : (
            <View style={[styles.honorAvatar, styles.honorAvatarPlaceholder]}>
              <Text style={styles.honorInitial}>{data.honor.name[0]}</Text>
            </View>
          )}
          <View style={{ flex: 1 }}>
            <Text style={styles.honorEyebrow}>Волонтёр месяца</Text>
            <Text style={styles.honorName}>{data.honor.name}</Text>
            {data.honor.note ? <Text style={styles.honorNote}>{data.honor.note}</Text> : null}
          </View>
        </Card>
      ) : null}

      <Text style={styles.sectionTitle}>Новости отделения</Text>
      {data?.news?.length ? (
        data.news.map((n) => (
          <PressableCard key={n.id} style={styles.newsCard} onPress={() => navigation.navigate('NewsDetail', { id: n.id })}>
            {n.cover ? <Image source={{ uri: n.cover }} style={styles.newsCover} /> : null}
            <Text style={styles.newsDate}>{ruDate(n.publishedAt)}</Text>
            <Text style={styles.newsTitle}>{n.title}</Text>
            {n.excerpt ? <Text style={styles.newsExcerpt} numberOfLines={2}>{n.excerpt}</Text> : null}
          </PressableCard>
        ))
      ) : (
        <EmptyState title="Новостей пока нет" />
      )}

      <Text style={styles.sectionTitle}>Ближайшие мероприятия</Text>
      {data?.events?.length ? (
        data.events.map((ev) => {
          const { day, month } = ruDay(ev.startsAt);
          return (
            <PressableCard key={ev.id} style={styles.eventRow} onPress={() => navigation.navigate('EventDetail', { id: ev.id })}>
              <View style={styles.eventDate}>
                <Text style={styles.eventDay}>{day}</Text>
                <Text style={styles.eventMonth}>{month}</Text>
              </View>
              <View style={{ flex: 1 }}>
                <Text style={styles.eventTitle}>{ev.title}</Text>
                {ev.location ? <Text style={styles.eventMeta}>{ev.location}</Text> : null}
              </View>
            </PressableCard>
          );
        })
      ) : (
        <EmptyState title="Афиша пока пуста" />
      )}
      <View style={{ height: spacing.xl }} />
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: colors.blue025 },
  heroSlide: { width: 340, height: 200, marginLeft: spacing.md, marginTop: spacing.md, borderRadius: radius.lg, overflow: 'hidden' },
  heroImage: { width: '100%', height: '100%' },
  heroCaption: {
    position: 'absolute', bottom: 10, left: 12, color: '#fff', fontWeight: '700',
    backgroundColor: 'rgba(10,20,50,.55)', paddingHorizontal: 10, paddingVertical: 4, borderRadius: radius.sm,
  },
  statsRow: { flexDirection: 'row', justifyContent: 'space-around', marginVertical: spacing.lg },
  stat: { alignItems: 'center' },
  statNum: { fontSize: 22, fontWeight: '800', color: colors.blue900 },
  statLabel: { fontSize: 12, color: colors.muted },
  honorCard: { flexDirection: 'row', alignItems: 'center', gap: spacing.md },
  honorAvatar: { width: 56, height: 56, borderRadius: 28 },
  honorAvatarPlaceholder: { backgroundColor: colors.blue100, alignItems: 'center', justifyContent: 'center' },
  honorInitial: { fontSize: 20, fontWeight: '800', color: colors.blue700 },
  honorEyebrow: { fontSize: 12, fontWeight: '700', color: colors.accent, textTransform: 'uppercase' },
  honorName: { fontSize: 16, fontWeight: '800', color: colors.text },
  honorNote: { fontSize: 13, color: colors.muted, marginTop: 2 },
  sectionTitle: { fontSize: 18, fontWeight: '800', color: colors.text, marginHorizontal: spacing.md, marginTop: spacing.md, marginBottom: spacing.sm },
  newsCard: {},
  newsCover: { width: '100%', height: 140, borderRadius: radius.md, marginBottom: spacing.sm },
  newsDate: { fontSize: 12, fontWeight: '700', color: colors.accent, marginBottom: 4 },
  newsTitle: { fontSize: 16, fontWeight: '800', color: colors.text, marginBottom: 4 },
  newsExcerpt: { fontSize: 14, color: colors.muted },
  eventRow: { flexDirection: 'row', alignItems: 'center', gap: spacing.md },
  eventDate: { width: 52, alignItems: 'center' },
  eventDay: { fontSize: 20, fontWeight: '800', color: colors.blue900 },
  eventMonth: { fontSize: 11, fontWeight: '700', color: colors.muted },
  eventTitle: { fontSize: 15, fontWeight: '700', color: colors.text },
  eventMeta: { fontSize: 13, color: colors.muted, marginTop: 2 },
});
