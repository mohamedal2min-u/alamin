import { useEffect } from 'react';
import { Capacitor } from '@capacitor/core';
import { PushNotifications, type Token } from '@capacitor/push-notifications';
import { apiClient } from '@/lib/api/client';

export const usePushNotifications = () => {
  useEffect(() => {
    // Only run on native platforms (Android/iOS)
    if (Capacitor.getPlatform() === 'web') {
      return;
    }

    const registerPush = async () => {
      let permStatus = await PushNotifications.checkPermissions();

      if (permStatus.receive === 'prompt') {
        permStatus = await PushNotifications.requestPermissions();
      }

      if (permStatus.receive !== 'granted') {
        console.warn('User denied push notification permissions');
        return;
      }

      await PushNotifications.register();
    };

    const addListeners = async () => {
      await PushNotifications.addListener('registration', async (token: Token) => {
        console.log('Push registration success, token: ' + token.value);
        try {
          // Send token to backend
          await apiClient.post('/auth/update-fcm-token', {
            fcm_token: token.value,
          });
        } catch (error) {
          console.error('Failed to send FCM token to backend', error);
        }
      });

      await PushNotifications.addListener('registrationError', (err: any) => {
        console.error('Push registration error: ', err.error);
      });

      await PushNotifications.addListener('pushNotificationReceived', (notification) => {
        console.log('Push notification received: ', notification);
        // You can add logic here to show a toast or update local state
      });

      await PushNotifications.addListener('pushNotificationActionPerformed', (action) => {
        console.log('Push notification action performed', action.notification);
        // Handle notification click (e.g., navigate to a specific page)
        const data = action.notification.data;
        if (data?.flock_id) {
          window.location.href = `/flocks/${data.flock_id}`;
        }
      });
    };

    registerPush();
    addListeners();

    // Cleanup listeners on unmount
    return () => {
      PushNotifications.removeAllListeners();
    };
  }, []);
};
