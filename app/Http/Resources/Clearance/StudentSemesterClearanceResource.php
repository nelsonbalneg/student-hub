<?php

namespace App\Http\Resources\Clearance;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudentSemesterClearanceResource extends JsonResource
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
            'reference_no' => $this->reference_no,
            'status' => $this->status,
            'applied_at' => $this->applied_at?->format('Y-m-d H:i:s'),
            'cleared_at' => $this->cleared_at?->format('Y-m-d H:i:s'),
            'completed_at' => $this->completed_at?->format('Y-m-d H:i:s'),
            'remarks' => $this->remarks,
            'clearance_update' => [
                'id' => $this->clearanceUpdate->id,
                'reference_code' => $this->clearanceUpdate->reference_code,
                'title' => $this->clearanceUpdate->title,
                'type' => [
                    'id' => $this->clearanceUpdate->type->id,
                    'name' => $this->clearanceUpdate->type->name,
                ],
                'accountabilities' => $this->when($this->relationLoaded('clearanceUpdate') && $this->clearanceUpdate->relationLoaded('accountabilities'), function () {
                    return ClearanceAccountabilityResource::collection($this->clearanceUpdate->accountabilities);
                }),
                'offices' => $this->when($this->relationLoaded('clearanceUpdate') && $this->clearanceUpdate->relationLoaded('offices'), function () {
                    return $this->clearanceUpdate->offices->map(fn ($o) => [
                        'id' => $o->id,
                        'office' => [
                            'id' => $o->office->id,
                            'name' => $o->office->name,
                        ],
                        'finalized_at' => $o->finalized_at?->format('Y-m-d H:i:s'),
                    ]);
                }),
            ],
            'semester' => [
                'id' => $this->semester->id,
                'academic_year' => $this->semester->academic_year,
                'term' => $this->semester->term,
            ],
            'logs' => ClearanceLogResource::collection($this->whenLoaded('logs')),
        ];
    }
}
