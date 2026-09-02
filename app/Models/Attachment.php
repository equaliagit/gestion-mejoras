<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Number;

class Attachment extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['proposal_id', 'user_id', 'original_name', 'path', 'mime', 'size_bytes'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function proposal(): BelongsTo
    {
        return $this->belongsTo(Proposal::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mime, 'image/');
    }

    /** «2,4 MB», para pintarlo al lado del nombre. */
    public function humanSize(): string
    {
        return Number::fileSize($this->size_bytes, precision: 1);
    }
}
