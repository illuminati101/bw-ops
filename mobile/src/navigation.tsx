import React, { useEffect } from 'react';
import { DarkTheme, NavigationContainer, Theme } from '@react-navigation/native';
import { createNativeStackNavigator } from '@react-navigation/native-stack';
import { createBottomTabNavigator } from '@react-navigation/bottom-tabs';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { IS_CREW } from './config';
import { registerPush } from './lib/push';
import { DraftProvider } from './state/draft';
import { useSession } from './state/session';
import { colors, fonts } from './theme';
import { Icon, IconName, Loading } from './components/ui';
import Login from './screens/shared/Login';
import ServerSettings from './screens/shared/ServerSettings';
import Chat from './screens/shared/Chat';
import Sos from './screens/shared/Sos';
import ProfileSetup from './screens/client/ProfileSetup';
import Home from './screens/client/Home';
import Bookings from './screens/client/Bookings';
import Account from './screens/client/Account';
import BookService from './screens/client/BookService';
import BookDetails from './screens/client/BookDetails';
import BookReview from './screens/client/BookReview';
import PlaceSearch from './screens/client/PlaceSearch';
import BookingDetail from './screens/client/BookingDetail';
import EditProfile from './screens/client/EditProfile';
import Payment from './screens/client/Payment';
import Verify from './screens/client/Verify';
import Safety from './screens/client/Safety';
import CrewHome from './screens/crew/CrewHome';
import Job from './screens/crew/Job';
import History from './screens/crew/History';
import Documents from './screens/crew/Documents';
import CrewProfile from './screens/crew/CrewProfile';

const Stack = createNativeStackNavigator();
const Tab = createBottomTabNavigator();

const theme: Theme = {
  ...DarkTheme,
  colors: { ...DarkTheme.colors, primary: colors.gold, background: colors.night, card: colors.night, text: colors.bone, border: colors.line, notification: colors.gold },
};

const stackOptions = {
  headerStyle: { backgroundColor: colors.night },
  headerTintColor: colors.bone,
  headerTitleStyle: { fontFamily: fonts.semibold, fontSize: 16 },
  headerShadowVisible: false,
  headerBackButtonDisplayMode: 'minimal' as const,
  contentStyle: { backgroundColor: colors.night },
};

function tabs(items: { name: string; component: React.ComponentType<any>; title: string; icon: IconName }[]) {
  return function Tabs() {
    const insets = useSafeAreaInsets();
    return (
      <Tab.Navigator
        screenOptions={({ route }) => ({
          headerShown: false,
          tabBarStyle: { backgroundColor: colors.night, borderTopColor: colors.line, height: 68 + insets.bottom, paddingTop: 6, paddingBottom: insets.bottom || 10 },
          tabBarActiveTintColor: colors.gold,
          tabBarInactiveTintColor: colors.ash,
          tabBarLabelStyle: { fontFamily: fonts.medium, fontSize: 11 },
          tabBarIcon: ({ color }) => <Icon name={items.find((i) => i.name === route.name)!.icon} color={color} size={20} />,
        })}
      >
        {items.map((i) => <Tab.Screen key={i.name} name={i.name} component={i.component} options={{ title: i.title }} />)}
      </Tab.Navigator>
    );
  };
}

const ClientTabs = tabs([
  { name: 'Home', component: Home, title: 'Book', icon: 'navigation' },
  { name: 'Bookings', component: Bookings, title: 'Bookings', icon: 'calendar' },
  { name: 'Account', component: Account, title: 'Account', icon: 'user' },
]);

const CrewTabs = tabs([
  { name: 'Today', component: CrewHome, title: 'Today', icon: 'briefcase' },
  { name: 'History', component: History, title: 'Earnings', icon: 'dollar-sign' },
  { name: 'Documents', component: Documents, title: 'Documents', icon: 'file-text' },
  { name: 'Me', component: CrewProfile, title: 'Me', icon: 'user' },
]);

export default function RootNavigation() {
  const { ready, token, needsProfile } = useSession();

  useEffect(() => {
    if (token) registerPush();
  }, [token]);

  if (!ready) return <Loading />;

  return (
    <NavigationContainer theme={theme}>
      <DraftProvider>
        <Stack.Navigator screenOptions={stackOptions}>
          {!token ? (
            <>
              <Stack.Screen name="Login" component={Login} options={{ headerShown: false }} />
              <Stack.Screen name="ServerSettings" component={ServerSettings} options={{ title: 'Server' }} />
            </>
          ) : IS_CREW ? (
            <>
              <Stack.Screen name="Tabs" component={CrewTabs} options={{ headerShown: false }} />
              <Stack.Screen name="Job" component={Job} options={{ title: 'Job' }} />
              <Stack.Screen name="Chat" component={Chat} />
              <Stack.Screen name="Sos" component={Sos} options={{ title: 'SOS', presentation: 'modal' }} />
              <Stack.Screen name="ServerSettings" component={ServerSettings} options={{ title: 'Server' }} />
            </>
          ) : needsProfile ? (
            <Stack.Screen name="ProfileSetup" component={ProfileSetup} options={{ headerShown: false }} />
          ) : (
            <>
              <Stack.Screen name="Tabs" component={ClientTabs} options={{ headerShown: false }} />
              <Stack.Screen name="BookService" component={BookService} options={{ title: '' }} />
              <Stack.Screen name="BookDetails" component={BookDetails} options={{ title: '' }} />
              <Stack.Screen name="BookReview" component={BookReview} options={{ title: '' }} />
              <Stack.Screen name="PlaceSearch" component={PlaceSearch} options={{ presentation: 'modal', title: 'Pickup' }} />
              <Stack.Screen name="BookingDetail" component={BookingDetail} options={{ title: 'Booking' }} />
              <Stack.Screen name="Chat" component={Chat} />
              <Stack.Screen name="Sos" component={Sos} options={{ title: 'SOS', presentation: 'modal' }} />
              <Stack.Screen name="EditProfile" component={EditProfile} options={{ title: 'Your details' }} />
              <Stack.Screen name="Payment" component={Payment} options={{ title: '' }} />
              <Stack.Screen name="Verify" component={Verify} options={{ title: '' }} />
              <Stack.Screen name="Safety" component={Safety} options={{ title: '' }} />
              <Stack.Screen name="ServerSettings" component={ServerSettings} options={{ title: 'Server' }} />
            </>
          )}
        </Stack.Navigator>
      </DraftProvider>
    </NavigationContainer>
  );
}
