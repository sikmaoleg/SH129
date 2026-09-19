export type HomeStackParamList = {
  HomeScreen: undefined;
  NewsDetail: { id: number };
  EventDetail: { id: number };
};

export type NewsStackParamList = {
  NewsList: undefined;
  NewsDetail: { id: number };
};

export type EventsStackParamList = {
  EventsList: undefined;
  EventDetail: { id: number };
};

export type CabinetStackParamList = {
  Login: undefined;
  Register: undefined;
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
