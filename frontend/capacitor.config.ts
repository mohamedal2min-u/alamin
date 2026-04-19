import type { CapacitorConfig } from '@capacitor/cli';

const config: CapacitorConfig = {
  appId: 'se.alamin.ch',
  appName: 'YMD',
  webDir: 'out',
  server: {
    url: 'https://ch.alamin.se/',
    cleartext: true
  }
};

export default config;
