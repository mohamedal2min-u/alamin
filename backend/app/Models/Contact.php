<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * مورد أو تاجر (مشترٍ) أو كلاهما — لكل مزرعة.
 */
class Contact extends Model
{
    public const TYPES = ['supplier', 'buyer', 'both'];

    protected $fillable = [
        'farm_id',
        'name',
        'type',
        'phone',
        'whatsapp',
        'notes',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class, 'farm_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'contact_id');
    }

    /** هل يرتبط بأي سجل مالي أو دفعة؟ (عندها لا يُحذف بل يُعطَّل) */
    public function isReferenced(): bool
    {
        return Expense::where('contact_id', $this->id)->exists()
            || Sale::where('contact_id', $this->id)->exists()
            || FlockWaterLog::where('contact_id', $this->id)->exists()
            || InventoryTransaction::where('contact_id', $this->id)->exists()
            || Payment::where('contact_id', $this->id)->exists();
    }
}
