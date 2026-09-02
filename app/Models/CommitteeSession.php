<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommitteeSession extends Model
{
    protected $fillable = ['held_on', 'notes', 'closed_at'];

    protected function casts(): array
    {
        return [
            'held_on' => 'date',
            'closed_at' => 'datetime',
        ];
    }

    /** El orden del día de la sesión. */
    public function proposals(): HasMany
    {
        return $this->hasMany(Proposal::class);
    }

    public function isClosed(): bool
    {
        return $this->closed_at !== null;
    }
}
