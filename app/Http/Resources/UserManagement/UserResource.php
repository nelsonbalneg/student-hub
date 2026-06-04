<?php

namespace App\Http\Resources\UserManagement;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'email_verified_at' => $this->email_verified_at?->format('Y-m-d\TH:i'),
            'user_type' => $this->user_type,
            'office' => $this->office,
            'office_id' => $this->office_id,
            'office_details' => $this->whenLoaded('office', fn () => [
                'id' => $this->office->id,
                'name' => $this->office->name,
                'code' => $this->office->code,
            ]),
            'department' => $this->department,
            'sso_id' => $this->sso_id,
            'sso_uuid' => $this->sso_uuid,
            'sso_username' => $this->sso_username,
            'sso_account_type' => $this->sso_account_type,
            'sso_avatar' => $this->sso_avatar,
            'tenant_id' => $this->tenant_id,
            'campus_id' => $this->campus_id,
            'campus_name' => $this->campus_name,
            'student_no' => $this->student_no,
            'employee_no' => $this->employee_no,
            'two_factor_confirmed_at' => $this->two_factor_confirmed_at?->format('Y-m-d\TH:i'),
            'status' => $this->is_active ? 'Active' : 'Inactive',
            'is_active' => $this->is_active,
            'created_at' => $this->created_at?->format('M d, Y'),
            'created_at_full' => $this->created_at?->toDateTimeString(),
            'updated_at_full' => $this->updated_at?->toDateTimeString(),
            'roles' => $this->relationLoaded('roles')
                ? $this->roles->pluck('name')->values()
                : [],
        ];
    }
}
