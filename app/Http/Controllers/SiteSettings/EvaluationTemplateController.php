<?php

namespace App\Http\Controllers\SiteSettings;

use App\Http\Controllers\Controller;
use App\Models\CcdCaresEvaluationPeriod;
use App\Models\EvaluationChoice;
use App\Models\EvaluationInterpretationRange;
use App\Models\EvaluationRatingScale;
use App\Models\EvaluationScaleSet;
use App\Models\EvaluationStatement;
use App\Models\EvaluationStatementCategory;
use App\Models\EvaluationTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class EvaluationTemplateController extends Controller
{
    private const STATEMENT_TYPES = [
        'likert',
        'short_answer',
        'long_answer',
        'yes_no',
        'multiple_choice',
        'checkbox',
        'rating_scale',
        'numeric_score',
        'text_answer',
    ];

    public function index(Request $request): Response
    {
        $request->user()->can('evaluation.templates.view') || abort(403);

        $templates = EvaluationTemplate::query()
            ->withCount(['categories', 'statements'])
            ->with(['creator:id,name', 'updater:id,name'])
            ->latest()
            ->get();

        $selectedId = $request->integer('template') ?: $templates->first()?->id;
        $selectedTemplate = $selectedId
            ? $this->templateTree(EvaluationTemplate::query()->findOrFail($selectedId))
            : null;

        return Inertia::render('SiteSettings/Evaluation/Index', [
            'templates' => $templates->map(fn (EvaluationTemplate $template): array => [
                ...$template->toArray(),
                'can_delete' => ! $this->templateIsUsed($template),
            ]),
            'selectedTemplate' => $selectedTemplate,
            'statementTypes' => collect(self::STATEMENT_TYPES)
                ->reject(fn (string $type): bool => $type === 'text_answer')
                ->map(fn (string $type): array => [
                    'value' => $type,
                    'label' => $type === 'likert'
                        ? 'Likert Scale'
                        : str($type)->replace('_', ' ')->title()->toString(),
                ])->values(),
            'can' => [
                'create' => $request->user()->can('evaluation.templates.create'),
                'update' => $request->user()->can('evaluation.templates.update'),
                'delete' => $request->user()->can('evaluation.templates.delete'),
            ],
        ]);
    }

    public function storeTemplate(Request $request): RedirectResponse
    {
        $request->user()->can('evaluation.templates.create') || abort(403);
        $validated = $this->validateTemplate($request);

        $template = DB::transaction(fn (): EvaluationTemplate => EvaluationTemplate::query()->create([
            ...$validated,
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]));

        return to_route('site-settings.evaluation.index', ['template' => $template->id])
            ->with('success', 'Evaluation template created.');
    }

    public function updateTemplate(Request $request, EvaluationTemplate $template): RedirectResponse
    {
        $request->user()->can('evaluation.templates.update') || abort(403);

        DB::transaction(fn () => $template->update([
            ...$this->validateTemplate($request),
            'updated_by' => $request->user()->id,
        ]));

        return $this->redirectToTemplate($template->id, 'Evaluation template updated.');
    }

    public function cloneTemplate(Request $request, EvaluationTemplate $template): RedirectResponse
    {
        $request->user()->can('evaluation.templates.create') || abort(403);

        $clone = DB::transaction(function () use ($request, $template): EvaluationTemplate {
            $template->load(['categories', 'statements.choices', 'scaleSets.ratingScales']);

            $clone = EvaluationTemplate::query()->create([
                'name' => $template->name.' Copy',
                'description' => $template->description,
                'status' => 'inactive',
                'created_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
            ]);

            $categoryIds = [];
            $scaleSetIds = [];

            foreach ($template->scaleSets as $scaleSet) {
                $newScaleSet = $clone->scaleSets()->create(
                    $scaleSet->only(['name', 'description', 'is_default', 'status', 'sort_order'])
                );
                $scaleSetIds[$scaleSet->id] = $newScaleSet->id;
                $newScaleSet->ratingScales()->createMany(
                    $scaleSet->ratingScales->map(fn (EvaluationRatingScale $scale): array => [
                        'template_id' => $clone->id,
                        ...$scale->only(['value', 'label', 'interpretation', 'sort_order', 'status']),
                    ])->all()
                );
            }

            foreach ($template->categories as $category) {
                $categoryIds[$category->id] = $clone->categories()->create([
                    'scale_set_id' => $category->scale_set_id ? $scaleSetIds[$category->scale_set_id] : null,
                    'name' => $category->name,
                    'description' => $category->description,
                    'sort_order' => $category->sort_order,
                    'status' => $category->status,
                ])->id;
            }

            foreach ($template->statements as $statement) {
                $newStatement = $clone->statements()->create([
                    'category_id' => $statement->category_id ? $categoryIds[$statement->category_id] : null,
                    'scale_set_id' => $statement->scale_set_id ? $scaleSetIds[$statement->scale_set_id] : null,
                    'statement' => $statement->statement,
                    'help_text' => $statement->help_text,
                    'statement_type' => $statement->statement_type,
                    'is_required' => $statement->is_required,
                    'weight' => $statement->weight,
                    'is_visible' => $statement->is_visible,
                    'scoring_enabled' => $statement->scoring_enabled,
                    'is_read_only' => $statement->is_read_only,
                    'settings_json' => $statement->settings_json,
                    'sort_order' => $statement->sort_order,
                    'status' => $statement->status,
                ]);

                $newStatement->choices()->createMany(
                    $statement->choices->map->only(['choice_text', 'choice_value', 'score_value', 'sort_order'])->all()
                );

            }

            return $clone;
        });

        return to_route('site-settings.evaluation.index', ['template' => $clone->id])
            ->with('success', 'Evaluation template cloned.');
    }

    public function destroyTemplate(Request $request, EvaluationTemplate $template): RedirectResponse
    {
        $request->user()->can('evaluation.templates.delete') || abort(403);

        abort_if($this->templateIsUsed($template), 422, 'Used evaluation templates cannot be deleted.');
        DB::transaction(fn () => $template->delete());

        return to_route('site-settings.evaluation.index')->with('success', 'Evaluation template deleted.');
    }

    public function storeCategory(Request $request): RedirectResponse
    {
        $request->user()->can('evaluation.templates.create') || abort(403);
        $category = DB::transaction(
            fn (): EvaluationStatementCategory => EvaluationStatementCategory::query()->create($this->validateCategory($request))
        );

        return $this->redirectToTemplate($category->template_id, 'Statement category created.');
    }

    public function updateCategory(Request $request, EvaluationStatementCategory $category): RedirectResponse
    {
        $request->user()->can('evaluation.templates.update') || abort(403);
        DB::transaction(fn () => $category->update($this->validateCategory($request, $category)));

        return $this->redirectToTemplate($category->template_id, 'Statement category updated.');
    }

    public function destroyCategory(Request $request, EvaluationStatementCategory $category): RedirectResponse
    {
        $request->user()->can('evaluation.templates.delete') || abort(403);
        DB::transaction(function () use ($category): void {
            $category->statements()->update(['category_id' => null]);
            $category->delete();
        });

        return $this->redirectToTemplate($category->template_id, 'Statement category deleted.');
    }

    public function reorderCategories(Request $request): RedirectResponse
    {
        $request->user()->can('evaluation.templates.update') || abort(403);
        $validated = $request->validate([
            'template_id' => ['required', 'exists:evaluation_templates,id'],
            'ids' => ['required', 'array'],
            'ids.*' => ['integer', Rule::exists('evaluation_statement_categories', 'id')->where('template_id', $request->integer('template_id'))],
        ]);

        $this->reorder(EvaluationStatementCategory::query(), $validated['ids']);

        return $this->redirectToTemplate($validated['template_id']);
    }

    public function storeStatement(Request $request): RedirectResponse
    {
        $request->user()->can('evaluation.templates.create') || abort(403);
        $statement = DB::transaction(function () use ($request): EvaluationStatement {
            $validated = $this->validateStatement($request);
            $choices = in_array($validated['statement_type'], ['multiple_choice', 'checkbox', 'yes_no'], true)
                ? ($validated['choices'] ?? [])
                : [];
            unset($validated['choices']);

            $statement = EvaluationStatement::query()->create($validated);
            $statement->choices()->createMany($choices);

            return $statement;
        });

        return $this->redirectToTemplate($statement->template_id, 'Evaluation statement created.');
    }

    public function updateStatement(Request $request, EvaluationStatement $statement): RedirectResponse
    {
        $request->user()->can('evaluation.templates.update') || abort(403);
        DB::transaction(function () use ($request, $statement): void {
            $validated = $this->validateStatement($request, $statement);
            $choices = in_array($validated['statement_type'], ['multiple_choice', 'checkbox', 'yes_no'], true)
                ? ($validated['choices'] ?? [])
                : [];
            unset($validated['choices']);

            $statement->update($validated);
            $statement->choices()->delete();
            $statement->choices()->createMany($choices);
        });

        return $this->redirectToTemplate($statement->template_id, 'Evaluation statement updated.');
    }

    public function destroyStatement(Request $request, EvaluationStatement $statement): RedirectResponse
    {
        $request->user()->can('evaluation.templates.delete') || abort(403);
        DB::transaction(function () use ($statement): void {
            $statement->ratingScales()->delete();
            $statement->delete();
        });

        return $this->redirectToTemplate($statement->template_id, 'Evaluation statement deleted.');
    }

    public function reorderStatements(Request $request): RedirectResponse
    {
        $request->user()->can('evaluation.templates.update') || abort(403);
        $validated = $request->validate([
            'template_id' => ['required', 'exists:evaluation_templates,id'],
            'ids' => ['required', 'array'],
            'ids.*' => ['integer', Rule::exists('evaluation_statements', 'id')->where('template_id', $request->integer('template_id'))],
        ]);

        $this->reorder(EvaluationStatement::query(), $validated['ids']);

        return $this->redirectToTemplate($validated['template_id']);
    }

    public function storeScaleSet(Request $request): RedirectResponse
    {
        $request->user()->can('evaluation.templates.create') || abort(403);
        $validated = $this->validateScaleSet($request);

        $scaleSet = DB::transaction(function () use ($validated): EvaluationScaleSet {
            if ($validated['is_default']) {
                EvaluationScaleSet::query()
                    ->where('template_id', $validated['template_id'])
                    ->update(['is_default' => false]);
            }

            return EvaluationScaleSet::query()->create($validated);
        });

        return $this->redirectToTemplate($scaleSet->template_id, 'Rating scale set created.');
    }

    public function updateScaleSet(Request $request, EvaluationScaleSet $scaleSet): RedirectResponse
    {
        $request->user()->can('evaluation.templates.update') || abort(403);
        $validated = $this->validateScaleSet($request, $scaleSet);

        DB::transaction(function () use ($scaleSet, $validated): void {
            if ($validated['is_default']) {
                EvaluationScaleSet::query()
                    ->where('template_id', $scaleSet->template_id)
                    ->whereKeyNot($scaleSet->id)
                    ->update(['is_default' => false]);
            }

            $scaleSet->update($validated);
        });

        return $this->redirectToTemplate($scaleSet->template_id, 'Rating scale set updated.');
    }

    public function destroyScaleSet(Request $request, EvaluationScaleSet $scaleSet): RedirectResponse
    {
        $request->user()->can('evaluation.templates.delete') || abort(403);
        $templateId = $scaleSet->template_id;
        DB::transaction(function () use ($scaleSet): void {
            $scaleSet->categories()->update(['scale_set_id' => null]);
            $scaleSet->statements()->update(['scale_set_id' => null]);
            $scaleSet->ratingScales()->delete();
            $scaleSet->delete();
        });

        return $this->redirectToTemplate($templateId, 'Rating scale set deleted.');
    }

    public function storeScale(Request $request): RedirectResponse
    {
        $request->user()->can('evaluation.templates.create') || abort(403);
        $scale = DB::transaction(
            fn (): EvaluationRatingScale => EvaluationRatingScale::query()->create($this->validateScale($request))
        );

        return $this->redirectToTemplate($scale->template_id, 'Rating scale item created.');
    }

    public function updateScale(Request $request, EvaluationRatingScale $scale): RedirectResponse
    {
        $request->user()->can('evaluation.templates.update') || abort(403);
        DB::transaction(fn () => $scale->update($this->validateScale($request, $scale)));

        return $this->redirectToTemplate($scale->template_id, 'Rating scale item updated.');
    }

    public function destroyScale(Request $request, EvaluationRatingScale $scale): RedirectResponse
    {
        $request->user()->can('evaluation.templates.delete') || abort(403);
        DB::transaction(fn () => $scale->delete());

        return $this->redirectToTemplate($scale->template_id, 'Rating scale item deleted.');
    }

    public function reorderScales(Request $request): RedirectResponse
    {
        $request->user()->can('evaluation.templates.update') || abort(403);
        $templateId = $request->integer('template_id');
        $validated = $request->validate([
            'template_id' => ['required', 'exists:evaluation_templates,id'],
            'scale_set_id' => ['required', Rule::exists('evaluation_scale_sets', 'id')->where('template_id', $templateId)],
            'ids' => ['required', 'array'],
            'ids.*' => ['integer', Rule::exists('evaluation_rating_scales', 'id')->where('scale_set_id', $request->integer('scale_set_id'))],
        ]);

        $this->reorder(EvaluationRatingScale::query(), $validated['ids']);

        return $this->redirectToTemplate($validated['template_id']);
    }

    public function storeChoice(Request $request): RedirectResponse
    {
        $request->user()->can('evaluation.templates.create') || abort(403);
        $choice = DB::transaction(
            fn (): EvaluationChoice => EvaluationChoice::query()->create($this->validateChoice($request))
        );

        return $this->redirectToTemplate(
            $choice->statement()->valueOrFail('template_id'),
            'Multiple-choice option created.'
        );
    }

    public function updateChoice(Request $request, EvaluationChoice $choice): RedirectResponse
    {
        $request->user()->can('evaluation.templates.update') || abort(403);
        DB::transaction(fn () => $choice->update($this->validateChoice($request, $choice)));

        return $this->redirectToTemplate(
            $choice->statement()->valueOrFail('template_id'),
            'Multiple-choice option updated.'
        );
    }

    public function destroyChoice(Request $request, EvaluationChoice $choice): RedirectResponse
    {
        $request->user()->can('evaluation.templates.delete') || abort(403);
        $templateId = $choice->statement()->valueOrFail('template_id');
        DB::transaction(fn () => $choice->delete());

        return $this->redirectToTemplate($templateId, 'Multiple-choice option deleted.');
    }

    public function reorderChoices(Request $request): RedirectResponse
    {
        $request->user()->can('evaluation.templates.update') || abort(403);
        $validated = $request->validate([
            'statement_id' => ['required', 'exists:evaluation_statements,id'],
            'ids' => ['required', 'array'],
            'ids.*' => ['integer', Rule::exists('evaluation_choices', 'id')->where('statement_id', $request->integer('statement_id'))],
        ]);
        $templateId = EvaluationStatement::query()
            ->whereKey($validated['statement_id'])
            ->valueOrFail('template_id');

        $this->reorder(EvaluationChoice::query(), $validated['ids']);

        return $this->redirectToTemplate($templateId);
    }

    private function validateTemplate(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);
    }

    private function validateCategory(Request $request, ?EvaluationStatementCategory $category = null): array
    {
        $templateId = $request->integer('template_id');

        return $request->validate([
            'template_id' => ['required', 'exists:evaluation_templates,id'],
            'scale_set_id' => ['nullable', Rule::exists('evaluation_scale_sets', 'id')->where('template_id', $templateId)],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);
    }

    private function validateStatement(Request $request, ?EvaluationStatement $statement = null): array
    {
        $templateId = $request->integer('template_id');

        return $request->validate([
            'template_id' => ['required', 'exists:evaluation_templates,id'],
            'category_id' => ['nullable', Rule::exists('evaluation_statement_categories', 'id')->where('template_id', $templateId)],
            'scale_set_id' => ['nullable', Rule::exists('evaluation_scale_sets', 'id')->where('template_id', $templateId)],
            'statement' => ['required', 'string'],
            'help_text' => ['nullable', 'string'],
            'statement_type' => ['required', Rule::in(self::STATEMENT_TYPES)],
            'is_required' => ['required', 'boolean'],
            'weight' => ['nullable', 'numeric', 'min:0'],
            'is_visible' => ['required', 'boolean'],
            'scoring_enabled' => ['required', 'boolean'],
            'is_read_only' => ['required', 'boolean'],
            'settings_json' => ['nullable', 'array'],
            'settings_json.likert_preview_type' => ['nullable', Rule::in(['slider', 'stars', 'choices'])],
            'settings_json.min_value' => ['nullable', 'numeric'],
            'settings_json.max_value' => ['nullable', 'numeric', 'gte:settings_json.min_value'],
            'settings_json.placeholder' => ['nullable', 'string', 'max:255'],
            'settings_json.default_value' => ['nullable'],
            'settings_json.remarks_required_below' => ['nullable', 'numeric'],
            'settings_json.labels' => ['nullable', 'string'],
            'settings_json.conditional_display' => ['nullable', 'string'],
            'choices' => ['nullable', 'array'],
            'choices.*.choice_text' => ['required_with:choices', 'string', 'max:255'],
            'choices.*.choice_value' => ['required_with:choices', 'string', 'max:255'],
            'choices.*.score_value' => ['nullable', 'numeric'],
            'choices.*.sort_order' => ['nullable', 'integer', 'min:0'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);
    }

    private function validateScale(Request $request, ?EvaluationRatingScale $scale = null): array
    {
        $templateId = $request->integer('template_id');

        return $request->validate([
            'template_id' => ['required', 'exists:evaluation_templates,id'],
            'scale_set_id' => ['required', Rule::exists('evaluation_scale_sets', 'id')->where('template_id', $templateId)],
            'statement_id' => ['nullable', Rule::exists('evaluation_statements', 'id')->where('template_id', $templateId)],
            'value' => ['required', 'numeric'],
            'label' => ['required', 'string', 'max:255'],
            'interpretation' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);
    }

    private function validateScaleSet(Request $request, ?EvaluationScaleSet $scaleSet = null): array
    {
        $templateId = $request->integer('template_id');

        return $request->validate([
            'template_id' => ['required', 'exists:evaluation_templates,id'],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('evaluation_scale_sets', 'name')
                    ->where('template_id', $templateId)
                    ->ignore($scaleSet),
            ],
            'description' => ['nullable', 'string'],
            'is_default' => ['required', 'boolean'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);
    }

    public function storeInterpretationRange(Request $request): RedirectResponse
    {
        $request->user()->can('evaluation.templates.create') || abort(403);
        $range = DB::transaction(
            fn (): EvaluationInterpretationRange => EvaluationInterpretationRange::query()->create($this->validateInterpretationRange($request))
        );

        return $this->redirectToTemplate($range->template_id, 'Interpretation rule created.');
    }

    public function updateInterpretationRange(Request $request, EvaluationInterpretationRange $range): RedirectResponse
    {
        $request->user()->can('evaluation.templates.update') || abort(403);
        DB::transaction(fn () => $range->update($this->validateInterpretationRange($request, $range)));

        return $this->redirectToTemplate($range->template_id, 'Interpretation rule updated.');
    }

    public function destroyInterpretationRange(Request $request, EvaluationInterpretationRange $range): RedirectResponse
    {
        $request->user()->can('evaluation.templates.delete') || abort(403);
        $templateId = $range->template_id;
        DB::transaction(fn () => $range->delete());

        return $this->redirectToTemplate($templateId, 'Interpretation rule deleted.');
    }

    private function validateInterpretationRange(Request $request, ?EvaluationInterpretationRange $range = null): array
    {
        $templateId = $request->integer('template_id');

        return $request->validate([
            'template_id' => ['required', 'exists:evaluation_templates,id'],
            'category_id' => ['required', Rule::exists('evaluation_statement_categories', 'id')->where('template_id', $templateId)],
            'min_value' => ['required', 'numeric', 'min:0'],
            'max_value' => ['required', 'numeric', 'gte:min_value'],
            'interpretation' => ['required', 'string', 'max:255'],
            'suggested_intervention' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);
    }

    private function validateChoice(Request $request, ?EvaluationChoice $choice = null): array
    {
        return $request->validate([
            'statement_id' => ['required', 'exists:evaluation_statements,id'],
            'choice_text' => ['required', 'string', 'max:255'],
            'choice_value' => ['required', 'string', 'max:255'],
            'score_value' => ['nullable', 'numeric'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);
    }

    private function templateTree(EvaluationTemplate $template): array
    {
        $data = $template->load([
            'categories' => fn ($query) => $query->with('scaleSet')->orderBy('sort_order')->orderBy('id'),
            'statements' => fn ($query) => $query->with([
                'choices' => fn ($choiceQuery) => $choiceQuery->orderBy('sort_order')->orderBy('id'),
                'scaleSet.ratingScales' => fn ($scaleQuery) => $scaleQuery->orderBy('sort_order')->orderBy('id'),
            ])->orderBy('sort_order')->orderBy('id'),
            'scaleSets' => fn ($query) => $query->with([
                'ratingScales' => fn ($scaleQuery) => $scaleQuery->orderBy('sort_order')->orderBy('id'),
            ])->orderBy('sort_order')->orderBy('id'),
            'interpretationRanges' => fn ($query) => $query->orderBy('sort_order')->orderBy('id'),
        ])->toArray();

        return [
            ...$data,
            'can_delete' => ! $this->templateIsUsed($template),
        ];
    }

    private function templateIsUsed(EvaluationTemplate $template): bool
    {
        if (CcdCaresEvaluationPeriod::query()->where('evaluation_template_id', $template->id)->exists()) {
            return true;
        }

        if (! Schema::hasTable('evaluation_responses') || ! Schema::hasColumn('evaluation_responses', 'template_id')) {
            return false;
        }

        return DB::table('evaluation_responses')->where('template_id', $template->id)->exists();
    }

    private function reorder($query, array $ids): void
    {
        DB::transaction(function () use ($query, $ids): void {
            foreach ($ids as $index => $id) {
                (clone $query)->whereKey($id)->update(['sort_order' => $index + 1]);
            }
        });
    }

    private function redirectToTemplate(int $templateId, ?string $message = null): RedirectResponse
    {
        $response = to_route('site-settings.evaluation.index', ['template' => $templateId]);

        return $message ? $response->with('success', $message) : $response;
    }
}
