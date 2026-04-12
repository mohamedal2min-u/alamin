<?php

namespace App\Http\Controllers\Api\Inventory;

use App\Actions\Inventory\CreateShipmentAction;
use App\Http\Controllers\Controller;
use App\Models\InventoryTransaction;
use App\Models\Item;
use App\Models\ItemType;
use App\Models\Warehouse;
use App\Models\WarehouseItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    public function __construct(
        private readonly CreateShipmentAction $createShipmentAction,
    ) {}

    // ── GET /api/inventory/items?type=feed|medicine ───────────────────────────

    public function items(Request $request): JsonResponse
    {
        $farmId = $request->attributes->get('farm_id');
        $type   = $request->query('type');

        $query = Item::where('farm_id', $farmId)
            ->where('status', 'active')
            ->with(['itemType:id,code,name']);

        if ($type) {
            $query->whereHas('itemType', fn ($q) => $q->where('code', $type));
        }

        $items = $query->orderBy('name')->get();

        return response()->json([
            'data' => $items->map(fn (Item $item) => [
                'id'           => $item->id,
                'name'         => $item->name,
                'input_unit'   => $item->input_unit,
                'content_unit' => $item->content_unit,
                'unit_value'   => (float) $item->unit_value,
                'type_code'    => $item->itemType?->code,
            ]),
        ]);
    }

    // ── GET /api/inventory/item-types ────────────────────────────────────────

    public function itemTypes(Request $request): JsonResponse
    {
        $farmId = $request->attributes->get('farm_id');

        $types = ItemType::where('is_active', true)
            ->where(function ($q) use ($farmId) {
                $q->whereNull('farm_id')        // system-wide types
                  ->orWhere('farm_id', $farmId); // farm-specific types
            })
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'is_system']);

        return response()->json([
            'data' => $types->map(fn (ItemType $t) => [
                'id'        => $t->id,
                'name'      => $t->name,
                'code'      => $t->code,
                'is_system' => $t->is_system,
            ]),
        ]);
    }

    // ── POST /api/inventory/items ─────────────────────────────────────────────

    public function createItem(Request $request): JsonResponse
    {
        $farmId = $request->attributes->get('farm_id');
        $userId = $request->user()->id;

        $validated = $request->validate([
            'item_type_id'  => ['required', 'integer', 'exists:item_types,id'],
            'name'          => ['required', 'string', 'max:150'],
            'input_unit'    => ['required', 'string', 'max:50'],
            'unit_value'    => ['required', 'numeric', 'min:0.001'],
            'content_unit'  => ['required', 'string', 'max:50'],
            'minimum_stock' => ['nullable', 'numeric', 'min:0'],
            'default_cost'  => ['nullable', 'numeric', 'min:0'],
            'notes'         => ['nullable', 'string', 'max:2000'],
        ]);

        // Check unique name per farm
        if (Item::where('farm_id', $farmId)->where('name', $validated['name'])->exists()) {
            return response()->json(['message' => 'يوجد صنف بهذا الاسم مسبقاً في المزرعة'], 422);
        }

        $item = Item::create([
            'farm_id'       => $farmId,
            'item_type_id'  => $validated['item_type_id'],
            'name'          => $validated['name'],
            'input_unit'    => $validated['input_unit'],
            'unit_value'    => $validated['unit_value'],
            'content_unit'  => $validated['content_unit'],
            'minimum_stock' => $validated['minimum_stock'] ?? null,
            'default_cost'  => $validated['default_cost'] ?? null,
            'status'        => 'active',
            'notes'         => $validated['notes'] ?? null,
            'created_by'    => $userId,
            'updated_by'    => $userId,
        ]);

        return response()->json([
            'message' => 'تمت إضافة الصنف بنجاح',
            'data'    => ['id' => $item->id],
        ], 201);
    }

    // ── GET /api/inventory/stock ──────────────────────────────────────────────

    public function stock(Request $request): JsonResponse
    {
        $farmId = $request->attributes->get('farm_id');

        $items = Item::where('farm_id', $farmId)
            ->where('status', 'active')
            ->with(['itemType:id,code,name'])
            ->withSum('warehouseItems', 'current_quantity')
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $items->map(fn (Item $item) => [
                'id'             => $item->id,
                'name'           => $item->name,
                'type_code'      => $item->itemType?->code,
                'type_name'      => $item->itemType?->name,
                'input_unit'     => $item->input_unit,
                'content_unit'   => $item->content_unit,
                'unit_value'     => (float) $item->unit_value,
                'minimum_stock'  => (float) ($item->minimum_stock ?? 0),
                'total_quantity' => (float) ($item->warehouse_items_sum_current_quantity ?? 0),
            ]),
        ]);
    }

    // ── GET /api/inventory/overview ──────────────────────────────────────────
    // endpoint مجمّع يرجع كل بيانات صفحة المخزون في طلب واحد

    public function overview(Request $request): JsonResponse
    {
        $farmId = $request->attributes->get('farm_id');

        // ── 1. Stock (items + quantities) ────────────────────────────────────
        $items = Item::where('farm_id', $farmId)
            ->where('status', 'active')
            ->with(['itemType:id,code,name'])
            ->withSum('warehouseItems', 'current_quantity')
            ->orderBy('name')
            ->get();

        $stock = $items->map(fn (Item $item) => [
            'id'             => $item->id,
            'name'           => $item->name,
            'type_code'      => $item->itemType?->code,
            'type_name'      => $item->itemType?->name,
            'input_unit'     => $item->input_unit,
            'content_unit'   => $item->content_unit,
            'unit_value'     => (float) $item->unit_value,
            'minimum_stock'  => (float) ($item->minimum_stock ?? 0),
            'total_quantity' => (float) ($item->warehouse_items_sum_current_quantity ?? 0),
        ]);

        // ── 2. Summary KPIs ───────────────────────────────────────────────────
        // حساب الكميات من $stock (محمّل مسبقاً) بدل استعلام WarehouseItem مستقل
        $feedStock  = $stock->filter(fn ($s) => $s['type_code'] === 'feed');
        $medStock   = $stock->filter(fn ($s) => $s['type_code'] === 'medicine');

        $feedQty  = (float) $feedStock->sum('total_quantity');
        $feedUnit = $feedStock->first()['content_unit'] ?? '';
        $medQty   = (float) $medStock->sum('total_quantity');
        $medUnit  = $medStock->first()['content_unit'] ?? '';

        // قيمة المخزون الإجمالية — استعلام تجميعي واحد
        $totalValue = (float) (WarehouseItem::where('farm_id', $farmId)
            ->selectRaw('COALESCE(SUM(current_quantity * COALESCE(average_cost, 0)), 0) as total')
            ->value('total') ?? 0.0);

        $lowCount = $stock->filter(
            fn ($s) => $s['minimum_stock'] > 0 && $s['total_quantity'] <= $s['minimum_stock']
        )->count();

        // آخر حمولة — value() يعيد raw string لا Carbon
        $lastShipmentRaw = InventoryTransaction::where('farm_id', $farmId)
            ->where('direction', 'in')
            ->orderByDesc('transaction_date')
            ->value('transaction_date');

        $lastShipmentDate = $lastShipmentRaw
            ? \Carbon\Carbon::parse($lastShipmentRaw)->toDateString()
            : null;

        $summary = [
            'feed_quantity'      => $feedQty,
            'feed_unit'          => $feedUnit,
            'medicine_quantity'  => $medQty,
            'medicine_unit'      => $medUnit,
            'low_stock_count'    => $lowCount,
            'last_shipment_date' => $lastShipmentDate,
            'total_value'        => $totalValue,
        ];

        // ── 3. Warehouses ─────────────────────────────────────────────────────
        $warehouses = Warehouse::where('farm_id', $farmId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'location'])
            ->map(fn ($w) => [
                'id'       => $w->id,
                'name'     => $w->name,
                'location' => $w->location,
            ]);

        // ── 4. Item types ─────────────────────────────────────────────────────
        $itemTypes = ItemType::where('is_active', true)
            ->where(fn ($q) => $q->whereNull('farm_id')->orWhere('farm_id', $farmId))
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'is_system'])
            ->map(fn (ItemType $t) => [
                'id'        => $t->id,
                'name'      => $t->name,
                'code'      => $t->code,
                'is_system' => $t->is_system,
            ]);

        // ── 5. Transactions (آخر 100 حركة فقط) ──────────────────────────────
        $txns = InventoryTransaction::where('farm_id', $farmId)
            ->with([
                'item:id,name,input_unit,content_unit,unit_value',
                'flock:id,name',
                'warehouse:id,name',
                'createdByUser:id,name',
            ])
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->limit(100)
            ->get()
            ->map(fn (InventoryTransaction $t) => [
                'id'                => $t->id,
                'transaction_date'  => \Carbon\Carbon::parse($t->getRawOriginal('transaction_date'))->toDateString(),
                'item_name'         => $t->item?->name,
                'transaction_type'  => $t->transaction_type,
                'direction'         => $t->direction,
                'original_quantity' => (float) $t->original_quantity,
                'computed_quantity' => (float) $t->computed_quantity,
                'input_unit'        => $t->item?->input_unit,
                'content_unit'      => $t->item?->content_unit,
                'unit_price'        => $t->unit_price !== null ? (float) $t->unit_price : null,
                'total_amount'      => $t->total_amount !== null ? (float) $t->total_amount : null,
                'payment_status'    => $t->payment_status,
                'supplier_name'     => $t->supplier_name,
                'invoice_no'        => $t->invoice_no,
                'reference_no'      => $t->reference_no,
                'flock_name'        => $t->flock?->name,
                'warehouse_name'    => $t->warehouse?->name,
                'created_by_name'   => $t->createdByUser?->name,
                'notes'             => $t->notes,
                'attachment_path'   => $t->attachment_path ? asset('storage/' . $t->attachment_path) : null,
            ]);

        return response()->json([
            'data' => [
                'stock'       => $stock,
                'summary'     => $summary,
                'warehouses'  => $warehouses,
                'item_types'  => $itemTypes,
                'transactions' => $txns,
            ],
        ]);
    }

    // ── GET /api/inventory/summary ────────────────────────────────────────────

    public function summary(Request $request): JsonResponse
    {
        $farmId = $request->attributes->get('farm_id');

        $warehouseItems = WarehouseItem::where('farm_id', $farmId)
            ->with('item.itemType:id,code')
            ->get();

        $feedQty    = 0.0; $feedUnit  = '';
        $medQty     = 0.0; $medUnit   = '';
        $totalValue = 0.0;

        foreach ($warehouseItems as $wi) {
            $typeCode = $wi->item?->itemType?->code;
            $qty      = (float) $wi->current_quantity;
            $cost     = (float) ($wi->average_cost ?? 0);
            $totalValue += $qty * $cost;

            if ($typeCode === 'feed') {
                $feedQty += $qty; $feedUnit = $wi->item?->content_unit ?? '';
            } elseif ($typeCode === 'medicine') {
                $medQty += $qty; $medUnit = $wi->item?->content_unit ?? '';
            }
        }

        $allItems = Item::where('farm_id', $farmId)
            ->where('status', 'active')
            ->withSum('warehouseItems', 'current_quantity')
            ->get();

        $lowCount = $allItems->filter(
            fn ($i) => (float) ($i->minimum_stock ?? 0) > 0
                && (float) ($i->warehouse_items_sum_current_quantity ?? 0) <= (float) $i->minimum_stock
        )->count();

        $lastShipmentRaw = InventoryTransaction::where('farm_id', $farmId)
            ->where('direction', 'in')
            ->orderByDesc('transaction_date')
            ->value('transaction_date');

        return response()->json([
            'data' => [
                'feed_quantity'      => $feedQty,
                'feed_unit'          => $feedUnit,
                'medicine_quantity'  => $medQty,
                'medicine_unit'      => $medUnit,
                'low_stock_count'    => $lowCount,
                'last_shipment_date' => $lastShipmentRaw
                    ? \Carbon\Carbon::parse($lastShipmentRaw)->toDateString()
                    : null,
                'total_value'        => $totalValue,
            ],
        ]);
    }

    // ── GET /api/inventory/warehouses ─────────────────────────────────────────

    public function warehouses(Request $request): JsonResponse
    {
        $farmId = $request->attributes->get('farm_id');

        $warehouses = Warehouse::where('farm_id', $farmId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'location']);

        return response()->json([
            'data' => $warehouses->map(fn ($w) => [
                'id'       => $w->id,
                'name'     => $w->name,
                'location' => $w->location,
            ]),
        ]);
    }

    // ── GET /api/inventory/transactions ──────────────────────────────────────

    public function transactions(Request $request): JsonResponse
    {
        $farmId = $request->attributes->get('farm_id');

        $txns = InventoryTransaction::where('farm_id', $farmId)
            ->with([
                'item:id,name,input_unit,content_unit,unit_value',
                'flock:id,name',
                'warehouse:id,name',
                'createdByUser:id,name',
            ])
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'data' => $txns->map(fn (InventoryTransaction $t) => [
                'id'                => $t->id,
                'transaction_date'  => $t->transaction_date->toDateString(),
                'item_name'         => $t->item?->name,
                'transaction_type'  => $t->transaction_type,
                'direction'         => $t->direction,
                'original_quantity' => (float) $t->original_quantity,
                'computed_quantity' => (float) $t->computed_quantity,
                'input_unit'        => $t->item?->input_unit,
                'content_unit'      => $t->item?->content_unit,
                'unit_price'        => $t->unit_price !== null ? (float) $t->unit_price : null,
                'total_amount'      => $t->total_amount !== null ? (float) $t->total_amount : null,
                'payment_status'    => $t->payment_status,
                'supplier_name'     => $t->supplier_name,
                'invoice_no'        => $t->invoice_no,
                'reference_no'      => $t->reference_no,
                'flock_name'        => $t->flock?->name,
                'warehouse_name'    => $t->warehouse?->name,
                'created_by_name'   => $t->createdByUser?->name,
                'notes'             => $t->notes,
                'attachment_path'   => $t->attachment_path ? asset('storage/' . $t->attachment_path) : null,
            ]),
        ]);
    }

    // ── POST /api/inventory/transactions ─────────────────────────────────────

    public function addShipment(Request $request): JsonResponse
    {
        $farmId = $request->attributes->get('farm_id');
        $userId = $request->user()->id;

        $validated = $request->validate([
            'item_id'           => ['required', 'integer'],
            'warehouse_id'      => ['required', 'integer'],
            'transaction_date'  => ['required', 'date_format:Y-m-d'],
            'original_quantity' => ['required', 'numeric', 'min:0.001'],
            'unit_price'        => ['nullable', 'numeric', 'min:0'],
            'total_amount'      => ['nullable', 'numeric', 'min:0'],
            'payment_status'    => ['nullable', 'in:paid,unpaid,partial'],
            'supplier_name'     => ['nullable', 'string', 'max:255'],
            'invoice_no'        => ['nullable', 'string', 'max:100'],
            'notes'             => ['nullable', 'string', 'max:2000'],
            'attachment'        => ['nullable', 'file', 'image', 'max:5120'], // Max 5MB
        ]);

        // Handle attachment storage
        $attachmentPath = null;
        if ($request->hasFile('attachment')) {
            $attachmentPath = $request->file('attachment')->store("farms/{$farmId}/inventory", 'public');
        }

        try {
            $data = $validated;
            $data['attachment_path'] = $attachmentPath;
            $txn = $this->createShipmentAction->execute($farmId, $userId, $data);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'تمت إضافة الحمولة بنجاح',
            'data'    => ['id' => $txn->id],
        ], 201);
    }
}
