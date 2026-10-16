<?php

namespace App\Console\Commands;

use App\Models\Expense;
use App\Models\Flock;
use App\Models\FlockMedicine;
use App\Models\InventoryTransaction;
use App\Models\Item;
use App\Models\WarehouseItem;
use App\Services\InventoryRepricingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * مسح أدوية فوج مفتوح لإعادة تسجيلها: شحنات شراء الأدوية وديونها، وسجلات إعطاء الدواء واستهلاكها.
 * المخزون يرجع كما كان قبل هذه السجلات، ومتوسط التكلفة يُعاد حسابه.
 * بدون --force يعرض ما سيُحذف فقط. مع --force يحفظ نسخة JSON من كل السجلات ثم يحذف في معاملة واحدة.
 */
class PurgeFlockMedicines extends Command
{
    protected $signature = 'flock:purge-medicines {flock? : رقم الفوج (بدونه تُعرض الأفواج المفتوحة)} {--force : تنفيذ الحذف فعلاً (بدونه عرض فقط)} {--zero-stock : بعد المسح يُصفَّر مخزون كل أصناف الأدوية بدل إرجاعه}';

    protected $description = 'مسح شحنات الأدوية وديونها وسجلات إعطاء الدواء لفوج مفتوح، مع إرجاع المخزون (عرض فقط بدون --force)';

