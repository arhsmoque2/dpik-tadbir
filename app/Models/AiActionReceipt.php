<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $action_type
 * @property string $description
 * @property array<int, string>|null $target_recipients
 * @property array<string, mixed>|null $payload
 * @property string $status
 * @property string|null $approval_token
 * @property Carbon|null $executed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class AiActionReceipt extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'action_type',
        'description',
        'target_recipients',
        'payload',
        'status',
        'approval_token',
        'executed_at',
    ];

    protected function casts(): array
    {
        return [
            'target_recipients' => 'array',
            'payload' => 'array',
            'executed_at' => 'datetime',
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
