<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkspaceMember extends Model
{
    protected $fillable = ['workspace_id', 'user_id', 'role', 'is_restricted'];

    protected function casts(): array
    {
        return ['is_restricted' => 'boolean'];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isOwner(): bool
    {
        return $this->role === 'owner';
    }
}
