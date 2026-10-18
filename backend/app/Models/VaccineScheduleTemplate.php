<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VaccineScheduleTemplate extends Model
{
    protected $fillable = ['farm_id', 'name', 'is_active', 'is_example', 'created_by', 'updated_by'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'is_example' => 'boolean'];
    }

    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class, 'farm_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(VaccineScheduleTemplateItem::class, 'template_id')->orderBy('age_day')->orderBy('id');
    }
}
