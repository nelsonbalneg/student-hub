<?php

use App\Models\EvaluationStatement;
use App\Services\EvaluationTemplatePayloadService;
use Illuminate\Support\Collection;

function payloadForStatement(string $type): array
{
    $statement = new EvaluationStatement;
    $statement->forceFill([
        'id' => 10,
        'statement' => 'Example question',
        'statement_type' => $type,
        'is_required' => false,
        'settings_json' => ['min_value' => 1, 'max_value' => 5],
    ]);
    $statement->setRelation('choices', collect());

    $scaleSet = new class
    {
        public Collection $ratingScales;

        public function __construct()
        {
            $this->ratingScales = collect([
                (object) ['id' => 1, 'label' => 'Strongly Disagree', 'value' => 1],
                (object) ['id' => 2, 'label' => 'Strongly Agree', 'value' => 5],
            ]);
        }
    };

    $method = new ReflectionMethod(EvaluationTemplatePayloadService::class, 'statement');

    return $method->invoke(new EvaluationTemplatePayloadService, $statement, $scaleSet);
}

test('long answer questions do not inherit template rating scales', function () {
    expect(payloadForStatement('long_answer')['scale_options'])->toBeEmpty();
});

test('rating scale questions still receive configured scale options', function () {
    expect(payloadForStatement('rating_scale')['scale_options'])
        ->toHaveCount(2)
        ->and(payloadForStatement('rating_scale')['scale_options'][0]['label'])
        ->toBe('Strongly Disagree');
});
