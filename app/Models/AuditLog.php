<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    protected $fillable = ['actor_id', 'action', 'target_type', 'target_id', 'reason', 'ip_address', 'metadata'];

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }
}
