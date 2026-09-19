import React, { useState } from 'react';
import { Image, KeyboardAvoidingView, Platform, ScrollView, StyleSheet, Text, TextInput, View } from 'react-native';
import type { NativeStackScreenProps } from '@react-navigation/native-stack';
import type { CabinetStackParamList } from '../navigation/types';
import { useAuth } from '../context/AuthContext';
import { Button, ErrorBanner } from '../components/ui';
import { colors, radius, spacing } from '../theme/colors';
import { ApiError } from '../api/client';

type Props = NativeStackScreenProps<CabinetStackParamList, 'Login'>;

export default function LoginScreen({ navigation }: Props) {
  const { login } = useAuth();
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');

  const onSubmit = async () => {
    if (!email || !password) {
      setError('Заполните оба поля.');
      return;
    }
    setLoading(true);
    setError('');
    try {
      await login(email.trim(), password);
    } catch (e) {
      setError(e instanceof ApiError ? e.message : 'Не удалось войти.');
    } finally {
      setLoading(false);
    }
  };

  return (
    <KeyboardAvoidingView style={{ flex: 1 }} behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
      <ScrollView contentContainerStyle={styles.container} keyboardShouldPersistTaps="handled">
        <View style={styles.logoWrap}>
          <View style={styles.logoCircle}>
            <Text style={styles.logoText}>МГ</Text>
          </View>
          <Text style={styles.title}>Молодая Гвардия</Text>
          <Text style={styles.subtitle}>Щёлково</Text>
        </View>

        <Text style={styles.heading}>Вход в личный кабинет</Text>

        {error ? <ErrorBanner message={error} /> : null}

        <View style={styles.field}>
          <Text style={styles.label}>Электронная почта</Text>
          <TextInput
            style={styles.input}
            value={email}
            onChangeText={setEmail}
            autoCapitalize="none"
            autoCorrect={false}
            keyboardType="email-address"
            placeholder="you@example.ru"
            placeholderTextColor={colors.muted}
          />
        </View>
        <View style={styles.field}>
          <Text style={styles.label}>Пароль</Text>
          <TextInput
            style={styles.input}
            value={password}
            onChangeText={setPassword}
            secureTextEntry
            placeholder="••••••••"
            placeholderTextColor={colors.muted}
          />
        </View>

        <Button title="Войти" onPress={onSubmit} loading={loading} style={{ marginTop: spacing.sm }} />

        <View style={styles.footer}>
          <Text style={styles.footerText}>Ещё не в организации?</Text>
          <Text style={styles.footerLink} onPress={() => navigation.navigate('Register')}>
            {' '}Подать заявку
          </Text>
        </View>
      </ScrollView>
    </KeyboardAvoidingView>
  );
}

const styles = StyleSheet.create({
  container: { flexGrow: 1, backgroundColor: colors.white, padding: spacing.lg, justifyContent: 'center' },
  logoWrap: { alignItems: 'center', marginBottom: spacing.xl },
  logoCircle: {
    width: 64, height: 64, borderRadius: 32, backgroundColor: colors.blue900,
    alignItems: 'center', justifyContent: 'center', marginBottom: spacing.sm,
  },
  logoText: { color: '#fff', fontWeight: '800', fontSize: 18 },
  title: { fontSize: 20, fontWeight: '800', color: colors.blue900 },
  subtitle: { fontSize: 14, color: colors.muted },
  heading: { fontSize: 22, fontWeight: '800', color: colors.text, marginBottom: spacing.lg },
  field: { marginBottom: spacing.md },
  label: { fontSize: 13, fontWeight: '600', color: colors.muted, marginBottom: 6 },
  input: {
    borderWidth: 1.5, borderColor: colors.border, borderRadius: radius.md,
    paddingHorizontal: spacing.md, paddingVertical: 12, fontSize: 15, color: colors.text,
  },
  footer: { flexDirection: 'row', justifyContent: 'center', marginTop: spacing.lg, flexWrap: 'wrap' },
  footerText: { color: colors.muted, fontSize: 14 },
  footerLink: { color: colors.accent, fontWeight: '700', fontSize: 14 },
});
