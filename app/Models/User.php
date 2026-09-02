<?php

namespace App\Models;

use App\Support\Permissions;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'area_id', 'microsoft_id', 'is_active'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'is_active' => 'boolean',
            'password' => 'hashed',
        ];
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    /** Las que ha escrito esta persona. */
    public function proposals(): HasMany
    {
        return $this->hasMany(Proposal::class);
    }

    /** Las que le han asignado para revisar. */
    public function reviewing(): HasMany
    {
        return $this->hasMany(Proposal::class, 'reviewer_id');
    }

    /**
     * Si puede ver las privadas y las anónimas de otros. Es el permiso que
     * separa al comité de todo el mundo, administración y soporte incluidos.
     */
    public function canSeeRestrictedProposals(): bool
    {
        return $this->hasPermissionTo(Permissions::VIEW_RESTRICTED);
    }

    /** Entra con Microsoft 365 y no tiene contraseña propia. */
    public function usesSingleSignOn(): bool
    {
        return $this->microsoft_id !== null;
    }
}
