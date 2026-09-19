import React, { useState } from 'react';
import { Dimensions, FlatList, Image, Modal, Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import type { NativeStackScreenProps } from '@react-navigation/native-stack';
import type { NewsStackParamList } from '../navigation/types';
import { newsApi } from '../api/endpoints';
import { useApi } from '../utils/useApi';
import { ErrorBanner, LoadingView } from '../components/ui';
import { colors, radius, spacing } from '../theme/colors';
import { ruDate } from '../utils/date';

type Props = NativeStackScreenProps<NewsStackParamList, 'NewsDetail'>;

const screenWidth = Dimensions.get('window').width;

export default function NewsDetailScreen({ route }: Props) {
  const { id } = route.params;
  const { data, loading, error } = useApi(() => newsApi.detail(id), [id]);
  const [lightboxIndex, setLightboxIndex] = useState<number | null>(null);

  if (loading) return <LoadingView />;
  if (error) return <ErrorBanner message={error} />;
  if (!data) return null;

  const { item } = data;
  const images = item.images.length ? item.images : item.cover ? [item.cover] : [];
  const paragraphs = item.body.split(/\n{2,}/).map((p) => p.trim()).filter(Boolean);

  return (
    <>
      <ScrollView style={styles.screen} contentContainerStyle={{ padding: spacing.md }}>
        <Text style={styles.date}>{ruDate(item.publishedAt, true)}</Text>
        <Text style={styles.title}>{item.title}</Text>

        {images.length > 0 && (
          <View style={styles.gallery}>
            {images.map((img, i) => (
              <Pressable key={img} onPress={() => setLightboxIndex(i)} style={styles.galleryItem}>
                <Image source={{ uri: img }} style={styles.galleryImage} />
              </Pressable>
            ))}
          </View>
        )}

        {item.excerpt ? <Text style={styles.excerpt}>{item.excerpt}</Text> : null}
        {paragraphs.map((p, i) => (
          <Text key={i} style={styles.paragraph}>{p}</Text>
        ))}
      </ScrollView>

      <Modal visible={lightboxIndex !== null} transparent animationType="fade" onRequestClose={() => setLightboxIndex(null)}>
        <View style={styles.lightbox}>
          <Pressable style={styles.lightboxClose} onPress={() => setLightboxIndex(null)}>
            <Text style={styles.lightboxCloseText}>✕</Text>
          </Pressable>
          <FlatList
            data={images}
            horizontal
            pagingEnabled
            initialScrollIndex={lightboxIndex ?? 0}
            getItemLayout={(_, i) => ({ length: screenWidth, offset: screenWidth * i, index: i })}
            keyExtractor={(img) => img}
            renderItem={({ item: img }) => (
              <View style={styles.lightboxSlide}>
                <Image source={{ uri: img }} style={styles.lightboxImage} resizeMode="contain" />
              </View>
            )}
          />
        </View>
      </Modal>
    </>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: colors.white },
  date: { fontSize: 13, fontWeight: '700', color: colors.accent, marginBottom: 6 },
  title: { fontSize: 22, fontWeight: '800', color: colors.text, marginBottom: spacing.md },
  gallery: { flexDirection: 'row', flexWrap: 'wrap', gap: spacing.xs, marginBottom: spacing.md },
  galleryItem: { width: '48%', aspectRatio: 1, borderRadius: radius.md, overflow: 'hidden' },
  galleryImage: { width: '100%', height: '100%' },
  excerpt: { fontSize: 16, fontWeight: '700', color: colors.text, marginBottom: spacing.md },
  paragraph: { fontSize: 15, color: colors.text, lineHeight: 22, marginBottom: spacing.sm },
  lightbox: { flex: 1, backgroundColor: 'rgba(5,15,38,0.96)', justifyContent: 'center' },
  lightboxClose: { position: 'absolute', top: 56, right: 20, zIndex: 1, padding: spacing.sm },
  lightboxCloseText: { color: '#fff', fontSize: 24 },
  lightboxSlide: { width: screenWidth, alignItems: 'center', justifyContent: 'center' },
  lightboxImage: { width: screenWidth - 32, height: '80%' },
});
