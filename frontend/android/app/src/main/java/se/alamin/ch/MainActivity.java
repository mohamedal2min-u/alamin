package se.alamin.ch;

import android.app.NotificationChannel;
import android.app.NotificationManager;
import android.media.AudioAttributes;
import android.net.Uri;
import android.os.Build;
import android.os.Bundle;

import com.getcapacitor.BridgeActivity;

import java.lang.reflect.Field;

public class MainActivity extends BridgeActivity {

    @Override
    public void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        createAdhanChannels();
    }

    /**
     * قناة إشعار لكل صوت أذان (res/raw/adhan_*.mp3) تعمل على صوت المنبّه لا صوت الإشعارات،
     * فيُسمع الأذان بمستوى المنبّه (أعلى غالباً ولا يتأثر بخفض صوت الإشعارات).
     * يجب إنشاؤها قبل أن ينشئها JS: أندرويد لا يغيّر صوت قناة موجودة.
     */
    private void createAdhanChannels() {
        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.O) return;
        NotificationManager nm = getSystemService(NotificationManager.class);
        if (nm == null) return;

        AudioAttributes attrs = new AudioAttributes.Builder()
            .setUsage(AudioAttributes.USAGE_ALARM)
            .setContentType(AudioAttributes.CONTENT_TYPE_SONIFICATION)
            .build();

        for (Field f : R.raw.class.getFields()) {
            String name = f.getName();
            if (!name.startsWith("adhan_") || nm.getNotificationChannel(name) != null) continue;
            NotificationChannel ch = new NotificationChannel(name, "الأذان", NotificationManager.IMPORTANCE_HIGH);
            ch.setDescription("تنبيه الأذان في وقت الصلاة");
            ch.setSound(Uri.parse("android.resource://" + getPackageName() + "/raw/" + name), attrs);
            ch.enableVibration(true);
            ch.setLockscreenVisibility(android.app.Notification.VISIBILITY_PUBLIC);
            nm.createNotificationChannel(ch);
        }
    }
}
