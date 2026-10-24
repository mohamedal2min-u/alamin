import { useEffect } from 'react';
import { Capacitor } from '@capacitor/core';
import { PushNotifications, type Token } from '@capacitor/push-notifications';
import { toast } from 'sonner';
import { apiClient } from '@/lib/api/client';
import { useAuthStore } from '@/stores/auth.store';
import { EDIT_KINDS, playEditTone, type EditKind } from '@/lib/prayer/audio';

export const usePushNotifications = () => {
  // يُعاد التسجيل بعد تسجيل الدخول ليُربط رمز الجهاز بالحساب
  const isAuthenticated = useAuthStore((s) => s.isAuthenticated);

  useEffect(() => {
    // Only run on native platforms (Android/iOS)
    if (Capacitor.getPlatform() === 'web') {
      return;
    }

    const registerPush = async () => {
      // قنوات بنغمات مميّزة: المحاسب عدّل، المدير عدّل، وسجل يومي بنغمة بسيطة (ملفات في res/raw)
      try {
        await PushNotifications.createChannel({ id: 'accountant_edit', name: 'تعديلات المحاسب', importance: 5, sound: 'accountant_edit.wav', vibration: true });
        await PushNotifications.createChannel({ id: 'manager_edit', name: 'تعديلات المدير', importance: 5, sound: 'manager_edit.wav', vibration: true });
        await PushNotifications.createChannel({ id: 'daily_entry', name: 'السجلات اليومية (نفوق، علف، دواء...)', importance: 4, sound: 'daily_entry.wav', vibration: true });
      } catch { /* القنوات تُنشأ مرة واحدة */ }

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

      await PushNotifications.addListener('registrationError', (err: { error: string }) => {
        console.error('Push registration error: ', err.error);
      });

      await PushNotifications.addListener('pushNotificationReceived', (notification) => {
        // والتطبيق مفتوح لا يعرض النظام الإشعار، فنُصدر النغمة هنا
        const kind = notification.data?.type as EditKind | undefined;
        if (kind && EDIT_KINDS.includes(kind)) {
          playEditTone(kind);
          toast(notification.title ?? 'إشعار', { description: notification.body });
        }
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

    if (!isAuthenticated) return;
    registerPush();
    addListeners();

    // Cleanup listeners on unmount
    return () => {
      PushNotifications.removeAllListeners();
    };
  }, [isAuthenticated]);
};
