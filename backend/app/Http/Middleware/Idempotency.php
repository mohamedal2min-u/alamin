<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * منع تكرار الإدخال اليومي عند إعادة الإرسال (ضعف الشبكة / صندوق الانتظار في التطبيق).
 *
 * الطلب الذي يحمل Idempotency-Key (UUID) يُسجَّل مفتاحه لكل مزرعة + مستخدم.
 * الرد الناجح (2xx) يُحفظ 48 ساعة، وتكرار نفس المفتاح يعيد نفس الرد بدل إنشاء سجل جديد.
 * نفس المفتاح بمحتوى مختلف → 422. الرد الفاشل لا يُحفظ (يمكن تصحيح الطلب وإعادة إرساله بنفس المفتاح).
 * الطلب بدون الترويسة يمر كما كان.
 */
class Idempotency
{
    public const TTL_HOURS = 48;

    /** المفتاح قيد التنفيذ أكثر من هذا يُعتبر عالقاً (توقف العملية) ويُعاد تنفيذه */
    private const STALE_SECONDS = 120;

    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->header('Idempotency-Key');

        if ($key === null || $key === '') {
            return $next($request);
        }

        if (! Str::isUuid($key)) {
            return response()->json(['message' => 'مفتاح منع التكرار (Idempotency-Key) يجب أن يكون UUID'], 400);
        }

        $farmId = (int) $request->attributes->get('farm_id');
        $user   = $request->user();

        if (! $farmId || ! $user) {
            return $next($request);
        }

        $scope = ['farm_id' => $farmId, 'user_id' => $user->id, 'key' => strtolower($key)];
        $route = Str::limit($request->method() . ' ' . $request->path(), 191, '');
        $hash  = hash('sha256', $route . "\n" . json_encode(self::normalize($request->all())));

        self::maybePrune();

        // مفتاح منتهي الصلاحية أو عالق → يُحذف ويُعامل كأنه جديد
        DB::table('idempotency_keys')->where($scope)
            ->where(function ($q): void {
                $q->where('created_at', '<', now()->subHours(self::TTL_HOURS))
                    ->orWhere(fn ($q2) => $q2->whereNull('response_status')
                        ->where('created_at', '<', now()->subSeconds(self::STALE_SECONDS)));
            })
            ->delete();

        $inserted = DB::table('idempotency_keys')->insertOrIgnore($scope + [
            'route'        => $route,
            'request_hash' => $hash,
            'created_at'   => now(),
        ]);

        if ($inserted === 0) {
            $row = DB::table('idempotency_keys')->where($scope)->first();

            if ($row && ($row->route !== $route || $row->request_hash !== $hash)) {
                return response()->json(['message' => 'مفتاح منع التكرار مستخدم لطلب مختلف'], 422);
            }

            if (! $row || $row->response_status === null) {
                return response()->json(['message' => 'الطلب نفسه قيد التنفيذ، أعد المحاولة بعد قليل', 'code' => 'idempotency_in_progress'], 409);
            }

            return response($row->response_body ?? '', (int) $row->response_status, [
                'Content-Type'        => 'application/json',
                'Idempotent-Replayed' => 'true',
            ]);
        }

        try {
            $response = $next($request);
        } catch (\Throwable $e) {
            DB::table('idempotency_keys')->where($scope)->delete();
            throw $e;
        }

        $status = $response->getStatusCode();

        if ($status >= 200 && $status < 300) {
            DB::table('idempotency_keys')->where($scope)->update([
                'response_status' => $status,
                'response_body'   => $response->getContent(),
            ]);
        } else {
            DB::table('idempotency_keys')->where($scope)->delete();
        }

        return $response;
    }

    /** حذف المفاتيح الأقدم من 48 ساعة */
    public static function prune(): int
    {
        return DB::table('idempotency_keys')
            ->where('created_at', '<', now()->subHours(self::TTL_HOURS))
            ->delete();
    }

    /** تنظيف عَرَضي: مرة كل ~50 طلباً تقريباً، بدون جدولة */
    private static function maybePrune(): void
    {
        if (random_int(1, 50) === 1) {
            self::prune();
        }
    }

    /** ترتيب المفاتيح لتثبيت البصمة، والملفات تُمثَّل ببصمتها */
    private static function normalize(mixed $value): mixed
    {
        if ($value instanceof UploadedFile) {
            return 'file:' . (string) @hash_file('sha256', $value->getRealPath() ?: '');
        }

        if (is_array($value)) {
            if (! array_is_list($value)) {
                ksort($value);
            }

            return array_map([self::class, 'normalize'], $value);
        }

        return $value;
    }
}
