import React, { useState } from 'react';
import { KeyboardAvoidingView, Linking, Platform, Pressable, ScrollView, StyleSheet, Text, TextInput, View } from 'react-native';
import type { NativeStackScreenProps } from '@react-navigation/native-stack';
import type { AuthGateParamList } from '../navigation/types';
import { authApi } from '../api/endpoints';
import { Button, ErrorBanner } from '../components/ui';
import { colors, radius, spacing } from '../theme/colors';
import { API_BASE, ApiError } from '../api/client';

const SITE = API_BASE.replace(/\/api$/, '');
const openDoc = (path: string) => Linking.openURL(`${SITE}/${path}`);

/** Полных лет по дате ГГГГ-ММ-ДД, либо null. */
function ageOf(date: string): number | null {
  const m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(date.trim());
  if (!m) return null;
  const now = new Date();
  let age = now.getFullYear() - Number(m[1]);
  const mm = now.getMonth() + 1, dd = now.getDate();
  if (mm < Number(m[2]) || (mm === Number(m[2]) && dd < Number(m[3]))) age--;
  return age;
}

function Check({ on, onPress, children }: { on: boolean; onPress: () => void; children: React.ReactNode }) {
  return (
    <Pressable style={styles.checkRow} onPress={onPress} accessibilityRole="checkbox" accessibilityState={{ checked: on }}>
      <View style={[styles.checkbox, on && styles.checkboxOn]}>
        {on ? <Text style={styles.checkmark}>✓</Text> : null}
      </View>
      <Text style={styles.checkLabel}>{children}</Text>
    </Pressable>
  );
}

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
    guardianName: '', guardianPhone: '',
  });
  const [agreePd, setAgreePd] = useState(false);
  const [agreePublic, setAgreePublic] = useState(false);
  const [agreeGuardian, setAgreeGuardian] = useState(false);
  const age = ageOf(form.birthDate);
  const minor = age !== null && age < 18;
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');
  const [done, setDone] = useState(false);

  const set = (k: keyof typeof form) => (v: string) => setForm((f) => ({ ...f, [k]: v }));

  const onSubmit = async () => {
    setError('');
    if (!agreePd) {
      setError('Нужно согласие на обработку персональных данных.');
      return;
    }
    if (minor && (!form.guardianName.trim() || !form.guardianPhone.trim() || !agreeGuardian)) {
      setError('Вам меньше 18 лет: укажите ФИО и телефон родителя или законного представителя и подтвердите его согласие.');
      return;
    }
    setLoading(true);
    try {
      await authApi.register({ ...form, agreePd, agreePublic, agreeGuardian: minor && agreeGuardian });
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

        {minor ? (
          <View style={styles.guardian}>
            <Text style={styles.guardianTitle}>Родитель или законный представитель</Text>
            <Text style={styles.hint}>Вам меньше 18 лет, поэтому анкету подаём с согласия родителя или законного представителя.</Text>
            <Field label="ФИО родителя или представителя" value={form.guardianName} onChangeText={set('guardianName')} />
            <Field label="Его телефон" value={form.guardianPhone} onChangeText={set('guardianPhone')} keyboardType="phone-pad" placeholder="+7 900 000-00-00" />
            <Check on={agreeGuardian} onPress={() => setAgreeGuardian((v) => !v)}>
              Мой родитель или законный представитель ознакомлен с согласием и Политикой и согласен на обработку моих персональных данных
            </Check>
          </View>
        ) : null}

        <Check on={agreePd} onPress={() => setAgreePd((v) => !v)}>
          Даю <Text style={styles.link} onPress={() => openDoc('consent.php')}>согласие на обработку персональных данных</Text> и ознакомлен(а) с <Text style={styles.link} onPress={() => openDoc('privacy.php')}>Политикой</Text>
        </Check>
        <Check on={agreePublic} onPress={() => setAgreePublic((v) => !v)}>
          Разрешаю показывать моё имя и фото на сайте (<Text style={styles.link} onPress={() => openDoc('consent-public.php')}>согласие на распространение</Text>). Необязательно.
        </Check>

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
  checkRow: { flexDirection: 'row', alignItems: 'flex-start', marginVertical: spacing.sm },
  checkbox: {
    width: 22, height: 22, borderRadius: 6, borderWidth: 1.5, borderColor: colors.border,
    alignItems: 'center', justifyContent: 'center', marginRight: spacing.sm,
  },
  checkboxOn: { backgroundColor: colors.accent, borderColor: colors.accent },
  checkmark: { color: '#fff', fontWeight: '800', fontSize: 13 },
  checkLabel: { flex: 1, fontSize: 14, color: colors.text, lineHeight: 20 },
  link: { color: colors.accent, textDecorationLine: 'underline' },
  guardian: { padding: spacing.md, borderRadius: radius.md, backgroundColor: '#F1F3F9', marginBottom: spacing.md },
  guardianTitle: { fontSize: 15, fontWeight: '700', color: colors.text, marginBottom: 4 },
  footerLink: { color: colors.accent, fontWeight: '700', fontSize: 14, textAlign: 'center', marginTop: spacing.lg, marginBottom: spacing.xl },
  doneWrap: { flex: 1, alignItems: 'center', justifyContent: 'center', padding: spacing.xl, backgroundColor: colors.white },
  doneTitle: { fontSize: 20, fontWeight: '800', color: colors.text, marginBottom: spacing.sm },
  doneText: { fontSize: 15, color: colors.muted, textAlign: 'center' },
});
