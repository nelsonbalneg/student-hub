<?php

namespace App\Models;

use App\Services\CcdCaresEvaluationAnalysisService;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'ccd_cares_evaluation_period_id',
    'evaluation_template_id',
    'student_id',
    'answers_json',
    'submitted_at',
])]
class CcdCaresEvaluationSubmission extends Model
{
    protected function casts(): array
    {
        return [
            'ccd_cares_evaluation_period_id' => 'integer',
            'evaluation_template_id' => 'integer',
            'student_id' => 'integer',
            'answers_json' => 'array',
            'submitted_at' => 'datetime',
        ];
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(CcdCaresEvaluationPeriod::class, 'ccd_cares_evaluation_period_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(EvaluationTemplate::class, 'evaluation_template_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function getInterpretationResults(): array
    {
        if (! $this->template) {
            return [];
        }

        $analysis = app(CcdCaresEvaluationAnalysisService::class);

        return $analysis->interpretations(
            $this->answers_json ?? [],
            $analysis->prepareTemplate($this->template),
        );
    }
}
