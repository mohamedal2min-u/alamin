<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Farm extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'location',
        'status',
        'admin_user_id',
        'started_at',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'date',
        ];
    }

    // ─── Admin ────────────────────────────────────────────────────────────────

    public function adminUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_user_id');
    }

    // ─── Members ──────────────────────────────────────────────────────────────

    public function farmMemberships(): HasMany
    {
        return $this->hasMany(FarmUser::class, 'farm_id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'farm_users', 'farm_id', 'user_id')
            ->using(FarmUser::class)
            ->withPivot(['status', 'is_primary', 'joined_at', 'notes'])
            ->withTimestamps();
    }

    // ─── Flocks ───────────────────────────────────────────────────────────────

    public function flocks(): HasMany
    {
        return $this->hasMany(Flock::class, 'farm_id');
    }

    public function activeFlock(): HasOne
    {
        return $this->hasOne(Flock::class, 'farm_id')->where('status', 'active');
    }

    // ─── Inventory ────────────────────────────────────────────────────────────

    public function warehouses(): HasMany
    {
        return $this->hasMany(Warehouse::class, 'farm_id');
    }

    public function itemTypes(): HasMany
    {
        return $this->hasMany(ItemType::class, 'farm_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(Item::class, 'farm_id');
    }

    public function inventoryTransactions(): HasMany
    {
        return $this->hasMany(InventoryTransaction::class, 'farm_id');
    }

    // ─── Accounting ───────────────────────────────────────────────────────────

    public function expenseCategories(): HasMany
    {
        return $this->hasMany(ExpenseCategory::class, 'farm_id');
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class, 'farm_id');
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class, 'farm_id');
    }

    // ─── Partners ─────────────────────────────────────────────────────────────

    public function partners(): HasMany
    {
        return $this->hasMany(Partner::class, 'farm_id');
    }

    public function farmPartnerShares(): HasMany
    {
        return $this->hasMany(FarmPartnerShare::class, 'farm_id');
    }

    // ─── Audit ────────────────────────────────────────────────────────────────

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
