<?php

use App\Models\EvaluationScaleSet;
use App\Models\EvaluationStatement;
use App\Models\EvaluationStatementCategory;
use App\Models\EvaluationTemplate;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

test('scale sets expose template options section and question relationships', function () {
    $scaleSet = new EvaluationScaleSet;

    expect($scaleSet->template())->toBeInstanceOf(BelongsTo::class)
        ->and($scaleSet->ratingScales())->toBeInstanceOf(HasMany::class)
        ->and($scaleSet->categories())->toBeInstanceOf(HasMany::class)
        ->and($scaleSet->statements())->toBeInstanceOf(HasMany::class)
        ->and((new EvaluationTemplate)->scaleSets())->toBeInstanceOf(HasMany::class)
        ->and((new EvaluationStatementCategory)->scaleSet())->toBeInstanceOf(BelongsTo::class)
        ->and((new EvaluationStatement)->scaleSet())->toBeInstanceOf(BelongsTo::class);
});
