import React, { useState } from 'react';
import { Alert, Image, ScrollView, StyleSheet, Text, TextInput, View } from 'react-native';
import * as ImagePicker from 'expo-image-picker';
import { useAuth } from '../context/AuthContext';
import { meApi } from '../api/endpoints';
import { Button, Card, ErrorBanner } from '../components/ui';
import { colors, radius, spacing } from '../theme/colors';
import { ApiError } from '../api/client';

function Field({
  label, value, onChangeText, keyboardType, multiline,
}: {
  label: string; value: string; onChangeText: (v: string) => void;
  keyboardType?: 'default' | 'phone-pad'; multiline?: boolean;
}) {
  return (
    <View style={styles.field}>
      <Text style={styles.label}>{label}</Text>
      <TextInput
        style={[styles.input, multiline && styles.inputMultiline]}
        value={value}
        onChangeText={onChangeText}
        keyboardType={keyboardType}
        multiline={multiline}
      />
    </View>
  );
}

export default function ProfileScreen() {
  const { user, setUser, logout } = useAuth();
  const [form, setForm] = useState({
    lastName: user?.lastName ?? '', firstName: user?.firstName ?? '', middleName: user?.middleName ?? '',
    phone: user?.phone ?? '', vk: user?.vk ?? '', telegram: user?.telegram ?? '',
    school: user?.school ?? '', about: user?.about ?? '',
  });
  const [saving, setSaving] = useState(false);
  const [uploadingAvatar, setUploadingAvatar] = useState(false);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');

  const [pwForm, setPwForm] = useState({ current: '', next: '', next2: '' });
  const [pwSaving, setPwSaving] = useState(false);
  const [pwError, setPwError] = useState('');
  const [pwSuccess, setPwSuccess] = useState('');

  const set = (k: keyof typeof form) => (v: string) => setForm((f) => ({ ...f, [k]: v }));

  const onSave = async () => {
    setSaving(true);
    setError('');
    setSuccess('');
    try {
      const { user: updated } = await meApi.update(form);
      setUser(updated);
      setSuccess('Данные сохранены.');
    } catch (e) {
      setError(e instanceof ApiError ? e.message : 'Не удалось сохранить.');
    } finally {
      setSaving(false);
    }
  };

  const onPickAvatar = async () => {
    const perm = await ImagePicker.requestMediaLibraryPermissionsAsync();
    if (!perm.granted) {
      Alert.alert('Нет доступа', 'Разрешите доступ к фото в настройках телефона.');
      return;
    }
    const result = await ImagePicker.launchImageLibraryAsync({
      mediaTypes: ImagePicker.MediaTypeOptions.Images,
      quality: 0.9,
      allowsEditing: true,
      aspect: [1, 1],
    });
    if (result.canceled || !result.assets[0]) return;

    const asset = result.assets[0];
    setUploadingAvatar(true);
    setError('');
    try {
      const ext = asset.uri.split('.').pop() || 'jpg';
      const { user: updated } = await meApi.uploadAvatar({
        uri: asset.uri,
        name: `avatar.${ext}`,
        type: asset.mimeType || `image/${ext}`,
      });
      setUser(updated);
    } catch (e) {
      setError(e instanceof ApiError ? e.message : 'Не удалось загрузить фото.');
    } finally {
      setUploadingAvatar(false);
    }
  };

  const onRemoveAvatar = async () => {
    setUploadingAvatar(true);
    try {
      const { user: updated } = await meApi.removeAvatar();
      setUser(updated);
    } catch (e) {
      setError(e instanceof ApiError ? e.message : 'Не удалось удалить фото.');
    } finally {
      setUploadingAvatar(false);
    }
  };

  const onChangePassword = async () => {
    setPwError('');
    setPwSuccess('');
    setPwSaving(true);
    try {
      await meApi.changePassword(pwForm.current, pwForm.next, pwForm.next2);
      setPwSuccess('Пароль изменён.');
      setPwForm({ current: '', next: '', next2: '' });
    } catch (e) {
      setPwError(e instanceof ApiError ? e.message : 'Не удалось изменить пароль.');
    } finally {
      setPwSaving(false);
    }
  };

  return (
    <ScrollView style={styles.screen} contentContainerStyle={{ padding: spacing.md }}>
      <Card style={{ marginHorizontal: 0, alignItems: 'center' }}>
        {user?.avatar ? (
          <Image source={{ uri: user.avatar }} style={styles.avatar} />
        ) : (
          <View style={[styles.avatar, styles.avatarPlaceholder]}>
            <Text style={styles.avatarInitial}>{(user?.firstName?.[0] ?? '') + (user?.lastName?.[0] ?? '')}</Text>
          </View>
        )}
        <View style={styles.avatarActions}>
          <Button title="Загрузить фото" variant="outline" onPress={onPickAvatar} loading={uploadingAvatar} />
          {user?.avatar ? <Button title="Удалить" variant="ghost" onPress={onRemoveAvatar} /> : null}
        </View>
      </Card>

      <Card style={{ marginHorizontal: 0 }}>
        <Text style={styles.cardTitle}>Личные данные</Text>
        {error ? <ErrorBanner message={error} /> : null}
        {success ? <Text style={styles.success}>{success}</Text> : null}
        <Field label="Фамилия" value={form.lastName} onChangeText={set('lastName')} />
        <Field label="Имя" value={form.firstName} onChangeText={set('firstName')} />
        <Field label="Отчество" value={form.middleName} onChangeText={set('middleName')} />
        <Field label="Телефон" value={form.phone} onChangeText={set('phone')} keyboardType="phone-pad" />
        <Field label="ВКонтакте" value={form.vk} onChangeText={set('vk')} />
        <Field label="Telegram" value={form.telegram} onChangeText={set('telegram')} />
        <Field label="Школа, колледж или работа" value={form.school} onChangeText={set('school')} />
        <Field label="О себе" value={form.about} onChangeText={set('about')} multiline />
        <Button title="Сохранить" onPress={onSave} loading={saving} style={{ marginTop: spacing.sm }} />
      </Card>

      <Card style={{ marginHorizontal: 0 }}>
        <Text style={styles.cardTitle}>Смена пароля</Text>
        {pwError ? <ErrorBanner message={pwError} /> : null}
        {pwSuccess ? <Text style={styles.success}>{pwSuccess}</Text> : null}
        <Field label="Текущий пароль" value={pwForm.current} onChangeText={(v) => setPwForm((f) => ({ ...f, current: v }))} />
        <Field label="Новый пароль" value={pwForm.next} onChangeText={(v) => setPwForm((f) => ({ ...f, next: v }))} />
        <Field label="Повторите новый пароль" value={pwForm.next2} onChangeText={(v) => setPwForm((f) => ({ ...f, next2: v }))} />
        <Button title="Изменить пароль" onPress={onChangePassword} loading={pwSaving} style={{ marginTop: spacing.sm }} />
      </Card>

      <Button title="Выйти" variant="outline" onPress={logout} style={{ marginBottom: spacing.xl }} />
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: colors.blue025 },
  avatar: { width: 88, height: 88, borderRadius: 44, marginBottom: spacing.sm },
  avatarPlaceholder: { backgroundColor: colors.blue100, alignItems: 'center', justifyContent: 'center' },
  avatarInitial: { fontSize: 28, fontWeight: '800', color: colors.blue700 },
  avatarActions: { flexDirection: 'row', gap: spacing.sm },
  cardTitle: { fontSize: 16, fontWeight: '800', color: colors.text, marginBottom: spacing.sm },
  field: { marginBottom: spacing.sm },
  label: { fontSize: 12, fontWeight: '600', color: colors.muted, marginBottom: 4 },
  input: {
    borderWidth: 1.5, borderColor: colors.border, borderRadius: radius.md,
    paddingHorizontal: spacing.md, paddingVertical: 10, fontSize: 14, color: colors.text,
  },
  inputMultiline: { minHeight: 80, textAlignVertical: 'top' },
  success: { color: colors.ok, fontWeight: '600', marginBottom: spacing.sm },
});
