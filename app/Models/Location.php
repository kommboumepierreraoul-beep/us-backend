<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Location extends Model
{
    protected $fillable = ['user_id', 'latitude', 'longitude', 'accuracy_meters'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
