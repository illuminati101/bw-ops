import { registerRootComponent } from 'expo';
// Background location must be registered before the app renders (Crew app only uses it).
import './src/lib/crewLocation';
import App from './App';

registerRootComponent(App);
