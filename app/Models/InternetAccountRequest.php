<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'student_no',
    'semester',
    'term_id',
    'campus_id',
    'username',
    'password',
    'status',
    'failure_reason',
    'mikrotik_response',
])]
#[Hidden(['password'])]
class InternetAccountRequest extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_FAILED = 'failed';

    protected function casts(): array
    {
        return [
            'password' => 'encrypted',
            'mikrotik_response' => 'array',
            'term_id' => 'string',
            'campus_id' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
