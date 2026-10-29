<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Contact;
use App\Models\ExpenseCategory;
use App\Models\Flock;
use App\Models\Item;
use App\Models\Partner;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

/**
 * تفاصيل التعديل التي تُعرض عند الضغط على الإشعار: صفوف «الحقل: قبل ← بعد».
 * تُبنى من الطلب نفسه (الحقول المرسلة)، ومن لقطة السجل قبل التعديل، ومن سجل النشاطات للإدخالات اليومية.
 */
class EditDetails
{
    private const MAX_ROWS = 40;

    /** حقول لا تُعرض أبداً */
    private const HIDDEN = [
        'password', 'current_password', 'password_confirmation', 'fcm_token', 'token',
        'idempotency_key', 'client_id', 'form_ms', 'page', 'avatar', 'file', 'attachment',
        'farm_id', 'id', 'created_at', 'updated_at', 'deleted_at', 'created_by', 'updated_by',
    ];

    private const LABELS = [
        'quantity' => 'الكمية', 'unit_price' => 'سعر الوحدة', 'unit_price_per_kg' => 'سعر الكيلو',
        'total_amount' => 'المجموع', 'amount' => 'المبلغ', 'paid_amount' => 'المدفوع',
        'received_amount' => 'المقبوض', 'discount_amount' => 'الخصم', 'default_cost' => 'التكلفة',
        'chick_unit_price' => 'سعر الصوص', 'chick_paid_amount' => 'المدفوع للصوص', 'salary' => 'الراتب',
        'notes' => 'ملاحظات', 'note' => 'ملاحظة', 'note_text' => 'النص', 'note_type' => 'النوع',
        'description' => 'الوصف', 'reason' => 'السبب', 'edit_reason' => 'سبب التعديل', 'title' => 'العنوان',
        'name' => 'الاسم', 'phone' => 'الهاتف', 'whatsapp' => 'واتساب', 'location' => 'الموقع',
        'entry_date' => 'التاريخ', 'log_date' => 'التاريخ', 'sale_date' => 'تاريخ البيع',
        'transaction_date' => 'التاريخ', 'paid_at' => 'تاريخ الدفع', 'start_date' => 'تاريخ البدء',
        'started_at' => 'تاريخ البدء', 'close_date' => 'تاريخ الإغلاق', 'time_of_day' => 'الفترة',
        'item_id' => 'الصنف', 'item_name' => 'الصنف', 'flock_id' => 'الفوج', 'contact_id' => 'الجهة',
        'partner_id' => 'الشريك', 'warehouse_id' => 'المستودع', 'expense_category_id' => 'الفئة',
        'unit_label' => 'الوحدة', 'input_unit' => 'الوحدة', 'content_unit' => 'وحدة المحتوى',
        'unit_value' => 'قيمة الوحدة', 'temperature' => 'الحرارة', 'status' => 'الحالة',
        'payment_status' => 'حالة الدفع', 'method' => 'طريقة الدفع', 'share_percent' => 'النسبة',
        'initial_count' => 'عدد الصوص', 'birds_count' => 'عدد الطيور', 'total_weight_kg' => 'الوزن (كغ)',
        'gross_weight_kg' => 'الوزن القائم (كغ)', 'vehicle_weight_before_kg' => 'وزن السيارة فارغة (كغ)',
        'weight_deduction_kg' => 'خصم الوزن (كغ)', 'crates_count' => 'عدد الأقفاص', 'crate_weight_kg' => 'وزن القفص (كغ)',
        'buyer_name' => 'المشتري', 'supplier_name' => 'المورد', 'invoice_no' => 'رقم الفاتورة',
        'reference_no' => 'رقم المرجع', 'expense_type' => 'نوع المصروف', 'transaction_type' => 'نوع الحركة',
        'minimum_stock' => 'الحد الأدنى', 'barcode' => 'الباركود', 'is_correction' => 'قيد تصحيح',
        'is_active' => 'فعّال', 'withdrawal_days' => 'أيام السحب', 'age_day' => 'العمر (يوم)',
        'original_quantity' => 'الكمية الأصلية', 'stock_action' => 'نوع التصحيح',
    ];

    /** معرّفات تُستبدل بأسمائها للقراءة */
    private const NAMED = [
        'item_id' => Item::class, 'flock_id' => Flock::class, 'contact_id' => Contact::class,
        'partner_id' => Partner::class, 'warehouse_id' => Warehouse::class, 'expense_category_id' => ExpenseCategory::class,
    ];

    /** معاملات المسار التي تشير لسجل يُعدَّل (حين لا يربطها Laravel تلقائياً) */
    private const ROUTE_MODELS = [
        'flock' => Flock::class, 'item' => Item::class, 'partner' => Partner::class, 'contact' => Contact::class,
    ];

    /** لقطة سجل المسار (قبل التنفيذ) لمقارنة القيم القديمة بالجديدة */
    public function snapshot(Request $request): array
    {
        $out = [];
        foreach ($request->route()?->parameters() ?? [] as $name => $model) {
            if (! $model instanceof Model && isset(self::ROUTE_MODELS[$name]) && is_numeric($model)) {
                $model = (self::ROUTE_MODELS[$name])::find((int) $model);
            }
            if ($model instanceof Model) {
                $out = $model->getAttributes() + $out;
            }
        }

        return $out;
    }

