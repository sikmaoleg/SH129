export type User = {
  id: number;
  email: string;
  lastName: string;
  firstName: string;
  middleName: string | null;
  phone: string | null;
  birthDate: string | null;
  vk: string | null;
  telegram: string | null;
  school: string | null;
  about: string | null;
  avatar: string | null;
  role: 'volunteer' | 'admin' | 'dev';
  position: string | null;
  positionLabel: string;
  points: number;
  hours: number;
  memberSince: string | null;
};

export type NewsItem = {
  id: number;
  title: string;
  excerpt: string | null;
  cover: string | null;
  publishedAt: string;
};

export type NewsDetail = NewsItem & {
  body: string;
  images: string[];
};

export type EventItem = {
  id: number;
  title: string;
  startsAt: string;
  location: string | null;
  directionTitle: string | null;
  capacity: number;
  freeSlots: number | null;
};

export type EventDetail = EventItem & {
  description: string | null;
  cover: string | null;
  isPast: boolean;
  myStatus: 'registered' | 'attended' | 'no_show' | 'cancelled' | null;
};

export type MyEventRow = {
  id: number;
  title: string;
  startsAt: string;
  location: string | null;
  directionTitle: string | null;
  status: 'registered' | 'attended' | 'no_show' | 'cancelled';
  pointsAwarded: number;
  comment: string | null;
};

export type Level = { index: number; name: string; min: number; max: number | null };

export type CabinetOverview = {
  level: { current: Level; next: Level | null; progress: number; to_next: number };
  myPlace: number;
  totalVolunteers: number;
  attended: number;
  badgesEarned: number;
  upcoming: { id: number; title: string; startsAt: string; location: string | null }[];
  openEvents: { id: number; title: string; startsAt: string; location: string | null }[];
  history: { points: number; reason: string; createdAt: string }[];
};

export type RatingRow = {
  place: number;
  id: number;
  name: string;
  avatar: string | null;
  points: number;
  levelName: string;
  isMe: boolean;
};

export type Badge = {
  id: number;
  code: string;
  title: string;
  description: string | null;
  icon: string | null;
  earned: boolean;
  awardedAt: string | null;
};

export type TeamMember = {
  id: number;
  name: string;
  position: string;
  positionLabel: string;
  avatar: string | null;
  vk: string | null;
  telegram: string | null;
};

export type PublicSettings = {
  orgName: string;
  orgAddress: string;
  orgEmail: string;
  orgVk: string | null;
  orgTg: string | null;
  orgLeaderName: string | null;
  orgLeaderPhone: string | null;
  registrationOpen: boolean;
};

export type HomeData = {
  heroPhotos: { image: string; caption: string | null }[];
  news: NewsItem[];
  events: EventItem[];
  stats: { volunteers: number; events: number; hours: number };
  honor: { id: number; name: string; avatar: string | null; note: string | null } | null;
};
