import * as SecureStore from 'expo-secure-store';

// Сайт работает по обычному http (без TLS-терминации) -- см. NSAppTransportSecurity
// в app.json, там же исключение именно для этого домена.
export const API_BASE = 'http://sh-129-mg.ru/api';

const TOKEN_KEY = 'mg_schelkovo_token';

export async function getToken(): Promise<string | null> {
  return SecureStore.getItemAsync(TOKEN_KEY);
}

export async function setToken(token: string | null): Promise<void> {
  if (token) {
    await SecureStore.setItemAsync(TOKEN_KEY, token);
  } else {
    await SecureStore.deleteItemAsync(TOKEN_KEY);
  }
}

export class ApiError extends Error {
  status: number;
  constructor(message: string, status: number) {
    super(message);
    this.status = status;
  }
}

type RequestOptions = {
  method?: 'GET' | 'POST';
  body?: Record<string, unknown> | FormData;
  auth?: boolean; // по умолчанию true -- подставляем Bearer-токен, если он есть
  query?: Record<string, string | number | undefined>;
};

function buildUrl(path: string, query?: RequestOptions['query']): string {
  const url = new URL(`${API_BASE}/${path}`);
  if (query) {
    for (const [k, v] of Object.entries(query)) {
      if (v !== undefined && v !== null && v !== '') {
        url.searchParams.set(k, String(v));
      }
    }
  }
  return url.toString();
}

export async function apiRequest<T = any>(path: string, options: RequestOptions = {}): Promise<T> {
  const { method = 'GET', body, auth = true, query } = options;
  const headers: Record<string, string> = {};

  let payload: BodyInit | undefined;
  if (body instanceof FormData) {
    payload = body;
  } else if (body) {
    headers['Content-Type'] = 'application/json';
    payload = JSON.stringify(body);
  }

  if (auth) {
    const token = await getToken();
    if (token) {
      headers['Authorization'] = `Bearer ${token}`;
    }
  }

  let response: Response;
  try {
    response = await fetch(buildUrl(path, query), { method, headers, body: payload });
  } catch (e) {
    throw new ApiError('Не удалось подключиться к серверу. Проверьте интернет-соединение.', 0);
  }

  let data: any = null;
  try {
    data = await response.json();
  } catch {
    throw new ApiError('Сервер вернул некорректный ответ.', response.status);
  }

  if (!response.ok || !data?.ok) {
    throw new ApiError(data?.error || 'Что-то пошло не так.', response.status);
  }
  return data as T;
}