    /**
     * @return array{rows: list<array{label: string, from?: string, to: string}>, flock: ?string, link: ?string}
     */
    public function build(Request $request, array $before, ?string $entryKind, int $actorId): array
    {
        $flockId = null;
        $rows = [];

        if ($entryKind === EditNotifier::DAILY_ENTRY) {
            [$rows, $flockId] = $this->fromActivityLog($request, $actorId);
        }

        if ($rows === []) {
            $rows = $this->fromInput($request, $before);
        }

        $flockId ??= $this->routeFlockId($request) ?? $request->input('flock_id') ?? ($before['flock_id'] ?? null);
        $flock = is_numeric($flockId) ? Flock::find((int) $flockId) : null;

        return [
            'rows' => array_slice($rows, 0, self::MAX_ROWS),
            'flock' => $flock?->name,
            'link' => $flock ? "/flocks/{$flock->id}" : null,
        ];
    }

    private function routeFlockId(Request $request): ?int
    {
        $flock = $request->route('flock');
        if ($flock instanceof Flock) {
            return $flock->id;
        }

        return is_numeric($flock) ? (int) $flock : null;
    }

    /** الإدخالات اليومية: آخر سجل نشاط للسجل المعدَّل (قبل ← بعد) */
    private function fromActivityLog(Request $request, int $actorId): array
    {
        $log = ActivityLog::where('subject_type', $request->route('type'))
            ->where('subject_id', (int) $request->route('id'))
            ->where('user_id', $actorId)
            ->latest('id')
            ->first();
        if (! $log) {
            return [[], null];
        }

        $rows = [];
        if ($log->action === ActivityLogger::UPDATED && $log->before && $log->after) {
            foreach ($log->after as $k => $to) {
                $from = $log->before[$k] ?? null;
                if ($k === 'item_id' || $this->show($from) === $this->show($to)) {
                    continue;
                }
                $rows[] = ['label' => $this->label($k), 'from' => $this->show($from), 'to' => $this->show($to)];
            }
        } else {
            $snap = ($log->action === ActivityLogger::VOIDED ? $log->before : $log->after) ?? [];
            foreach ($snap as $k => $v) {
                if ($k !== 'item_id' && $v !== null && $v !== '') {
                    $rows[] = ['label' => $this->label($k), 'to' => $this->show($v)];
                }
            }
        }
        if ($log->reason) {
            $rows[] = ['label' => 'السبب', 'to' => $log->reason];
        }

        return [$rows, $log->flock_id];
    }

    /** الحقول المرسلة في الطلب، مع القيمة السابقة إن وُجدت */
    private function fromInput(Request $request, array $before): array
    {
        $rows = [];
        foreach ($request->except(self::HIDDEN) as $key => $value) {
            if ($value instanceof UploadedFile || in_array($key, self::HIDDEN, true)) {
                continue;
            }
            if (is_array($value)) {
                foreach (array_values($value) as $i => $line) {
                    if (! is_array($line)) {
                        continue;
                    }
                    foreach ($line as $k => $v) {
                        if (! is_array($v) && ! in_array($k, self::HIDDEN, true) && $v !== null && $v !== '') {
                            $rows[] = ['label' => 'بند '.($i + 1).' · '.$this->label($k), 'to' => $this->value($k, $v)];
                        }
                    }
                }

                continue;
            }
            if ($value === null || $value === '') {
                continue;
            }

            $row = ['label' => $this->label($key), 'to' => $this->value($key, $value)];
            if (array_key_exists($key, $before)) {
                $from = $this->value($key, $before[$key]);
                if ($this->same($before[$key], $value)) {
                    continue; // لم يتغيّر
                }
                $row = ['label' => $row['label'], 'from' => $from, 'to' => $row['to']];
            }
            $rows[] = $row;
        }

        return $rows;
    }

    private function same(mixed $a, mixed $b): bool
    {
        if (is_numeric($a) && is_numeric($b)) {
            return (float) $a === (float) $b;
        }
        if (is_string($a) && is_string($b) && preg_match('/^\d{4}-\d{2}-\d{2}/', $a) && preg_match('/^\d{4}-\d{2}-\d{2}/', $b)) {
            return substr($a, 0, 10) === substr($b, 0, 10);
        }

        return $this->show($a) === $this->show($b);
    }

    private function label(string $key): string
    {
        return self::LABELS[$key] ?? str_replace('_', ' ', $key);
    }

    private function value(string $key, mixed $v): string
    {
        if (isset(self::NAMED[$key]) && is_numeric($v)) {
            try {
                $name = (self::NAMED[$key])::query()->whereKey((int) $v)->value('name');
                if ($name) {
                    return (string) $name;
                }
            } catch (\Throwable) {
                // نعرض الرقم إن تعذّر الاسم
            }
        }
        if (is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}:\d{2}/', $v)) {
            return substr($v, 0, 10);
        }

        return $this->show($v);
    }

    private function show(mixed $v): string
    {
        if ($v === null || $v === '') {
            return '—';
        }
        if (is_bool($v)) {
            return $v ? 'نعم' : 'لا';
        }
        if (is_float($v) || (is_string($v) && is_numeric($v) && str_contains($v, '.'))) {
            return rtrim(rtrim(number_format((float) $v, 3, '.', ''), '0'), '.');
        }

        return is_scalar($v) ? (string) $v : json_encode($v, JSON_UNESCAPED_UNICODE);
    }
}
