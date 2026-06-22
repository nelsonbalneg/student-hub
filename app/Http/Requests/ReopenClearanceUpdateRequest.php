<?php

namespace App\Http\Requests;

use App\Models\ClearanceUpdate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReopenClearanceUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('reopen', $this->route('update'));
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'status' => [
                'required',
                Rule::in([
                    ClearanceUpdate::STATUS_PUBLISHED,
                    ClearanceUpdate::STATUS_DRAFT,
                ]),
            ],
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ];
    }
}
