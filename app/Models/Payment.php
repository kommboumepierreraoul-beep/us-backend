<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $fillable = [
        'user_id', 'plan_id', 'subscription_id', 'provider', 'provider_reference',
        'idempotency_key', 'amount_cents', 'currency', 'phone', 'status', 'payload', 'confirmed_at',
    ];

    protected function casts(): array
    {
        return ['payload' => 'array', 'confirmed_at' => 'datetime'];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
