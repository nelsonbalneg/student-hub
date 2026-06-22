<?php

namespace App\Http\Requests;

use App\Models\ClearanceUpdate;
use App\Models\SiteCampus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SyncClearanceUpdateOfficesRequest extends FormRequest
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
        /** @var ClearanceUpdate $update */
        $update = $this->route('update');
        $siteCampusId = SiteCampus::query()
            ->where('real_campus_id', (string) $update->semester->campus_id)
            ->value('id')
            ?? SiteCampus::query()->whereKey($update->semester->campus_id)->value('id');

        return [
            'office_ids' => ['required', 'array', 'min:1'],
            'office_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('offices', 'id')->where(
                    fn ($query) => $query->where('campus_id', $siteCampusId),
                ),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'office_ids.required' => 'Select at least one participating office.',
            'office_ids.min' => 'Select at least one participating office.',
            'office_ids.*.exists' => 'One of the selected offices is not available for this campus.',
        ];
    }
}
