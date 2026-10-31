import { Client } from 'ssh2'

const HOST = '82.29.181.61'
const PASS = 'a550055A!'

const cmd = `cd /home/alamin-api/app/backend && php artisan tinker --execute="
use App\\Models\\FlockFeedLog;
use App\\Models\\InventoryTransaction;
use App\\Models\\WarehouseItem;
use App\\Models\\Warehouse;
use App\\Models\\Item;
use App\\Models\\Expense;
use Illuminate\\Support\\Facades\\DB;

DB::transaction(function () {
    \\$log = FlockFeedLog::findOrFail(73);
    if (\\$log->item_id != 32) { throw new \\Exception('log 73 item_id is not 32 anymore, aborting: '.\\$log->item_id); }

    \\$farmId = \\$log->farm_id;
    \\$flockId = \\$log->flock_id;
    \\$warehouse = Warehouse::where('farm_id', \\$farmId)->where('is_active', true)->firstOrFail();

    \\$purchaseTxn = InventoryTransaction::findOrFail(273);
    \\$consumeTxn  = InventoryTransaction::findOrFail(274);
    if (\\$purchaseTxn->item_id != 32 || \\$consumeTxn->item_id != 32) { throw new \\Exception('txn item mismatch'); }

    \\$expense140 = Expense::find(140);
    if (\\$expense140 && \\$expense140->linked_inventory_transaction_id == 273) {
        \\$expense140->delete();
        echo 'deleted expense 140'.PHP_EOL;
    }

    \\$purchaseTxn->delete();
    \\$consumeTxn->delete();
    echo 'deleted txns 273 and 274 (net zero effect on item 32 stock)'.PHP_EOL;

    \\$oldWh = WarehouseItem::where('warehouse_id', \\$warehouse->id)->where('item_id', 32)->lockForUpdate()->first();
    echo 'item 32 stock after cleanup: '.(\\$oldWh->current_quantity ?? 'N/A').PHP_EOL;

    \\$newItem = Item::findOrFail(20);
    \\$qtyBags = \\$log->quantity;
    \\$realQty = \\$qtyBags * (\\$newItem->unit_value ?: 1);

    \\$newWh = WarehouseItem::where('warehouse_id', \\$warehouse->id)->where('item_id', 20)->lockForUpdate()->firstOrFail();
    if (\\$newWh->current_quantity < \\$realQty) { throw new \\Exception('insufficient stock for item 20: have '.\\$newWh->current_quantity.' need '.\\$realQty); }

    \\$avgCost = (float) (\\$newWh->average_cost ?? 0);
    \\$consumptionCost = \\$avgCost * \\$realQty;

    \\$newTxn = InventoryTransaction::create([
        'farm_id'           => \\$farmId,
        'warehouse_id'      => \\$warehouse->id,
        'item_id'           => 20,
        'flock_id'          => \\$flockId,
        'transaction_date'  => \\$log->entry_date,
        'transaction_type'  => 'consumption',
        'direction'         => 'out',
        'source_module'     => 'flock_feed',
        'original_quantity' => \\$qtyBags,
        'computed_quantity' => \\$realQty,
        'unit_price'        => \\$avgCost > 0 ? \\$avgCost : null,
        'input_unit'        => \\$newItem->input_unit,
        'content_unit'      => \\$newItem->content_unit,
        'total_amount'      => \\$consumptionCost > 0 ? \\$consumptionCost : null,
        'created_by'        => \\$log->created_by,
        'updated_by'        => \\$log->created_by,
    ]);

    \\$newWh->decrement('current_quantity', \\$realQty);

    \\$log->item_id = 20;
    \\$log->inventory_transaction_id = \\$newTxn->id;
    \\$log->unit_label = \\$newItem->input_unit ?? \\$log->unit_label;
    \\$log->updated_by = \\$log->created_by;
    \\$log->save();

    echo 'log 73 corrected: item_id now '.\\$log->item_id.' inventory_transaction_id='.\\$log->inventory_transaction_id.PHP_EOL;
    echo 'item 20 stock after decrement: '.\\$newWh->fresh()->current_quantity.PHP_EOL;
});
echo 'DONE'.PHP_EOL;
"`

const conn = new Client()
conn.on('ready', () => {
  conn.exec(cmd, (err, stream) => {
    if (err) { console.error(err); conn.end(); return }
    stream.on('data', d => process.stdout.write(d.toString()))
    stream.stderr.on('data', d => process.stderr.write(d.toString()))
    stream.on('close', () => { conn.end() })
  })
}).connect({ host: HOST, port: 22, username: 'alamin-api', password: PASS })
