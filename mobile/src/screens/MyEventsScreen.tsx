import React, { useState } from 'react';
import { Alert, SectionList, StyleSheet, Text, View } from 'react-native';
import { myEventsApi, eventsApi } from '../api/endpoints';
import { useApiOnFocus } from '../utils/useApi';
import { Button, EmptyState, ErrorBanner, LoadingView, PressableCard, Screen, Tag } from '../components/ui';
import { colors, spacing } from '../theme/colors';
import { ruDate } from '../utils/date';
import { ApiError } from '../api/client';
import type { NativeStackScreenProps } from '@react-navigation/native-stack';
import type { CabinetStackParamList } from '../navigation/types';
import type { MyEventRow } from '../api/types';

type Props = NativeStackScreenProps<CabinetStackParamList, 'MyEvents'>;

const STATUS_LABELS: Record<string, { label: string; tone: 'blue' | 'ok' | 'danger' | 'muted' }> = {
  registered: { label: 'Записан', tone: 'blue' },
  attended: { label: 'Участие принято', tone: 'ok' },
  no_show: { label: 'Не пришёл', tone: 'danger' },
  cancelled: { label: 'Отменено', tone: 'muted' },
};

export default function MyEventsScreen({ navigation }: Props) {
  const { data, loading, error, reload } = useApiOnFocus(() => myEventsApi.list());
  const [cancelling, setCancelling] = useState<number | null>(null);

  if (loading) return <LoadingView />;
  if (error) return <ErrorBanner message={error} />;

  const onCancel = (id: number) => {
    Alert.alert('Отменить запись?', 'Вы уверены, что хотите отменить запись на это мероприятие?', [
      { text: 'Не отменять', style: 'cancel' },
      {
        text: 'Отменить запись',
        style: 'destructive',
        onPress: async () => {
          setCancelling(id);
          try {
            await eventsApi.cancel(id);
            await reload();
          } catch (e) {
            Alert.alert('Ошибка', e instanceof ApiError ? e.message : 'Не удалось отменить запись.');
          } finally {
            setCancelling(null);
          }
        },
      },
    ]);
  };

  const sections = [
    { title: 'Предстоящие', data: data?.upcoming ?? [] },
    { title: 'История участия', data: data?.past ?? [] },
  ];

  return (
    <Screen>
      <SectionList
        sections={sections}
        keyExtractor={(item) => String(item.id)}
        contentContainerStyle={{ paddingVertical: spacing.sm }}
        renderSectionHeader={({ section }) => <Text style={styles.sectionTitle}>{section.title}</Text>}
        renderSectionFooter={({ section }) =>
          section.data.length === 0 ? (
            <EmptyState
              title={section.title === 'Предстоящие' ? 'Вы никуда не записаны' : 'История пуста'}
              text={section.title === 'Предстоящие' ? 'Загляните в афишу и выберите мероприятие.' : undefined}
            />
          ) : null
        }
        renderItem={({ item }: { item: MyEventRow }) => {
          const st = STATUS_LABELS[item.status];
          return (
            <PressableCard onPress={() => navigation.navigate('EventDetail', { id: item.id })}>
              <Text style={styles.date}>{ruDate(item.startsAt, true)}</Text>
              <Text style={styles.title}>{item.title}</Text>
              <View style={styles.rowBottom}>
                {st ? <Tag label={st.label} tone={st.tone} /> : null}
                {item.pointsAwarded > 0 ? <Text style={styles.points}>+{item.pointsAwarded}</Text> : null}
              </View>
              {item.status === 'registered' && (
                <Button
                  title="Отменить запись"
                  variant="outline"
                  loading={cancelling === item.id}
                  onPress={() => onCancel(item.id)}
                  style={{ marginTop: spacing.sm }}
                />
              )}
            </PressableCard>
          );
        }}
      />
    </Screen>
  );
}

const styles = StyleSheet.create({
  sectionTitle: { fontSize: 17, fontWeight: '800', color: colors.text, marginHorizontal: spacing.md, marginTop: spacing.md, marginBottom: spacing.sm },
  date: { fontSize: 12, color: colors.muted, marginBottom: 4 },
  title: { fontSize: 15, fontWeight: '700', color: colors.text, marginBottom: spacing.xs },
  rowBottom: { flexDirection: 'row', alignItems: 'center', gap: spacing.sm },
  points: { fontSize: 14, fontWeight: '800', color: colors.ok },
});
