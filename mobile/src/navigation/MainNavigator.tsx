import React from 'react';
import { Text } from 'react-native';
import { createBottomTabNavigator } from '@react-navigation/bottom-tabs';
import { createNativeStackNavigator } from '@react-navigation/native-stack';
import type { CabinetStackParamList, EventsStackParamList, HomeStackParamList, MainTabParamList, NewsStackParamList } from './types';
import HomeScreen from '../screens/HomeScreen';
import NewsListScreen from '../screens/NewsListScreen';
import NewsDetailScreen from '../screens/NewsDetailScreen';
import EventsListScreen from '../screens/EventsListScreen';
import EventDetailScreen from '../screens/EventDetailScreen';
import CabinetHomeScreen from '../screens/CabinetHomeScreen';
import MyEventsScreen from '../screens/MyEventsScreen';
import RatingScreen from '../screens/RatingScreen';
import BadgesScreen from '../screens/BadgesScreen';
import ProfileScreen from '../screens/ProfileScreen';
import LoginScreen from '../screens/LoginScreen';
import RegisterScreen from '../screens/RegisterScreen';
import { useAuth } from '../context/AuthContext';
import { LoadingView } from '../components/ui';
import { colors } from '../theme/colors';

const HomeStack = createNativeStackNavigator<HomeStackParamList>();
function HomeStackNavigator() {
  return (
    <HomeStack.Navigator screenOptions={screenOptions}>
      <HomeStack.Screen name="HomeScreen" component={HomeScreen} options={{ title: 'Молодая Гвардия' }} />
      <HomeStack.Screen name="NewsDetail" component={NewsDetailScreen} options={{ title: 'Новость' }} />
      <HomeStack.Screen name="EventDetail" component={EventDetailScreen} options={{ title: 'Мероприятие' }} />
    </HomeStack.Navigator>
  );
}

const NewsStack = createNativeStackNavigator<NewsStackParamList>();
function NewsStackNavigator() {
  return (
    <NewsStack.Navigator screenOptions={screenOptions}>
      <NewsStack.Screen name="NewsList" component={NewsListScreen} options={{ title: 'Новости' }} />
      <NewsStack.Screen name="NewsDetail" component={NewsDetailScreen} options={{ title: 'Новость' }} />
    </NewsStack.Navigator>
  );
}

const EventsStack = createNativeStackNavigator<EventsStackParamList>();
function EventsStackNavigator() {
  return (
    <EventsStack.Navigator screenOptions={screenOptions}>
      <EventsStack.Screen name="EventsList" component={EventsListScreen} options={{ title: 'Мероприятия' }} />
      <EventsStack.Screen name="EventDetail" component={EventDetailScreen} options={{ title: 'Мероприятие' }} />
    </EventsStack.Navigator>
  );
}

const CabinetStack = createNativeStackNavigator<CabinetStackParamList>();
function CabinetStackNavigator() {
  const { user, loading } = useAuth();

  if (loading) {
    return <LoadingView />; // проверяем сохранённый токен
  }

  if (!user) {
    return (
      <CabinetStack.Navigator screenOptions={screenOptions}>
        <CabinetStack.Screen name="Login" component={LoginScreen} options={{ title: 'Вход', headerShown: false }} />
        <CabinetStack.Screen name="Register" component={RegisterScreen} options={{ title: 'Анкета волонтёра' }} />
      </CabinetStack.Navigator>
    );
  }

  return (
    <CabinetStack.Navigator screenOptions={screenOptions}>
      <CabinetStack.Screen name="CabinetHome" component={CabinetHomeScreen} options={{ title: 'Личный кабинет' }} />
      <CabinetStack.Screen name="MyEvents" component={MyEventsScreen} options={{ title: 'Мои мероприятия' }} />
      <CabinetStack.Screen name="Rating" component={RatingScreen} options={{ title: 'Рейтинг' }} />
      <CabinetStack.Screen name="Badges" component={BadgesScreen} options={{ title: 'Достижения' }} />
      <CabinetStack.Screen name="Profile" component={ProfileScreen} options={{ title: 'Мои данные' }} />
      <CabinetStack.Screen name="EventDetail" component={EventDetailScreen} options={{ title: 'Мероприятие' }} />
    </CabinetStack.Navigator>
  );
}

const screenOptions = {
  headerStyle: { backgroundColor: colors.blue900 },
  headerTintColor: '#fff',
  headerTitleStyle: { fontWeight: '800' as const },
};

const Tab = createBottomTabNavigator<MainTabParamList>();

function TabIcon({ emoji, focused }: { emoji: string; focused: boolean }) {
  return <Text style={{ fontSize: 20, opacity: focused ? 1 : 0.5 }}>{emoji}</Text>;
}

export default function MainNavigator() {
  return (
    <Tab.Navigator
      screenOptions={{
        headerShown: false,
        tabBarActiveTintColor: colors.accent,
        tabBarInactiveTintColor: colors.muted,
      }}
    >
      <Tab.Screen
        name="HomeTab"
        component={HomeStackNavigator}
        options={{ title: 'Главная', tabBarIcon: ({ focused }) => <TabIcon emoji="🏠" focused={focused} /> }}
      />
      <Tab.Screen
        name="NewsTab"
        component={NewsStackNavigator}
        options={{ title: 'Новости', tabBarIcon: ({ focused }) => <TabIcon emoji="📰" focused={focused} /> }}
      />
      <Tab.Screen
        name="EventsTab"
        component={EventsStackNavigator}
        options={{ title: 'Афиша', tabBarIcon: ({ focused }) => <TabIcon emoji="📅" focused={focused} /> }}
      />
      <Tab.Screen
        name="CabinetTab"
        component={CabinetStackNavigator}
        options={{ title: 'Кабинет', tabBarIcon: ({ focused }) => <TabIcon emoji="👤" focused={focused} /> }}
      />
    </Tab.Navigator>
  );
}
