<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable([
    'name',
    'email',
    'password',
    'email_verified_at',
    'is_active',
    'user_type',
    'office',
    'office_id',
    'department',
    'sso_id',
    'sso_uuid',
    'sso_username',
    'sso_account_type',
    'sso_avatar',
    'tenant_id',
    'campus_id',
    'campus_name',
    'student_no',
    'employee_no',
])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable, TwoFactorAuthenticatable;

    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class);
    }

    public function legalAcceptances(): HasMany
    {
        return $this->hasMany(UserLegalAcceptance::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'is_active' => 'boolean',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'campus_id' => 'integer',
        ];
    }

    public function achievements(): HasMany
    {
        return $this->hasMany(Achievement::class);
    }

    public function trainings(): HasMany
    {
        return $this->hasMany(Training::class);
    }

    public function studentClearances(): HasMany
    {
        return $this->hasMany(StudentSemesterClearance::class, 'student_id');
    }

    public function clearanceAccountabilities(): HasMany
    {
        return $this->hasMany(ClearanceAccountability::class, 'student_id');
    }

    public function performedClearanceLogs(): HasMany
    {
        return $this->hasMany(ClearanceLog::class, 'performed_by');
    }

    /**
     * @return list<string>
     */
    public function permissionNames(): array
    {
        return $this->getAllPermissions()
            ->pluck('name')
            ->values()
            ->all();
    }
}
