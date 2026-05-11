<?php

namespace App\Http\Resources\UserManagement;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'guard_name' => $this->guard_name,
            'users_count' => $this->users_count ?? 0,
            'permissions_count' => $this->permissions_count ?? $this->permissions?->count() ?? 0,
            'created_at' => $this->created_at?->format('M d, Y'),
            'permissions' => $this->relationLoaded('permissions')
                ? $this->permissions->pluck('name')->values()
                : [],
        ];
    }
}
