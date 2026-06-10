<?php

namespace App\Http\Controllers;

use App\Models\PftTestType;
use App\Models\SiteAcademicTerm;
use App\Models\StudentPftResult;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;

class StudentPftResultController extends Controller
{
    public function store(Request $request, PftTestType $testType): RedirectResponse
    {
        $request->user()->can('pft.submit') || abort(403);

        $testType->load([
            'category.component',
            'configurations' => fn ($query) => $query->active()->orderBy('sort_order'),
        ]);

        abort_unless($testType->is_active && $testType->category?->is_active !== false, 404);

        $rules = [
            'term_id' => ['required', 'string'],
            'remarks' => ['nullable', 'string'],
            'tested_at' => ['nullable', 'date'],
            'results' => ['required', 'array'],
        ];

        foreach ($testType->configurations as $configuration) {
            $fieldRules = $configuration->is_required ? ['required'] : ['nullable'];
            $fieldRules[] = match ($configuration->field_type) {
                'number' => 'integer',
                'decimal' => 'numeric',
                'date' => 'date',
                'checkbox' => 'boolean',
                default => 'string',
            };
            $rules["results.{$configuration->field_name}"] = $fieldRules;
        }

        $validated = $request->validate($rules);
        $termId = (string) $validated['term_id'];
        $activeTermExists = SiteAcademicTerm::query()
            ->where('term_id', $termId)
            ->where('status', 'Active')
            ->whereHas('campus', fn ($query) => $query->where('real_campus_id', (string) $request->user()->campus_id))
            ->exists();

        if (! $activeTermExists) {
            throw ValidationException::withMessages([
                'term_id' => 'The selected academic term is not active for your campus.',
            ]);
        }

        $allowedFields = $testType->configurations->pluck('field_name')->all();
        $results = collect($validated['results'])
            ->only($allowedFields)
            ->filter(fn ($value): bool => $value !== null && $value !== '')
            ->all();

        if ($testType->slug === 'bmi-test') {
            $height = isset($results['height']) ? floatval($results['height']) : null;
            $weight = isset($results['weight']) ? floatval($results['weight']) : null;
            if ($height > 0 && $weight > 0) {
                $heightInMeters = $height / 100;
                $bmiValue = round($weight / ($heightInMeters * $heightInMeters), 2);
                $results['bmi'] = $bmiValue;

                if ($bmiValue < 18.5) {
                    $interpretation = 'Underweight';
                } elseif ($bmiValue < 25.0) {
                    $interpretation = 'Normal';
                } elseif ($bmiValue < 30.0) {
                    $interpretation = 'Overweight';
                } else {
                    $interpretation = 'Obese';
                }

                $validated['remarks'] = $interpretation;
                $results['remarks'] = $interpretation;
            }
        }

        DB::transaction(function () use ($request, $testType, $validated, $results, $termId): void {
            StudentPftResult::query()->updateOrCreate(
                [
                    'user_id' => $request->user()->id,
                    'pft_test_type_id' => $testType->id,
                    'term_id' => $termId,
                ],
                [
                    'results_json' => $results,
                    'remarks' => $validated['remarks'] ?? ($results['remarks'] ?? null),
                    'tested_at' => $validated['tested_at'] ?? ($results['date_tested'] ?? null),
                    'created_by' => $request->user()->id,
                    'updated_by' => $request->user()->id,
                ],
            );
        });

        return to_route('student-profile.index')
            ->with('success', 'Physical fitness result saved.');
    }
}
