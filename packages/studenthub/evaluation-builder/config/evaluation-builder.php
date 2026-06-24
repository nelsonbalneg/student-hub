<?php

use StudentHub\EvaluationBuilder\Models\Choice;
use StudentHub\EvaluationBuilder\Models\InterpretationRange;
use StudentHub\EvaluationBuilder\Models\RatingScale;
use StudentHub\EvaluationBuilder\Models\ScaleSet;
use StudentHub\EvaluationBuilder\Models\ScoringRule;
use StudentHub\EvaluationBuilder\Models\Statement;
use StudentHub\EvaluationBuilder\Models\StatementCategory;
use StudentHub\EvaluationBuilder\Models\Template;

return [
    'table_prefix' => 'evaluation_',

    'models' => [
        'template' => Template::class,
        'category' => StatementCategory::class,
        'statement' => Statement::class,
        'choice' => Choice::class,
        'scale_set' => ScaleSet::class,
        'rating_scale' => RatingScale::class,
        'scoring_rule' => ScoringRule::class,
        'interpretation_range' => InterpretationRange::class,
    ],

    'user_model' => null,
    'load_migrations' => true,
];
