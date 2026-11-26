<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'phone', 'is_active'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    /** الحسابات الموقوفة لا تستطيع الدخول */
    public function canAccessPanel(Panel $panel): bool
    {
        return (bool) $this->is_active;
    }

    /** المدير العام (super_admin) يملك كل الصلاحيات تلقائياً */
    public function isAdmin(): bool
    {
        return $this->hasRole(config('filament-shield.super_admin.name', 'super_admin'));
    }

    /** هل يرى كل العملاء؟ (وإلا يرى عملاءه فقط) */
    public function canSeeAllLeads(): bool
    {
        return $this->isAdmin() || $this->can('ViewAll:Lead');
    }

    /** هل يوزّع العملاء على الوكلاء؟ */
    public function canAssignLeads(): bool
    {
        return $this->isAdmin() || $this->can('Assign:Lead');
    }

    public function deals(): HasMany
    {
        return $this->hasMany(Deal::class, 'agent_id');
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class, 'assigned_to');
    }
}
