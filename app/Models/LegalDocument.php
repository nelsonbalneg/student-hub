<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'type',
    'title',
    'slug',
    'content',
    'version',
    'is_active',
    'published_at',
    'created_by',
    'updated_by',
])]
class LegalDocument extends Model
{
    public const TYPE_TERMS = 'terms';

    public const TYPE_COOKIE_POLICY = 'cookie_policy';

    public const TYPE_PRIVACY_POLICY = 'privacy_policy';

    public const TYPES = [
        self::TYPE_TERMS,
        self::TYPE_COOKIE_POLICY,
        self::TYPE_PRIVACY_POLICY,
    ];

    public function acceptances(): HasMany
    {
        return $this->hasMany(UserLegalAcceptance::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    public static function activeFor(string $type): ?self
    {
        return self::query()
            ->active()
            ->ofType($type)
            ->latest('published_at')
            ->latest('id')
            ->first();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'published_at' => 'datetime',
        ];
    }
}
