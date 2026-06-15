<?php

namespace Database\Seeders;

use App\Models\PftCategory;
use App\Models\PftComponent;
use App\Models\PftConfiguration;
use App\Models\PftInterpretationRule;
use App\Models\PftTestType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PhysicalFitnessTestSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            foreach ($this->defaults() as $componentIndex => $componentData) {
                $component = PftComponent::query()->updateOrCreate(
                    ['slug' => Str::slug($componentData['name'])],
                    [
                        'name' => $componentData['name'],
                        'description' => $componentData['description'] ?? null,
                        'sort_order' => $componentIndex + 1,
                        'is_active' => true,
                    ],
                );

                foreach ($componentData['categories'] as $categoryIndex => $categoryData) {
                    $category = PftCategory::query()->updateOrCreate(
                        [
                            'pft_component_id' => $component->id,
                            'slug' => Str::slug($categoryData['name']),
                        ],
                        [
                            'name' => $categoryData['name'],
                            'description' => $categoryData['description'] ?? null,
                            'sort_order' => $categoryIndex + 1,
                            'is_active' => true,
                        ],
                    );

                    foreach ($categoryData['tests'] as $testIndex => $testName) {
                        $testType = PftTestType::query()->updateOrCreate(
                            [
                                'pft_category_id' => $category->id,
                                'slug' => Str::slug($testName),
                            ],
                            [
                                'name' => $testName,
                                'description' => null,
                                'unit' => null,
                                'sort_order' => $testIndex + 1,
                                'is_active' => true,
                            ],
                        );

                        foreach ($this->defaultFieldsFor($testName) as $fieldIndex => $field) {
                            PftConfiguration::query()->updateOrCreate(
                                [
                                    'pft_test_type_id' => $testType->id,
                                    'field_name' => $field['field_name'],
                                ],
                                [
                                    ...$field,
                                    'sort_order' => $fieldIndex + 1,
                                    'is_active' => true,
                                ],
                            );
                        }

                        foreach ($this->defaultInterpretationRulesFor($testName) as $ruleIndex => $rule) {
                            PftInterpretationRule::query()->updateOrCreate(
                                [
                                    'pft_test_type_id' => $testType->id,
                                    'field_name' => $rule['field_name'],
                                    'label' => $rule['label'],
                                ],
                                [
                                    ...$rule,
                                    'sort_order' => ($ruleIndex + 1) * 10,
                                    'is_active' => true,
                                ],
                            );
                        }
                    }
                }
            }
        });
    }

    private function defaults(): array
    {
        return [
            [
                'name' => 'Health Related',
                'description' => 'Physical fitness tests measuring health-related fitness components.',
                'categories' => [
                    ['name' => 'Body Composition', 'tests' => ['BMI Test']],
                    ['name' => 'Flexibility', 'tests' => ['Zipper Test', 'Sit and Reach Test']],
                    ['name' => 'Cardiovascular Endurance', 'tests' => ['3-Minute Step Test']],
                    ['name' => 'Muscular Strength', 'tests' => ['Push-Up Test']],
                    ['name' => 'Muscular Endurance', 'tests' => ['Curl-Up Test']],
                ],
            ],
            [
                'name' => 'Skill Related',
                'description' => 'Physical fitness tests measuring skill-related fitness components.',
                'categories' => [
                    ['name' => 'Agility', 'tests' => ['Shuttle Run']],
                    ['name' => 'Speed', 'tests' => ['40-Meter Sprint']],
                    ['name' => 'Power', 'tests' => ['Standing Long Jump']],
                    ['name' => 'Balance', 'tests' => ['Stork Balance Stand Test']],
                    ['name' => 'Coordination', 'tests' => ['Juggling Test']],
                    ['name' => 'Reaction Time', 'tests' => ['Stick Drop Test']],
                ],
            ],
        ];
    }

    private function defaultFieldsFor(string $testName): array
    {
        return match ($testName) {
            'BMI Test' => [
                $this->field('height', 'Height (cm)', 'decimal', true, 'Enter height in centimeters.'),
                $this->field('weight', 'Weight (kg)', 'decimal', true, 'Enter weight in kilograms.'),
                $this->field('bmi', 'BMI', 'decimal', false, 'Auto-computed from height and weight.'),
                ...$this->commonResultFields(),
            ],
            'Zipper Test' => [
                $this->field('right_shoulder', 'Right Shoulder Reach (cm)', 'decimal', true),
                $this->field('left_shoulder', 'Left Shoulder Reach (cm)', 'decimal', true),
                $this->field('score', 'Best Reach (cm)', 'decimal', false),
                ...$this->commonResultFields(),
            ],
            'Sit and Reach Test' => [
                $this->field('trial_1', 'Trial 1 (cm)', 'decimal', true),
                $this->field('trial_2', 'Trial 2 (cm)', 'decimal', true),
                $this->field('trial_3', 'Trial 3 (cm)', 'decimal', false),
                $this->field('score', 'Best Reach (cm)', 'decimal', false),
                ...$this->commonResultFields(),
            ],
            '3-Minute Step Test' => [
                $this->field('heart_rate', 'Recovery Heart Rate (bpm)', 'number', true, 'Pulse count after the step test.'),
                $this->field('score', 'Step Test Score', 'decimal', false),
                ...$this->commonResultFields(),
            ],
            'Push-Up Test' => [
                $this->field('repetitions', 'Push-Ups Completed', 'number', true),
                $this->field('score', 'Score', 'number', false),
                ...$this->commonResultFields(),
            ],
            'Curl-Up Test' => [
                $this->field('repetitions', 'Curl-Ups Completed', 'number', true),
                $this->field('score', 'Score', 'number', false),
                ...$this->commonResultFields(),
            ],
            'Shuttle Run' => [
                $this->field('time_seconds', 'Time (seconds)', 'decimal', true),
                $this->field('score', 'Score', 'decimal', false),
                ...$this->commonResultFields(),
            ],
            '40-Meter Sprint' => [
                $this->field('time_seconds', 'Time (seconds)', 'decimal', true),
                $this->field('score', 'Score', 'decimal', false),
                ...$this->commonResultFields(),
            ],
            'Standing Long Jump' => [
                $this->field('distance_cm', 'Distance (cm)', 'decimal', true),
                $this->field('score', 'Score', 'decimal', false),
                ...$this->commonResultFields(),
            ],
            'Stork Balance Stand Test' => [
                $this->field('time_seconds', 'Balance Time (seconds)', 'decimal', true),
                $this->field('score', 'Score', 'decimal', false),
                ...$this->commonResultFields(),
            ],
            'Juggling Test' => [
                $this->field('successful_juggles', 'Successful Juggles', 'number', true),
                $this->field('score', 'Score', 'number', false),
                ...$this->commonResultFields(),
            ],
            'Stick Drop Test' => [
                $this->field('distance_cm', 'Catch Distance (cm)', 'decimal', true),
                $this->field('reaction_time', 'Reaction Time (seconds)', 'decimal', false),
                $this->field('score', 'Score', 'decimal', false),
                ...$this->commonResultFields(),
            ],
            default => [
                $this->field('score', 'Score', 'decimal', true),
                ...$this->commonResultFields(),
            ],
        };
    }

    private function commonResultFields(): array
    {
        return [
            $this->field('date_tested', 'Date Tested', 'date', false),
            $this->field('remarks', 'Remarks', 'textarea', false),
        ];
    }

    private function defaultInterpretationRulesFor(string $testName): array
    {
        return match ($testName) {
            'BMI Test' => [
                $this->rule('bmi', 'Underweight', null, 18.4999, 'amber'),
                $this->rule('bmi', 'Normal', 18.5, 24.9999, 'emerald'),
                $this->rule('bmi', 'Overweight', 25, 29.9999, 'orange'),
                $this->rule('bmi', 'Obese', 30, null, 'red'),
            ],
            '3-Minute Step Test' => [
                $this->rule('heart_rate', 'Excellent', null, 79, 'emerald'),
                $this->rule('heart_rate', 'Good', 80, 89, 'lime'),
                $this->rule('heart_rate', 'Average', 90, 99, 'amber'),
                $this->rule('heart_rate', 'Needs Improvement', 100, null, 'red'),
            ],
            'Shuttle Run', '40-Meter Sprint' => [
                $this->rule('time_seconds', 'Excellent', null, 10.9999, 'emerald'),
                $this->rule('time_seconds', 'Good', 11, 12.9999, 'lime'),
                $this->rule('time_seconds', 'Average', 13, 14.9999, 'amber'),
                $this->rule('time_seconds', 'Needs Improvement', 15, null, 'red'),
            ],
            'Stork Balance Stand Test' => [
                $this->rule('time_seconds', 'Needs Improvement', null, 19.9999, 'red'),
                $this->rule('time_seconds', 'Average', 20, 39.9999, 'amber'),
                $this->rule('time_seconds', 'Good', 40, 59.9999, 'lime'),
                $this->rule('time_seconds', 'Excellent', 60, null, 'emerald'),
            ],
            'Zipper Test' => [
                $this->rule('score', 'Needs Improvement', null, -5.0001, 'red'),
                $this->rule('score', 'Average', -5, -0.0001, 'amber'),
                $this->rule('score', 'Good', 0, 4.9999, 'lime'),
                $this->rule('score', 'Excellent', 5, null, 'emerald'),
            ],
            'Stick Drop Test' => [
                $this->rule('distance_cm', 'Excellent', null, 14.9999, 'emerald'),
                $this->rule('distance_cm', 'Good', 15, 24.9999, 'lime'),
                $this->rule('distance_cm', 'Average', 25, 34.9999, 'amber'),
                $this->rule('distance_cm', 'Needs Improvement', 35, null, 'red'),
            ],
            default => [
                $this->rule('score', 'Needs Improvement', null, 19.9999, 'red'),
                $this->rule('score', 'Average', 20, 39.9999, 'amber'),
                $this->rule('score', 'Good', 40, 59.9999, 'lime'),
                $this->rule('score', 'Excellent', 60, null, 'emerald'),
            ],
        };
    }

    private function field(
        string $name,
        string $label,
        string $type,
        bool $required,
        ?string $helpText = null,
        ?string $placeholder = null,
        ?array $options = null,
    ): array
    {
        return [
            'field_name' => $name,
            'field_label' => $label,
            'field_type' => $type,
            'options' => $options,
            'placeholder' => $placeholder,
            'help_text' => $helpText,
            'is_required' => $required,
        ];
    }

    private function rule(
        string $fieldName,
        string $label,
        ?float $minValue,
        ?float $maxValue,
        string $color,
    ): array {
        return [
            'field_name' => $fieldName,
            'label' => $label,
            'min_value' => $minValue,
            'max_value' => $maxValue,
            'color' => $color,
        ];
    }
}
