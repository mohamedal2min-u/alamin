<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VaccineScheduleTemplateItem extends Model
{
    public const METHODS = ['drinking_water', 'spray', 'eye_drop', 'injection'];

    protected $fillable = ['template_id', 'age_day', 'title', 'method', 'notes', 'withdrawal_days'];

    protected function casts(): array
    {
        return ['age_day' => 'integer', 'withdrawal_days' => 'integer'];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(VaccineScheduleTemplate::class, 'template_id');
    }
}
