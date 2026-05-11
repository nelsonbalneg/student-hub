<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Models\Role;

class AnnouncementTarget extends Model
{
    use HasFactory;

    protected $fillable = [
        'announcement_id',
        'role_id',
        'office_id',
        'department_id',
        'campus_id',
        'year_level',
    ];

    public function announcement()
    {
        return $this->belongsTo(Announcement::class);
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }
}
