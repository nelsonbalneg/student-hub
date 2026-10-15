<?php

namespace App\Http\Requests;

use App\Models\SarAccessRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSarAccessRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('site_settings.manage') ?? false;
    }

    public function rules(): array
    {
        $rule = $this->route('rule');

        return [
            'site_campus_id' => ['required', 'integer', 'exists:site_campuses,id'],
            'site_academic_term_id' => ['required', 'integer', Rule::exists('site_academic_terms', 'id')->where('site_campus_id', $this->input('site_campus_id'))->whereNotNull('term_id'), Rule::when($rule instanceof SarAccessRule, fn () => Rule::unique('sar_access_rules', 'site_academic_term_id')->ignore($rule))],
            'is_enabled' => ['required', 'boolean'],
        ];
    }
}
