<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserPersonalizationProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'persona_summary',
        'preferences',
        'weekly_reflection_notes',
        'last_reflected_at',
    ];

    protected function casts(): array
    {
        return [
            'preferences' => 'array',
            'last_reflected_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
