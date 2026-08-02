<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Profile extends Model
{
    protected $fillable = [
        'user_id', 'university_id', 'first_name', 'birth_date', 'gender', 'looking_for',
        'bio', 'study_level', 'languages', 'intentions', 'visibility',
        'completion_score', 'certification_score', 'certification_status',
        'certified_at', 'certification_notified_at', 'university_changed_at',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'languages' => 'array',
            'intentions' => 'array',
            'certified_at' => 'datetime',
            'certification_notified_at' => 'datetime',
            'university_changed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function university(): BelongsTo
    {
        return $this->belongsTo(University::class);
    }

    public function interests(): BelongsToMany
    {
        return $this->belongsToMany(Interest::class);
    }
}
