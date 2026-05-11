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
            'user_type' => $this->user_type,
            'office' => $this->office,
            'office_id' => $this->office_id,
            'office_details' => $this->whenLoaded('office', fn() => [
                'id' => $this->office->id,
                'name' => $this->office->name,
                'code' => $this->office->code,
            ]),
            'department' => $this->department,
            'status' => $this->is_active ? 'Active' : 'Inactive',
            'is_active' => $this->is_active,
            'created_at' => $this->created_at?->format('M d, Y'),
            'roles' => $this->relationLoaded('roles')
                ? $this->roles->pluck('name')->values()
                : [],
        ];
    }
}
