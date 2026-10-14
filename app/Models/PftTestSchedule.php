<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PftTestSchedule extends Model
{
    /**
     * Campus-local timezone used to decide whether a schedule window is open.
     * Matches the timezone used by the student submission gate.
     */
    public const TIMEZONE = 'Asia/Singapore';

    public const PHASES = ['pre', 'post'];

    public const STATUS_NOT_SCHEDULED = 'not_scheduled';

    public const STATUS_UPCOMING = 'upcoming';

    public const STATUS_OPEN = 'open';

    public const STATUS_CLOSED = 'closed';

    protected $fillable = ['site_academic_term_id', 'phase', 'opens_on', 'closes_on'];

    protected function casts(): array
    {
        return ['site_academic_term_id' => 'integer', 'opens_on' => 'date:Y-m-d', 'closes_on' => 'date:Y-m-d'];
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(SiteAcademicTerm::class, 'site_academic_term_id');
    }

    /**
     * Today's calendar date (Y-m-d) in the campus-local timezone.
     */
    public static function localToday(): string
    {
        return today(self::TIMEZONE)->toDateString();
    }

    /**
     * Resolve the schedule status for the given campus-local date (inclusive boundaries).
     */
    public static function statusFor(?self $schedule, string $today): string
    {
        if (! $schedule || ! $schedule->opens_on || ! $schedule->closes_on) {
            return self::STATUS_NOT_SCHEDULED;
        }

        $opensOn = $schedule->opens_on->toDateString();
        $closesOn = $schedule->closes_on->toDateString();

        return match (true) {
            $today < $opensOn => self::STATUS_UPCOMING,
            $today > $closesOn => self::STATUS_CLOSED,
            default => self::STATUS_OPEN,
        };
    }
}
