export type HomeStackParamList = {
  HomeScreen: undefined;
  NewsDetail: { id: number };
  EventDetail: { id: number };
};

export type NewsStackParamList = {
  NewsList: undefined;
  NewsDetail: { id: number };
};

export type AuthGateParamList = {
  Login: undefined;
  Register: undefined;
};

export type EventsStackParamList = AuthGateParamList & {
  EventsList: undefined;
  EventDetail: { id: number };
};

export type CabinetStackParamList = AuthGateParamList & {
  CabinetHome: undefined;
  MyEvents: undefined;
  Rating: undefined;
  Badges: undefined;
  Profile: undefined;
  EventDetail: { id: number };
};

export type MainTabParamList = {
  HomeTab: undefined;
  NewsTab: undefined;
  EventsTab: undefined;
  CabinetTab: undefined;
};
