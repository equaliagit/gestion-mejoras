<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Los ocho estados del flujo.
 *
 * Las constantes son el contrato con el código: las reglas de transición y los
 * avisos cuelgan del `code`, nunca del nombre visible (que sí es editable).
 */
class ProposalStatus extends Model
{
    public const NEW = 'new';

    public const IN_REVIEW = 'in_review';

    public const AWAITING_INFO = 'awaiting_info';

    public const IN_COMMITTEE = 'in_committee';

    public const APPROVED = 'approved';

    public const REJECTED = 'rejected';

    public const POSTPONED = 'postponed';

    public const IMPLEMENTED = 'implemented';

    protected $fillable = ['code', 'name', 'color', 'position', 'is_open', 'requires_reason', 'is_active'];

    protected function casts(): array
    {
        return [
            'is_open' => 'boolean',
            'requires_reason' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function proposals(): HasMany
    {
        return $this->hasMany(Proposal::class, 'status_id');
    }

    public function scopeCode(Builder $query, string $code): Builder
    {
        return $query->where('code', $code);
    }

    /** Id a partir del código, cacheado: se consulta en cada transición. */
    public static function idFor(string $code): int
    {
        return once(fn () => static::query()->pluck('id', 'code')->all())[$code]
            ?? throw new \InvalidArgumentException("Estado desconocido: {$code}");
    }

    public function hasCode(string $code): bool
    {
        return $this->code === $code;
    }
}
