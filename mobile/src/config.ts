import Constants from 'expo-constants';

const extra = (Constants.expoConfig?.extra ?? {}) as { variant?: string; defaultServer?: string; eas?: { projectId?: string } };

export const VARIANT: 'client' | 'crew' = extra.variant === 'crew' ? 'crew' : 'client';
export const IS_CREW = VARIANT === 'crew';
export const DEFAULT_SERVER = (extra.defaultServer || 'https://blackandwhiteops.com').replace(/\/+$/, '');
export const EAS_PROJECT_ID = extra.eas?.projectId;
export const APP_VERSION = Constants.expoConfig?.version ?? '1.0.0';
export const APP_SCHEME = IS_CREW ? 'bwopscrew' : 'bwops';