    public function handle(): int
    {
        if ($this->argument('flock') === null) {
            $this->info('الأفواج المفتوحة:');
            foreach (Flock::where('status', 'active')->with('farm')->get() as $f) {
                $this->line(sprintf('  #%d — %s (مزرعة: %s)', $f->id, $f->name, $f->farm?->name));
            }

            return self::SUCCESS;
        }

        $flock = Flock::find((int) $this->argument('flock'));
        if (! $flock) {
            $this->error('الفوج غير موجود.');

            return self::FAILURE;
        }
        if ($flock->status !== 'active') {
            $this->error("الفوج «{$flock->name}» ليس مفتوحاً ({$flock->status}) — المسح مسموح للفوج المفتوح فقط.");

            return self::FAILURE;
        }

        $medItemIds = Item::where('farm_id', $flock->farm_id)
            ->whereHas('itemType', fn ($q) => $q->where('code', 'medicine'))
            ->pluck('id');

        $logs = FlockMedicine::where('flock_id', $flock->id)->with('item')->get();

        $purchases = InventoryTransaction::where('flock_id', $flock->id)
            ->whereIn('item_id', $medItemIds)
            ->where('direction', 'in')->where('transaction_type', 'purchase')
            ->with('item')->get();

        // استهلاك الأدوية: كل حركات الإخراج للفوج على أصناف الأدوية + ما تشير إليه سجلات الإعطاء
        $consumptions = InventoryTransaction::where('flock_id', $flock->id)
            ->where('direction', 'out')->where('transaction_type', 'consumption')
            ->where(fn ($q) => $q->whereIn('item_id', $medItemIds)
                ->orWhereIn('id', $logs->pluck('inventory_transaction_id')->filter()))
            ->with('item')->get();

        // ديون الشراء: المرتبطة بشحنات الفوج، وكذلك ديون أدوية مسجّلة على الفوج وشحنتها غير مسجّلة عليه
        $expenses = Expense::where(fn ($q) => $q
            ->whereIn('linked_inventory_transaction_id', $purchases->pluck('id'))
            ->orWhere(fn ($q2) => $q2->where('flock_id', $flock->id)
                ->whereHas('linkedInventoryTransaction', fn ($t) => $t->whereIn('item_id', $medItemIds)
                    ->where('direction', 'in')->where('transaction_type', 'purchase'))))
            ->get();
        $purchases = $purchases->merge(
            InventoryTransaction::whereIn('id', $expenses->pluck('linked_inventory_transaction_id')->filter())
                ->whereNotIn('id', $purchases->pluck('id'))
                ->with('item')->get()
        );

        // للعلم فقط (لا تُحذف): حركات أخرى على أدوية الفوج، ومصاريف أدوية يدوية غير مرتبطة بشحنة
        $otherTxns = InventoryTransaction::where('flock_id', $flock->id)->whereIn('item_id', $medItemIds)
            ->whereNotIn('id', $purchases->pluck('id')->merge($consumptions->pluck('id')))->count();
        $manualMedExpenses = Expense::where('flock_id', $flock->id)->whereNull('linked_inventory_transaction_id')
            ->whereHas('expenseCategory', fn ($q) => $q->where('code', 'medicine'))->count();

        $this->info("الفوج: {$flock->name} (#{$flock->id})");

        $this->line("\nشحنات شراء الأدوية ({$purchases->count()}):");
        foreach ($purchases as $t) {
            $this->line(sprintf('  - %s | %s | كمية %s | المبلغ %s', $t->transaction_date?->toDateString() ?? $t->transaction_date, $t->item?->name, $t->original_quantity, $t->total_amount ?? 'بلا سعر'));
        }

        $paid = (float) $expenses->sum('paid_amount');
        $this->line("\nديون الشراء المرتبطة ({$expenses->count()}): الإجمالي ".(float) $expenses->sum('total_amount')." — المدفوع منها {$paid}");

        $this->line("\nسجلات إعطاء الدواء ({$logs->count()}):");
        foreach ($logs as $l) {
            $this->line(sprintf('  - %s | %s | كمية %s %s', $l->entry_date?->toDateString() ?? $l->entry_date, $l->item?->name, $l->quantity, $l->unit_label));
        }
        $this->line("حركات استهلاك المخزون: {$consumptions->count()}");

        // أثر المخزون لكل (مستودع، صنف): + المستهلك − المشترى
        $delta = [];
        foreach ($consumptions as $t) {
            $k = "{$t->warehouse_id}:{$t->item_id}";
            $delta[$k] = ($delta[$k] ?? 0) + (float) $t->computed_quantity;
        }
        foreach ($purchases as $t) {
            $k = "{$t->warehouse_id}:{$t->item_id}";
            $delta[$k] = ($delta[$k] ?? 0) - (float) $t->computed_quantity;
        }

        $this->line("\nأثر المخزون:");
        $negative = [];
        $stock = [];
        foreach ($delta as $k => $d) {
            [$warehouseId, $itemId] = array_map('intval', explode(':', $k));
            $wi = WarehouseItem::where('farm_id', $flock->farm_id)->where('warehouse_id', $warehouseId)->where('item_id', $itemId)->first();
            $before = (float) ($wi?->current_quantity ?? 0);
            $after = round($before + $d, 6);
            $name = Item::find($itemId)?->name;
            $stock[$k] = ['warehouse_item_id' => $wi?->id, 'before' => $before, 'after' => $after, 'delta' => $d, 'item_id' => $itemId];
            $this->line(sprintf('  - %s: %s ← %s', $name, $before, $after));
            if ($after < -0.0001) {
                $negative[] = $name;
            }
        }

        if ($otherTxns > 0) {
            $this->warn("\nتنبيه: {$otherTxns} حركة مخزون أخرى على أدوية هذا الفوج (تسوية/نقل) لن تُحذف.");
        }
        if ($manualMedExpenses > 0) {
            $this->warn("تنبيه: {$manualMedExpenses} مصروف أدوية يدوي غير مرتبط بشحنة لن يُحذف.");
        }
        if ($paid > 0) {
            $this->warn("تنبيه: مُسجّل دفع {$paid} على ديون الشراء — سيُحذف مع الديون.");
        }

        $zero = (bool) $this->option('zero-stock');
        if ($zero) {
            $this->warn("\nتصفير: بعد المسح يصير مخزون كل أصناف الأدوية في المزرعة 0.");
        }

        if ($negative !== [] && ! $zero) {
            $this->error("\nتوقف: المخزون سيصير بالسالب لـ: ".implode('، ', $negative).' (استُهلك منها خارج هذا الفوج). لم يُحذف شيء.');

            return self::FAILURE;
        }

        if (! $this->option('force')) {
            $this->info("\nعرض فقط — لم يُحذف شيء. للتنفيذ أضف --force");

            return self::SUCCESS;
        }

        $medStock = WarehouseItem::where('farm_id', $flock->farm_id)->whereIn('item_id', $medItemIds)->get(['id', 'item_id', 'current_quantity']);

        // نسخة احتياطية كاملة قبل الحذف
        $path = sprintf('purge-backups/flock-%d-medicines-%s.json', $flock->id, now()->format('Ymd-His'));
        Storage::disk('local')->put($path, json_encode([
            'flock_id' => $flock->id,
            'at' => now()->toIso8601String(),
            'flock_medicines' => $logs->map->getAttributes()->values(),
            'expenses' => $expenses->map->getAttributes()->values(),
            'inventory_transactions' => $purchases->merge($consumptions)->map->getAttributes()->values(),
            'stock' => $stock,
            'medicine_stock_before' => $medStock->map->getAttributes()->values(),
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        DB::transaction(function () use ($logs, $expenses, $purchases, $consumptions, $stock, $zero, $medStock): void {
            // الترتيب يحترم المفاتيح الأجنبية: السجلات والديون قبل حركات المخزون
            FlockMedicine::whereIn('id', $logs->pluck('id'))->delete();
            Expense::whereIn('id', $expenses->pluck('id'))->delete();
            InventoryTransaction::whereIn('id', $purchases->pluck('id')->merge($consumptions->pluck('id')))->delete();

            if ($zero) {
                WarehouseItem::whereIn('id', $medStock->pluck('id'))->update(['current_quantity' => 0]);

                return;
            }

            foreach ($stock as $s) {
                if ($s['warehouse_item_id']) {
                    // نطبّق الفرق على القيمة الحالية تحت القفل، لا قيمة محسوبة مسبقاً
                    $wi = WarehouseItem::where('id', $s['warehouse_item_id'])->lockForUpdate()->first();
                    $wi->update(['current_quantity' => round((float) $wi->current_quantity + $s['delta'], 6)]);
                }
            }
        });

        // متوسط التكلفة من الشحنات المتبقية
        $repricing = app(InventoryRepricingService::class);
        foreach (($zero ? $medStock->pluck('item_id') : collect($stock)->pluck('item_id'))->unique() as $itemId) {
            $repricing->repriceItem($flock->farm_id, (int) $itemId);
        }

        $this->info("\nتم الحذف. النسخة الاحتياطية: storage/app/{$path}");

        return self::SUCCESS;
    }
}
