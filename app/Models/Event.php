<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Event extends Model
{
    protected $fillable = [
        'title',
        'description',
        'category',
        'status',
        'venue',
        'city',
        'starts_at',
        'capacity',
        'cover_url',
        'is_premium',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'is_premium' => 'boolean',
    ];

    public function invitations(): HasMany
    {
        return $this->hasMany(EventInvitation::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(EventImage::class)->orderBy('sort_order');
    }
}
