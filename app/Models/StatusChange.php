<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una fila por cada cambio de estado. Se escribe y no se toca más:
 * de aquí salen el historial de la ficha y todos los datos de los informes.
 */
class StatusChange extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['proposal_id', 'from_status_id', 'to_status_id', 'user_id', 'comment'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function proposal(): BelongsTo
    {
        return $this->belongsTo(Proposal::class);
    }

    public function fromStatus(): BelongsTo
    {
        return $this->belongsTo(ProposalStatus::class, 'from_status_id');
    }

    public function toStatus(): BelongsTo
    {
        return $this->belongsTo(ProposalStatus::class, 'to_status_id');
    }

    /** Quién lo hizo. Ojo: en propuestas anónimas puede ser el proponente. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
