<?php

namespace App\Models;

use App\Support\Registration;
use Illuminate\Database\Eloquent\Model;

class PendingRegistration extends Model
{
    protected $fillable = ['email', 'name', 'password', 'code_hash', 'attempts', 'expires_at', 'last_sent_at', 'ip'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'last_sent_at' => 'datetime'];
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function codeMatches(string $code): bool
    {
        return hash_equals($this->code_hash, Registration::hash($code));
    }
}
