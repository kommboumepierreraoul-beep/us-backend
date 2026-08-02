<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiscoveryPreference extends Model
{
    protected $fillable = ['user_id', 'min_age', 'max_age', 'radius_km', 'gender', 'same_university_only'];

    protected function casts(): array
    {
        return ['same_university_only' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
