<?php

namespace App\Http\Resources\UserManagement;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class PermissionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'label' => Str::headline($this->name),
            'module' => $this->module ?? Str::before($this->name, '.'),
            'guard_name' => $this->guard_name,
            'roles_count' => $this->roles_count ?? 0,
            'created_at' => $this->created_at?->format('M d, Y'),
        ];
    }
}
