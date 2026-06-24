<?php

use App\Models\EvaluationChoice;
use App\Models\EvaluationInterpretationRange;
use App\Models\EvaluationRatingScale;
use App\Models\EvaluationScaleSet;
use App\Models\EvaluationScoringRule;
use App\Models\EvaluationStatement;
use App\Models\EvaluationStatementCategory;
use App\Models\EvaluationTemplate;
use App\Models\User;

return [
    'table_prefix' => 'evaluation_',

    'models' => [
        'template' => EvaluationTemplate::class,
        'category' => EvaluationStatementCategory::class,
        'statement' => EvaluationStatement::class,
        'choice' => EvaluationChoice::class,
        'scale_set' => EvaluationScaleSet::class,
        'rating_scale' => EvaluationRatingScale::class,
        'scoring_rule' => EvaluationScoringRule::class,
        'interpretation_range' => EvaluationInterpretationRange::class,
    ],

    'user_model' => User::class,

    // StudentHub already owns the evaluation builder migrations.
    'load_migrations' => false,
];
