'use client';

import { usePushNotifications } from '@/hooks/usePushNotifications';

export function PushNotificationHandler() {
  usePushNotifications();
  return null;
}
