<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * El hilo de la propuesta.
 *
 * is_internal = true  -> evaluación, solo el comité.
 * is_internal = false -> mensaje que el proponente sí ve.
 */
class Comment extends Model
{
    protected $fillable = ['proposal_id', 'user_id', 'body', 'is_internal'];

    protected function casts(): array
    {
        return ['is_internal' => 'boolean'];
    }

    public function proposal(): BelongsTo
    {
        return $this->belongsTo(Proposal::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Lo que esta persona puede leer del hilo. */
    public function scopeReadableBy(Builder $query, User $user): Builder
    {
        if ($user->canSeeRestrictedProposals()) {
            return $query;
        }

        return $query->where('is_internal', false);
    }

    /**
     * Quién firma el comentario. Si lo escribió el proponente de una anónima,
     * su nombre tampoco aparece.
     */
    public function authorName(): string
    {
        if ($this->proposal?->isAnonymous() && $this->user_id === $this->proposal->user_id) {
            return 'Proponente';
        }

        return $this->user?->name ?? 'Usuario dado de baja';
    }
}
