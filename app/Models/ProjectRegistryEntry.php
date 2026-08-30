<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectRegistryEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_code',
        'project_name',
        'summary',
        'decisions',
        'commitments',
        'source_type',
        'user_id',
        'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'decisions' => 'array',
            'commitments' => 'array',
            'recorded_at' => 'datetime',
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
