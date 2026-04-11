<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'whatsapp',
        'password',
        'status',
        'avatar_path',
        'last_login_at',
        'email_verified_at',
        'created_by',
        'updated_by',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at'     => 'datetime',
            'password'          => 'hashed',
        ];
    }

    // ─── Settings ────────────────────────────────────────────────────────────

    public function settings(): HasOne
    {
        return $this->hasOne(UserSettings::class, 'user_id');
    }

    // ─── Farm Membership ─────────────────────────────────────────────────────

    /** عضوية المستخدم في المداجن (membership rows) */
    public function farmMemberships(): HasMany
    {
        return $this->hasMany(FarmUser::class, 'user_id');
    }

    /** المداجن التي ينتمي إليها عبر farm_users */
    public function farms(): BelongsToMany
    {
        return $this->belongsToMany(Farm::class, 'farm_users', 'user_id', 'farm_id')
            ->using(FarmUser::class)
            ->withPivot(['status', 'is_primary', 'joined_at', 'notes'])
            ->withTimestamps();
    }

    /** المداجن التي يديرها كـ admin */
    public function adminFarms(): HasMany
    {
        return $this->hasMany(Farm::class, 'admin_user_id');
    }

    // ─── Partners ─────────────────────────────────────────────────────────────

    /** ملفات الشريك المرتبطة بهذا الحساب */
    public function partnerProfiles(): HasMany
    {
        return $this->hasMany(Partner::class, 'user_id');
    }

    // ─── Audit (self-referential — no FK constraint in DB) ────────────────────

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
