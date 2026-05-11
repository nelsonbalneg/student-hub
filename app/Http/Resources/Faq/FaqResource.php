<?php

namespace App\Http\Resources\Faq;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FaqResource extends JsonResource
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
            'faq_category_id' => $this->faq_category_id,
            'category' => new FaqCategoryResource($this->whenLoaded('category')),
            'question' => $this->question,
            'answer' => $this->answer,
            'summary' => $this->summary,
            'tags' => $this->tags,
            'keywords' => $this->keywords,
            'is_featured' => (bool) $this->is_featured,
            'status' => $this->status,
            'visibility' => $this->visibility,
            'sort_order' => $this->sort_order,
            'view_count' => $this->view_count,
            'helpful_count' => $this->helpful_count,
            'not_helpful_count' => $this->not_helpful_count,
            'published_at' => $this->published_at?->format('Y-m-d H:i:s'),
            'creator' => [
                'id' => $this->creator?->id,
                'name' => $this->creator?->name,
            ],
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
