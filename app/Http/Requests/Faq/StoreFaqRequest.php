<?php

namespace App\Http\Requests\Faq;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreFaqRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'faq_category_id' => 'required|exists:faq_categories,id',
            'question' => 'required|string|max:255',
            'answer' => 'required|string',
            'summary' => 'nullable|string|max:500',
            'tags' => 'nullable|array',
            'keywords' => 'nullable|array',
            'is_featured' => 'required|boolean',
            'status' => 'required|string|in:draft,published,archived',
            'visibility' => 'required|string|in:public,students,employees,admin',
            'sort_order' => 'nullable|integer',
        ];
    }
}
