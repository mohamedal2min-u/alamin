import { useEffect } from 'react';
import { useRouter } from 'next/navigation';
import { Capacitor, type PluginListenerHandle } from '@capacitor/core';
import { PushNotifications, type Token } from '@capacitor/push-notifications';
import { apiClient } from '@/lib/api/client';
import { useAuthStore } from '@/stores/auth.store';
import { playEditTone } from '@/lib/prayer/audio';

/** وجهة الضغط على الإشعار: تفاصيل التعديل، وإلا صفحة الفوج */
const targetOf = (data: Record<string, unknown> | undefined): string | null => {
  if (data?.notification_id) return `/notifications/${data.notification_id}`;
  if (data?.flock_id) return `/flocks/${data.flock_id}`;
  return null;
};

export const usePushNotifications = () => {
  // يُعاد التسجيل بعد تسجيل الدخول ليُربط رمز الجهاز بالحساب
  const isAuthenticated = useAuthStore((s) => s.isAuthenticated);
  const router = useRouter();

  useEffect(() => {
    // Only run on native platforms (Android/iOS)
    if (Capacitor.getPlatform() === 'web') {
      return;
    }

    const registerPush = async () => {
      // قنوات بنغمات مميّزة: المحاسب عدّل ← نغمة، المدير عدّل ← نغمة أخرى (ملفات في res/raw)
      try {
        // importance 5 + visibility 1: يظهر فوراً حتى والشاشة مقفلة
        await PushNotifications.createChannel({ id: 'accountant_edit', name: 'تعديلات المحاسب', importance: 5, visibility: 1, sound: 'accountant_edit.wav', vibration: true });
        await PushNotifications.createChannel({ id: 'manager_edit', name: 'تعديلات المدير', importance: 5, visibility: 1, sound: 'manager_edit.wav', vibration: true });
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

      await PushNotifications.addListener('registrationError', (err: any) => {
        console.error('Push registration error: ', err.error);
      });

      await PushNotifications.addListener('pushNotificationReceived', (notification) => {
        // والتطبيق مفتوح لا يعرض النظام الإشعار: نضعه في الشريط بنغمة قناته ليبقى حتى يُضغط عليه،
        // وإن تعذّر نُصدر النغمة فقط
        const kind = notification.data?.type;
        void showInTray(notification.title, notification.body, notification.data).then((shown) => {
          if (!shown && (kind === 'accountant_edit' || kind === 'manager_edit')) playEditTone(kind);
        });
      });

      await PushNotifications.addListener('pushNotificationActionPerformed', (action) => {
        // الضغط على الإشعار يفتح تفاصيل التعديل
        const target = targetOf(action.notification.data);
        if (target) router.push(target);
      });

      try {
        const { LocalNotifications } = await import('@capacitor/local-notifications');
        localHandle = await LocalNotifications.addListener('localNotificationActionPerformed', (action) => {
          const target = targetOf(action.notification.extra);
          if (target && action.notification.extra?.notification_id) router.push(target);
        });
      } catch { /* نسخة APK بلا الإشعارات المحلية */ }
    };

    let localHandle: PluginListenerHandle | undefined;

    if (!isAuthenticated) return;
    registerPush();
    addListeners();

    // Cleanup listeners on unmount
    return () => {
      PushNotifications.removeAllListeners();
      void localHandle?.remove();
    };
  }, [isAuthenticated, router]);
};

/** إشعار محلي بنفس قناة النغمة، يبقى في الشريط حتى الضغط عليه */
async function showInTray(title: string | undefined, body: string | undefined, data: Record<string, unknown> | undefined): Promise<boolean> {
  const id = Number(data?.notification_id);
  if (!title || !id) return false;
  try {
    const { LocalNotifications } = await import('@capacitor/local-notifications');
    const kind = String(data?.type ?? '');
    await LocalNotifications.schedule({
      notifications: [{
        id: 100000 + (id % 100000), // بعيد عن معرّفات الأذان واللقاحات
        title,
        body: body ?? '',
        largeBody: body ?? '',
        channelId: kind === 'accountant_edit' || kind === 'manager_edit' ? kind : undefined,
        autoCancel: true,
        extra: { notification_id: id },
      }],
    });
    return true;
  } catch {
    return false; // لا بأس: يظهر التنبيه داخل التطبيق
  }
}
