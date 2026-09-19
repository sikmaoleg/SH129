import { apiRequest } from './client';
import type {
  Badge,
  CabinetOverview,
  EventDetail,
  EventItem,
  HomeData,
  MyEventRow,
  NewsDetail,
  NewsItem,
  PublicSettings,
  RatingRow,
  TeamMember,
  User,
} from './types';

// ---- Авторизация -------------------------------------------------------
export type RegisterPayload = {
  lastName: string;
  firstName: string;
  middleName?: string;
  email: string;
  phone: string;
  birthDate: string;
  vk?: string;
  telegram?: string;
  school?: string;
  password: string;
  password2: string;
  agree: boolean;
};

export const authApi = {
  login: (email: string, password: string) =>
    apiRequest<{ token: string; user: User }>('auth.php', {
      method: 'POST',
      auth: false,
      query: { action: 'login' },
      body: { email, password, device: 'iOS app' },
    }),
  register: (payload: RegisterPayload) =>
    apiRequest<{ message: string }>('auth.php', {
      method: 'POST',
      auth: false,
      query: { action: 'register' },
      body: payload,
    }),
  logout: () => apiRequest<{}>('auth.php', { method: 'POST', query: { action: 'logout' } }),
};

// ---- Профиль -------------------------------------------------------------
export const meApi = {
  get: () => apiRequest<{ user: User }>('me.php'),
  update: (fields: Partial<Pick<User, 'lastName' | 'firstName' | 'middleName' | 'phone' | 'vk' | 'telegram' | 'school' | 'about'>>) =>
    apiRequest<{ user: User }>('me.php', { method: 'POST', body: { action: 'update', ...fields } }),
  uploadAvatar: (file: { uri: string; name: string; type: string }) => {
    const form = new FormData();
    form.append('action', 'avatar_upload');
    // @ts-expect-error -- React Native FormData принимает объект {uri,name,type}
    form.append('avatar', file);
    return apiRequest<{ user: User }>('me.php', { method: 'POST', body: form });
  },
  removeAvatar: () => apiRequest<{ user: User }>('me.php', { method: 'POST', body: { action: 'avatar_remove' } }),
  changePassword: (currentPassword: string, newPassword: string, newPassword2: string) =>
    apiRequest<{ message: string }>('me.php', {
      method: 'POST',
      body: { action: 'password', currentPassword, newPassword, newPassword2 },
    }),
};

// ---- Новости -------------------------------------------------------------
export const newsApi = {
  list: () => apiRequest<{ items: NewsItem[] }>('news.php', { auth: false }),
  detail: (id: number) => apiRequest<{ item: NewsDetail }>('news.php', { auth: false, query: { id } }),
};

// ---- Мероприятия -----------------------------------------------------------
export const eventsApi = {
  list: (filter: 'upcoming' | 'past' = 'upcoming') =>
    apiRequest<{ items: EventItem[] }>('events.php', { auth: false, query: { filter } }),
  detail: (id: number) => apiRequest<{ item: EventDetail }>('events.php', { query: { id } }),
  signup: (id: number) => apiRequest<{ message: string }>('events.php', { method: 'POST', body: { action: 'signup', id } }),
  cancel: (id: number) => apiRequest<{ message: string }>('events.php', { method: 'POST', body: { action: 'cancel', id } }),
};

// ---- Личный кабинет --------------------------------------------------------
export const cabinetApi = {
  overview: () => apiRequest<CabinetOverview>('cabinet.php'),
};

export const myEventsApi = {
  list: () => apiRequest<{ upcoming: MyEventRow[]; past: MyEventRow[] }>('my_events.php'),
};

export const ratingApi = {
  list: (period: 'all' | 'month' = 'all') =>
    apiRequest<{ items: RatingRow[]; levels: any[] }>('rating.php', { query: { period } }),
};

export const badgesApi = {
  list: () => apiRequest<{ items: Badge[] }>('badges.php'),
};

// ---- Публичное --------------------------------------------------------------
export const teamApi = {
  list: () => apiRequest<{ items: TeamMember[] }>('team.php', { auth: false }),
};

export const settingsApi = {
  get: () => apiRequest<{ item: PublicSettings }>('settings.php', { auth: false }),
};

export const homeApi = {
  get: () => apiRequest<HomeData>('home.php', { auth: false }),
};
