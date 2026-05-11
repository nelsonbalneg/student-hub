<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'clearance_update_id',
    'office_id',
    'sequence',
    'is_required',
    'can_upload_accountability',
    'can_resolve_accountability',
])]
class ClearanceUpdateOffice extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'can_upload_accountability' => 'boolean',
            'can_resolve_accountability' => 'boolean',
        ];
    }

    public function clearanceUpdate(): BelongsTo
    {
        return $this->belongsTo(ClearanceUpdate::class, 'clearance_update_id');
    }

    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class);
    }
}
