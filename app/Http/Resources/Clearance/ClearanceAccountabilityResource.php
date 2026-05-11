<?php

namespace App\Http\Resources\Clearance;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClearanceAccountabilityResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'amount' => $this->amount,
            'status' => $this->status,
            'student' => [
                'id' => $this->student->id,
                'name' => $this->student->name,
                'student_no' => $this->student->student_no,
            ],
            'office' => [
                'id' => $this->office->id,
                'name' => $this->office->name,
            ],
            'uploader' => [
                'id' => $this->uploader->id,
                'name' => $this->uploader->name,
            ],
            'resolver' => $this->when($this->resolver, fn() => [
                'id' => $this->resolver->id,
                'name' => $this->resolver->name,
            ]),
            'resolved_at' => $this->resolved_at?->format('Y-m-d H:i:s'),
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
        ];
    }
}
