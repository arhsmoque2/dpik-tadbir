<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Bundle extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'filter_label',
        'filter_criteria',
        'project_code',
        'retrieved_at',
        'email_count',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'filter_criteria' => 'array',
            'retrieved_at' => 'datetime',
            'email_count' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function bundleEmails(): HasMany
    {
        return $this->hasMany(BundleEmail::class);
    }
}
