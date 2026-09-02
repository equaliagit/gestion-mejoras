<?php

namespace App\Models;

use App\Enums\Priority;
use App\Enums\Visibility;
use App\Models\Scopes\VisibilityScope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * La propuesta de mejora.
 *
 * Guarda el estado de HOY; cómo se ha llegado hasta él está en status_changes.
 * El filtro de visibilidad va puesto de serie (ver VisibilityScope).
 */
#[ScopedBy([VisibilityScope::class])]
class Proposal extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id', 'area_id', 'title', 'problem', 'proposal', 'expected_benefit',
        'priority', 'visibility',
    ];

    protected function casts(): array
    {
        return [
            'priority' => Priority::class,
            'committee_priority' => Priority::class,
            'visibility' => Visibility::class,
            'decided_at' => 'date',
            'revisit_on' => 'date',
            'planned_start_on' => 'date',
            'planned_end_on' => 'date',
            'started_on' => 'date',
            'closed_on' => 'date',
            'submitted_at' => 'datetime',
        ];
    }

    // ---------------------------------------------------------------- Relaciones

    /**
     * Quien la escribió. Se guarda siempre, también en las anónimas.
     * Para pintarla en pantalla no se usa esto nunca: se usa authorName().
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(ProposalStatus::class, 'status_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function implementer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'implementer_id');
    }

    public function committeeSession(): BelongsTo
    {
        return $this->belongsTo(CommitteeSession::class);
    }

    public function impacts(): BelongsToMany
    {
        return $this->belongsToMany(Impact::class);
    }

    public function statusChanges(): HasMany
    {
        return $this->hasMany(StatusChange::class)->oldest();
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class)->oldest();
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class);
    }

    // ------------------------------------------------------------------ Estado

    public function isDraft(): bool
    {
        return $this->submitted_at === null;
    }

    public function isAnonymous(): bool
    {
        return $this->visibility === Visibility::Anonymous;
    }

    /** La prioridad que manda: la del comité si la ha ajustado. */
    public function effectivePriority(): Priority
    {
        return $this->committee_priority ?? $this->priority;
    }

    /**
     * Cómo se firma la propuesta en pantalla. ESTE es el único sitio de la
     * aplicación que decide si se enseña el nombre del autor o no.
     */
    public function authorName(): string
    {
        if ($this->isAnonymous()) {
            return 'Anónima';
        }

        return $this->author?->name ?? 'Usuario dado de baja';
    }

    // ------------------------------------------------------------------ Consultas

    public function scopeSubmitted(Builder $query): Builder
    {
        return $query->whereNotNull('submitted_at');
    }

    public function scopeDrafts(Builder $query): Builder
    {
        return $query->whereNull('submitted_at');
    }

    /** Las que siguen vivas para el comité. */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->submitted()->whereHas('status', fn (Builder $q) => $q->where('is_open', true));
    }

    public function scopeInStatus(Builder $query, string $code): Builder
    {
        return $query->whereHas('status', fn (Builder $q) => $q->where('code', $code));
    }
}
