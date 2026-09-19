import React, { useState } from 'react';
import { KeyboardAvoidingView, Platform, Pressable, ScrollView, StyleSheet, Text, TextInput, View } from 'react-native';
import type { NativeStackScreenProps } from '@react-navigation/native-stack';
import type { AuthGateParamList } from '../navigation/types';
import { authApi } from '../api/endpoints';
import { Button, ErrorBanner } from '../components/ui';
import { colors, radius, spacing } from '../theme/colors';
import { ApiError } from '../api/client';

type Props = NativeStackScreenProps<AuthGateParamList, 'Register'>;

function Field({
  label, value, onChangeText, placeholder, keyboardType, secureTextEntry, hint,
}: {
  label: string; value: string; onChangeText: (v: string) => void; placeholder?: string;
  keyboardType?: 'default' | 'email-address' | 'phone-pad'; secureTextEntry?: boolean; hint?: string;
}) {
  return (
    <View style={styles.field}>
      <Text style={styles.label}>{label}</Text>
      <TextInput
        style={styles.input}
        value={value}
        onChangeText={onChangeText}
        placeholder={placeholder}
        placeholderTextColor={colors.muted}
        keyboardType={keyboardType}
        secureTextEntry={secureTextEntry}
        autoCapitalize={keyboardType === 'email-address' ? 'none' : 'sentences'}
      />
      {hint ? <Text style={styles.hint}>{hint}</Text> : null}
    </View>
  );
}

export default function RegisterScreen({ navigation }: Props) {
  const [form, setForm] = useState({
    lastName: '', firstName: '', middleName: '', email: '', phone: '',
    birthDate: '', vk: '', telegram: '', school: '', password: '', password2: '',
  });
  const [agree, setAgree] = useState(false);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');
  const [done, setDone] = useState(false);

  const set = (k: keyof typeof form) => (v: string) => setForm((f) => ({ ...f, [k]: v }));

  const onSubmit = async () => {
    setError('');
    if (!agree) {
      setError('Нужно согласие на обработку персональных данных.');
      return;
    }
    setLoading(true);
    try {
      const { message } = await authApi.register({ ...form, agree });
      setDone(true);
    } catch (e) {
      setError(e instanceof ApiError ? e.message : 'Не удалось отправить заявку.');
    } finally {
      setLoading(false);
    }
  };

  if (done) {
    return (
      <View style={styles.doneWrap}>
        <Text style={styles.doneTitle}>Заявка отправлена</Text>
        <Text style={styles.doneText}>
          Учётная запись создана. Вход в личный кабинет откроется после того, как администратор одобрит заявку.
        </Text>
        <Button title="К входу" onPress={() => navigation.replace('Login')} style={{ marginTop: spacing.lg }} />
      </View>
    );
  }

  return (
    <KeyboardAvoidingView style={{ flex: 1 }} behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
      <ScrollView contentContainerStyle={styles.container} keyboardShouldPersistTaps="handled">
        <Text style={styles.heading}>Анкета волонтёра</Text>
        <Text style={styles.intro}>Заполните поля — после отправки анкету проверит администратор.</Text>

        {error ? <ErrorBanner message={error} /> : null}

        <Field label="Фамилия" value={form.lastName} onChangeText={set('lastName')} />
        <Field label="Имя" value={form.firstName} onChangeText={set('firstName')} />
        <Field label="Отчество" value={form.middleName} onChangeText={set('middleName')} />
        <Field label="Электронная почта" value={form.email} onChangeText={set('email')} keyboardType="email-address" hint="Он же логин для входа" />
        <Field label="Телефон" value={form.phone} onChangeText={set('phone')} keyboardType="phone-pad" placeholder="+7 900 000-00-00" />
        <Field label="Дата рождения" value={form.birthDate} onChangeText={set('birthDate')} placeholder="ГГГГ-ММ-ДД" hint="Вступить можно с 14 лет" />
        <Field label="ВКонтакте" value={form.vk} onChangeText={set('vk')} placeholder="vk.com/username" />
        <Field label="Telegram" value={form.telegram} onChangeText={set('telegram')} placeholder="@username" />
        <Field label="Школа, колледж или работа" value={form.school} onChangeText={set('school')} />
        <Field label="Пароль" value={form.password} onChangeText={set('password')} secureTextEntry hint="Не короче 8 символов" />
        <Field label="Повторите пароль" value={form.password2} onChangeText={set('password2')} secureTextEntry />

        <Pressable style={styles.checkRow} onPress={() => setAgree((v) => !v)}>
          <View style={[styles.checkbox, agree && styles.checkboxOn]}>
            {agree ? <Text style={styles.checkmark}>✓</Text> : null}
          </View>
          <Text style={styles.checkLabel}>Согласен на обработку персональных данных</Text>
        </Pressable>

        <Button title="Отправить заявку" onPress={onSubmit} loading={loading} style={{ marginTop: spacing.md }} />

        <Text style={styles.footerLink} onPress={() => navigation.goBack()}>
          Уже состоите в организации? Войти
        </Text>
      </ScrollView>
    </KeyboardAvoidingView>
  );
}

const styles = StyleSheet.create({
  container: { flexGrow: 1, backgroundColor: colors.white, padding: spacing.lg },
  heading: { fontSize: 22, fontWeight: '800', color: colors.text, marginTop: spacing.md },
  intro: { fontSize: 14, color: colors.muted, marginBottom: spacing.lg },
  field: { marginBottom: spacing.md },
  label: { fontSize: 13, fontWeight: '600', color: colors.muted, marginBottom: 6 },
  input: {
    borderWidth: 1.5, borderColor: colors.border, borderRadius: radius.md,
    paddingHorizontal: spacing.md, paddingVertical: 12, fontSize: 15, color: colors.text,
  },
  hint: { fontSize: 12, color: colors.muted, marginTop: 4 },
  checkRow: { flexDirection: 'row', alignItems: 'center', marginVertical: spacing.sm },
  checkbox: {
    width: 22, height: 22, borderRadius: 6, borderWidth: 1.5, borderColor: colors.border,
    alignItems: 'center', justifyContent: 'center', marginRight: spacing.sm,
  },
  checkboxOn: { backgroundColor: colors.accent, borderColor: colors.accent },
  checkmark: { color: '#fff', fontWeight: '800', fontSize: 13 },
  checkLabel: { flex: 1, fontSize: 14, color: colors.text },
  footerLink: { color: colors.accent, fontWeight: '700', fontSize: 14, textAlign: 'center', marginTop: spacing.lg, marginBottom: spacing.xl },
  doneWrap: { flex: 1, alignItems: 'center', justifyContent: 'center', padding: spacing.xl, backgroundColor: colors.white },
  doneTitle: { fontSize: 20, fontWeight: '800', color: colors.text, marginBottom: spacing.sm },
  doneText: { fontSize: 15, color: colors.muted, textAlign: 'center' },
});
