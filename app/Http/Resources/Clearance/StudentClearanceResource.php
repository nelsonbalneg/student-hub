<?php

namespace App\Http\Resources\Clearance;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudentClearanceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference_no' => $this->reference_no,
            'status' => $this->status,
            'applied_at' => $this->applied_at?->format('Y-m-d H:i:s'),
            'cleared_at' => $this->cleared_at?->format('Y-m-d H:i:s'),
            'student' => [
                'id' => $this->student->id,
                'name' => $this->student->name,
                'student_id' => $this->student->student_no,
            ],
            'student_id' => $this->student->id,
        ];
    }
}
