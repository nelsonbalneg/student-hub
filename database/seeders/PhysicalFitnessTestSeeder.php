<?php

namespace Database\Seeders;

use App\Models\PftCategory;
use App\Models\PftComponent;
use App\Models\PftConfiguration;
use App\Models\PftInterpretationRule;
use App\Models\PftProcedure;
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

                    foreach ($categoryData['tests'] as $testIndex => $testData) {
                        $testName = is_array($testData) ? $testData['name'] : $testData;
                        $testType = PftTestType::query()->updateOrCreate(
                            [
                                'pft_category_id' => $category->id,
                                'slug' => Str::slug($testName),
                            ],
                            [
                                'name' => $testName,
                                'description' => is_array($testData) ? ($testData['description'] ?? null) : null,
                                'unit' => is_array($testData) ? ($testData['unit'] ?? null) : null,
                                'sort_order' => $testIndex + 1,
                                'is_active' => true,
                            ],
                        );

                        PftProcedure::withTrashed()
                            ->where('pft_test_type_id', $testType->id)
                            ->forceDelete();

                        $defaultProcedures = $this->defaultProcedureFor($testName) ?? [];
                        foreach ($defaultProcedures as $stepIndex => $description) {
                            PftProcedure::query()->create([
                                'pft_test_type_id' => $testType->id,
                                'step_no' => $stepIndex + 1,
                                'description' => $description,
                                'is_active' => true,
                            ]);
                        }

                        $defaultFields = $this->defaultFieldsFor($testName);

                        PftConfiguration::withTrashed()
                            ->where('pft_test_type_id', $testType->id)
                            ->whereNotIn('field_name', collect($defaultFields)->pluck('field_name')->all())
                            ->forceDelete();

                        foreach ($defaultFields as $fieldIndex => $field) {
                            $configuration = PftConfiguration::withTrashed()->updateOrCreate(
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

                            if ($configuration->trashed()) {
                                $configuration->restore();
                            }
                        }

                        PftInterpretationRule::withTrashed()
                            ->where('pft_test_type_id', $testType->id)
                            ->forceDelete();

                        foreach ($this->defaultInterpretationRulesFor($testName) as $ruleIndex => $rule) {
                            PftInterpretationRule::query()->create([
                                ...$rule,
                                'pft_test_type_id' => $testType->id,
                                'sex' => $rule['sex'] ?? null,
                                'sort_order' => ($ruleIndex + 1) * 10,
                                'is_active' => true,
                            ]);
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
                    ['name' => 'Strength', 'tests' => ['Push-Up Test', 'Curl-Up Test']],
                ],
            ],
            [
                'name' => 'Skill Related',
                'description' => 'Physical fitness tests measuring skill-related fitness components.',
                'categories' => [
                    ['name' => 'Balance', 'tests' => ['Stork Balance Stand Test']],
                    ['name' => 'Speed', 'tests' => ['50-Meter Sprint']],
                    ['name' => 'Power', 'tests' => ['Seated Medicine Ball Chest Pass Test', 'Standing Long Jump', 'Vertical Jump Test']],
                    ['name' => 'Agility', 'tests' => ['Hexagon Agility Test']],
                    ['name' => 'Reaction Time', 'tests' => ['Ruler Drop Test']],
                    ['name' => 'Coordination', 'tests' => ['Alternate Hand Wall Toss Test']],
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
                $this->field('observation', 'Fingertips Observation', 'select', true, 'Select the observed fingertip position.', null, [
                    'Fingertips overlap',
                    'Fingertips just touch',
                    'Fingertips do not touch',
                ]),
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
                $this->field('resting_heart_rate', 'Resting Heart Rate (bpm)', 'number', false),
                $this->field('post_activity_heart_rate', 'Post-activity Heart Rate (bpm)', 'number', false),
                $this->field('heart_rate', 'Recovery Heart Rate (bpm)', 'number', true, 'Recovery pulse count after the step test.'),
                ...$this->commonResultFields(),
            ],
            'Push-Up Test' => [
                $this->field('repetitions', 'Push-Ups Completed', 'number', true),
                $this->field('score', 'Score', 'number', false),
                ...$this->commonResultFields(),
            ],
            'Curl-Up Test' => [
                $this->field('repetitions', 'Curl-Ups Completed', 'number', true),
                ...$this->commonResultFields(),
            ],
            '50-Meter Sprint', 'Hexagon Agility Test' => [
                $this->field('time_seconds', 'Time (seconds)', 'decimal', true),
                ...$this->commonResultFields(),
            ],
            'Seated Medicine Ball Chest Pass Test' => [
                $this->field('distance_m', 'Distance (m)', 'decimal', true),
                ...$this->commonResultFields(),
            ],
            'Standing Long Jump' => [
                $this->field('distance_cm', 'Distance (cm)', 'decimal', true),
                ...$this->commonResultFields(),
            ],
            'Vertical Jump Test' => [
                $this->field('height_cm', 'Vertical Jump Height (cm)', 'decimal', true),
                ...$this->commonResultFields(),
            ],
            'Stork Balance Stand Test' => [
                $this->field('time_seconds', 'Balance Time (seconds)', 'decimal', true),
                ...$this->commonResultFields(),
            ],
            'Alternate Hand Wall Toss Test' => [
                $this->field('successful_catches', 'Successful Catches (30 s)', 'number', true),
                ...$this->commonResultFields(),
            ],
            'Ruler Drop Test' => [
                $this->field('distance_cm', 'Catch Distance (cm)', 'decimal', true),
                $this->field('reaction_time', 'Reaction Time (seconds)', 'decimal', false),
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
                $this->rule('bmi', 'Normal Weight', 18.5, 24.9999, 'emerald', 'Normal weight'),
                $this->rule('bmi', 'Overweight', 25, 29.9999, 'orange', 'Overweight (Pre-obese)'),
                $this->rule('bmi', 'Obese Class I', 30, 34.9999, 'red', 'Obesity Class I'),
                $this->rule('bmi', 'Obese Class II', 35, 39.9999, 'red', 'Obesity Class II'),
                $this->rule('bmi', 'Obese Class III (Severe Obesity)', 40, null, 'red', 'Obesity Class III'),
            ],
            'Zipper Test' => [
                $this->rule('observation', 'Excellent Shoulder Flexibility', null, null, 'emerald', 'Fingertips overlap'),
                $this->rule('observation', 'Satisfactory Shoulder Flexibility', null, null, 'lime', 'Fingertips just touch'),
                $this->rule('observation', 'Needs Improvement', null, null, 'red', 'Fingertips do not touch'),
            ],
            'Sit and Reach Test' => [
                ...$this->rulesForSexes('score', [
                    ['male', 'Excellent', 27, null, 'emerald'],
                    ['male', 'Very Good', 23, 26, 'lime'],
                    ['male', 'Good', 18, 22, 'blue'],
                    ['male', 'Fair', 13, 17, 'amber'],
                    ['male', 'Poor', null, 12.9999, 'red'],
                    ['female', 'Excellent', 30, null, 'emerald'],
                    ['female', 'Very Good', 26, 29, 'lime'],
                    ['female', 'Good', 21, 25, 'blue'],
                    ['female', 'Fair', 16, 20, 'amber'],
                    ['female', 'Poor', null, 15.9999, 'red'],
                ]),
            ],
            '3-Minute Step Test' => [
                ...$this->rulesForSexes('heart_rate', [
                    ['male', 'Excellent', null, 79, 'emerald'],
                    ['male', 'Good', 80, 89, 'lime'],
                    ['male', 'Above Average', 90, 99, 'blue'],
                    ['male', 'Average', 100, 105, 'amber'],
                    ['male', 'Below Average', 106, 116, 'orange'],
                    ['male', 'Poor', 117, null, 'red'],
                    ['female', 'Excellent', null, 85, 'emerald'],
                    ['female', 'Good', 86, 94, 'lime'],
                    ['female', 'Above Average', 95, 104, 'blue'],
                    ['female', 'Average', 105, 113, 'amber'],
                    ['female', 'Below Average', 114, 126, 'orange'],
                    ['female', 'Poor', 127, null, 'red'],
                ]),
            ],
            'Push-Up Test' => [
                ...$this->rulesForSexes('repetitions', [
                    ['male', 'Excellent', 47, null, 'emerald'],
                    ['male', 'Very Good', 36, 46, 'lime'],
                    ['male', 'Good', 28, 35, 'blue'],
                    ['male', 'Fair', 18, 27, 'amber'],
                    ['male', 'Poor', null, 17.9999, 'red'],
                    ['female', 'Excellent', 33, null, 'emerald'],
                    ['female', 'Very Good', 25, 32, 'lime'],
                    ['female', 'Good', 18, 24, 'blue'],
                    ['female', 'Fair', 7, 17, 'amber'],
                    ['female', 'Poor', null, 6.9999, 'red'],
                ]),
            ],
            'Curl-Up Test' => [
                ...$this->rulesForSexes('repetitions', [
                    ['male', 'Excellent', 48, null, 'emerald'],
                    ['male', 'Very Good', 40, 47, 'lime'],
                    ['male', 'Good', 31, 39, 'blue'],
                    ['male', 'Fair', 21, 30, 'amber'],
                    ['male', 'Poor', null, 20.9999, 'red'],
                    ['female', 'Excellent', 40, null, 'emerald'],
                    ['female', 'Very Good', 32, 39, 'lime'],
                    ['female', 'Good', 24, 31, 'blue'],
                    ['female', 'Fair', 12, 23, 'amber'],
                    ['female', 'Poor', null, 11.9999, 'red'],
                ]),
            ],
            'Stork Balance Stand Test' => [
                ...$this->rulesForSexes('time_seconds', [
                    ['male', 'Excellent', 50.0001, null, 'emerald'],
                    ['male', 'Above Average', 41, 50, 'lime'],
                    ['male', 'Average', 31, 40, 'amber'],
                    ['male', 'Below Average', 20, 30, 'orange'],
                    ['male', 'Poor', null, 19.9999, 'red'],
                    ['female', 'Excellent', 30.0001, null, 'emerald'],
                    ['female', 'Above Average', 23, 30, 'lime'],
                    ['female', 'Average', 16, 22, 'amber'],
                    ['female', 'Below Average', 10, 15, 'orange'],
                    ['female', 'Poor', null, 9.9999, 'red'],
                ]),
            ],
            '50-Meter Sprint' => [
                ...$this->rulesForSexes('time_seconds', [
                    ['male', 'Excellent', null, 6.1999, 'emerald'],
                    ['male', 'Very Good', 6.2, 6.69, 'lime'],
                    ['male', 'Good', 6.7, 7.29, 'blue'],
                    ['female', 'Excellent', null, 7.1999, 'emerald'],
                    ['female', 'Very Good', 7.2, 7.69, 'lime'],
                    ['female', 'Good', 7.7, 8.29, 'blue'],
                ]),
            ],
            'Seated Medicine Ball Chest Pass Test' => [
                ...$this->rulesForSexes('distance_m', [
                    ['male', 'Excellent', 6.5, null, 'emerald'],
                    ['male', 'Very Good', 5.8, 6.49, 'lime'],
                    ['male', 'Good', 5, 5.79, 'blue'],
                    ['male', 'Average', 4.2, 4.99, 'amber'],
                    ['male', 'Below Average', 3.5, 4.19, 'orange'],
                    ['male', 'Poor', null, 3.4999, 'red'],
                    ['female', 'Excellent', 5.5, null, 'emerald'],
                    ['female', 'Very Good', 4.9, 5.49, 'lime'],
                    ['female', 'Good', 4.2, 4.89, 'blue'],
                    ['female', 'Average', 3.5, 4.19, 'amber'],
                    ['female', 'Below Average', 2.8, 3.49, 'orange'],
                    ['female', 'Poor', null, 2.7999, 'red'],
                ]),
            ],
            'Standing Long Jump' => [
                ...$this->rulesForSexes('distance_cm', [
                    ['male', 'Excellent', 250, null, 'emerald'],
                    ['male', 'Very Good', 230, 249, 'lime'],
                    ['male', 'Good', 210, 229, 'blue'],
                    ['male', 'Average', 190, 209, 'amber'],
                    ['male', 'Below Average', 170, 189, 'orange'],
                    ['male', 'Poor', null, 169.9999, 'red'],
                    ['female', 'Excellent', 200, null, 'emerald'],
                    ['female', 'Very Good', 180, 199, 'lime'],
                    ['female', 'Good', 160, 179, 'blue'],
                    ['female', 'Average', 140, 159, 'amber'],
                    ['female', 'Below Average', 120, 139, 'orange'],
                    ['female', 'Poor', null, 119.9999, 'red'],
                ]),
            ],
            'Vertical Jump Test' => [
                ...$this->rulesForSexes('height_cm', [
                    ['male', 'Excellent', 70, null, 'emerald'],
                    ['male', 'Very Good', 61, 69, 'lime'],
                    ['male', 'Good', 51, 60, 'blue'],
                    ['male', 'Average', 41, 50, 'amber'],
                    ['male', 'Below Average', 31, 40, 'orange'],
                    ['male', 'Poor', null, 30.9999, 'red'],
                    ['female', 'Excellent', 60, null, 'emerald'],
                    ['female', 'Very Good', 51, 59, 'lime'],
                    ['female', 'Good', 41, 50, 'blue'],
                    ['female', 'Average', 31, 40, 'amber'],
                    ['female', 'Below Average', 21, 30, 'orange'],
                    ['female', 'Poor', null, 20.9999, 'red'],
                ]),
            ],
            'Hexagon Agility Test' => [
                ...$this->rulesForSexes('time_seconds', [
                    ['male', 'Excellent', null, 10.4999, 'emerald'],
                    ['male', 'Very Good', 10.5, 11.4, 'lime'],
                    ['male', 'Good', 11.5, 12.4, 'blue'],
                    ['male', 'Average', 12.5, 13.4, 'amber'],
                    ['male', 'Below Average', 13.5, 14.5, 'orange'],
                    ['male', 'Poor', 14.5001, null, 'red'],
                    ['female', 'Excellent', null, 11.4999, 'emerald'],
                    ['female', 'Very Good', 11.5, 12.4, 'lime'],
                    ['female', 'Good', 12.5, 13.4, 'blue'],
                    ['female', 'Average', 13.5, 14.4, 'amber'],
                    ['female', 'Below Average', 14.5, 15.5, 'orange'],
                    ['female', 'Poor', 15.5001, null, 'red'],
                ]),
            ],
            'Ruler Drop Test' => [
                $this->rule('distance_cm', 'Excellent', null, 10.9999, 'emerald'),
                $this->rule('distance_cm', 'Very Good', 12, 15, 'lime'),
                $this->rule('distance_cm', 'Good', 16, 21, 'blue'),
                $this->rule('distance_cm', 'Average', 22, 28, 'amber'),
                $this->rule('distance_cm', 'Below Average', 29, 36, 'orange'),
                $this->rule('distance_cm', 'Poor', 36.0001, null, 'red'),
            ],
            'Alternate Hand Wall Toss Test' => [
                ...$this->rulesForSexes('successful_catches', [
                    ['male', 'Excellent', 42, null, 'emerald'],
                    ['male', 'Very Good', 36, 41, 'lime'],
                    ['male', 'Good', 30, 35, 'blue'],
                    ['male', 'Average', 24, 29, 'amber'],
                    ['male', 'Below Average', 18, 23, 'orange'],
                    ['male', 'Poor', null, 17.9999, 'red'],
                    ['female', 'Excellent', 38, null, 'emerald'],
                    ['female', 'Very Good', 32, 37, 'lime'],
                    ['female', 'Good', 26, 31, 'blue'],
                    ['female', 'Average', 20, 25, 'amber'],
                    ['female', 'Below Average', 14, 19, 'orange'],
                    ['female', 'Poor', null, 13.9999, 'red'],
                ]),
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
        ?string $classification = null,
        ?string $sex = null,
    ): array {
        $classification ??= $label;

        return [
            'field_name' => $fieldName,
            'sex' => $sex,
            'label' => $label,
            'classification' => $classification,
            'interpretation' => 'Results fall in the '.strtolower($classification).' range.',
            'suggested_intervention' => $this->suggestedInterventionFor($classification),
            'min_value' => $minValue,
            'max_value' => $maxValue,
            'color' => $color,
            'color_class' => $color,
        ];
    }

    private function rulesForSexes(string $fieldName, array $rows): array
    {
        return collect($rows)
            ->map(fn (array $row): array => $this->rule(
                $fieldName,
                $row[1],
                $row[2],
                $row[3],
                $row[4],
                $row[5] ?? null,
                $row[0],
            ))
            ->all();
    }

    private function suggestedInterventionFor(string $label): string
    {
        return match (strtolower($label)) {
            'obese' => 'Structured wellness activities, nutrition counseling, and regular monitoring.',
            'underweight' => 'Nutrition enhancement plans, dietary guidance, and growth tracking.',
            'needs improvement' => 'Specific physical drills, targeted training, and strength monitoring.',
            'poor' => 'Graduated fitness programs, basic drills, and close supervisor tracking.',
            'average', 'fair' => 'Standard physical activities, general fitness classes, and maintenance tracking.',
            'normal', 'good', 'excellent', 'very good' => 'General physical education activities and continued fitness maintenance.',
            default => 'Routine fitness tracking and recommended regular physical activities.',
        };
    }

    private function defaultProcedureFor(string $testName): ?array
    {
        return match ($testName) {
            'BMI Test' => [
                'Remove your shoes and heavy clothing or accessories.',
                'Stand straight on the weighing scale and record your weight.',
                'Stand against the height measuring device and record your height.',
                'Enter or confirm your height and weight in the application.',
                'The system will automatically calculate your BMI.',
            ],
            'Zipper Test' => [
                'Stand upright.',
                'Reach one hand over your shoulder and down your back.',
                'Reach the other hand behind your back and upward.',
                'Try to touch or overlap your middle fingers.',
                'Observe the distance or overlap between your fingertips.',
                'Repeat with the opposite hand positions.',
                'Record the best result.',
            ],
            'Sit and Reach Test' => [
                'Place a tape measure straight on the floor.',
                'Position the 23-cm mark of the tape measure at the edge where your heels will be placed (the 0-cm mark extends toward your body).',
                'Sit on the floor with both legs fully extended and your heels aligned with the 23-cm mark.',
                'Keep your feet about shoulder-width apart and your knees straight.',
                'Place one hand on top of the other with your palms facing down.',
                'Slowly reach forward along the tape measure as far as possible without bouncing.',
                'Hold the farthest position for 2 seconds.',
                'Record the farthest distance reached (cm).',
                'Perform two trials and record the best score.',
            ],
            '3-Minute Step Test' => [
                'Stand facing the 30.5-cm (12-inch) step bench.',
                'Begin stepping up and down at the required pace (24 complete steps per minute).',
                'Continue stepping continuously for 3 minutes.',
                'Stop immediately after 3 minutes.',
                'Measure your after activity pulse immediately for 1 minute.',
                'Then measure again immediately your pulse for 1 minute for recovery heart rate.',
                'Record your recovery heart rate (beats per minute).',
            ],
            'Push-Up Test' => [
                'Begin in the proper push-up position.',
                'Keep your body straight from head to heels.',
                'Lower your body until your elbows are about 90°.',
                'Push back up until your arms are fully extended.',
                'Continue performing correct push-ups until you can no longer maintain proper form.',
                'Record the total number of correct repetitions.',
            ],
            'Curl-Up Test' => [
                'Lie on your back with your knees bent and feet flat on the floor.',
                'Place your arms in the required position.',
                'Curl your upper body until your hands reach the required point.',
                'Return to the starting position.',
                'Continue performing correct curl-ups until you can no longer maintain proper form.',
                'Record the total number of correct repetitions.',
            ],
            'Stork Balance Stand Test' => [
                'Stand on your preferred foot.',
                'Place the other foot against the inside of your supporting knee.',
                'Put both hands on your hips.',
                'Raise the heel of your supporting foot and balance on your toes.',
                'Hold the position as long as possible.',
                'Stop the timer when you lose balance, move your hands, or your heel touches the floor.',
                'Record the best time.',
            ],
            '50-Meter Sprint' => [
                'Stand behind the starting line.',
                'Wait for the start signal.',
                'Run as fast as possible to the finish line.',
                'Do not slow down until you have crossed the finish line.',
                'Record your finishing time.',
            ],
            'Seated Medicine Ball Chest Pass Test' => [
                'Sit on the floor with your back against a wall and legs straight.',
                'Hold the medicine ball against your chest with both hands.',
                'Push the ball forward as far as possible using a chest pass.',
                'Keep your back against the wall during the throw.',
                'Measure the distance from the wall to where the ball first lands.',
                'Perform three trials.',
                'Record the best distance.',
            ],
            'Standing Long Jump' => [
                'Stand behind the take-off line with both feet together.',
                'Swing your arms and bend your knees.',
                'Jump forward as far as possible.',
                'Land on both feet without falling backward.',
                'Measure the distance from the take-off line to the nearest heel mark.',
                'Perform three trials.',
                'Record the best distance.',
            ],
            'Vertical Jump Test' => [
                'Stand beside the measuring board or wall.',
                'Reach up and mark your standing reach height.',
                'Jump as high as possible and touch the highest point you can reach.',
                'Measure the difference between your standing reach and highest touch.',
                'Perform three trials.',
                'Record the highest jump.',
            ],
            'Hexagon Agility Test' => [
                'Stand in the center of the hexagon facing forward.',
                'Jump over one side of the hexagon and immediately back to the center.',
                'Continue around the hexagon following the correct sequence.',
                'Complete the required number of rounds as quickly as possible.',
                'Record your fastest time.',
            ],
            'Ruler Drop Test' => [
                'Sit comfortably and place your thumb and index finger around the ruler without touching it.',
                'Have a partner hold the ruler vertically above your hand.',
                'Catch the ruler as quickly as possible when it is released.',
                'Record the distance where you caught the ruler.',
                'Perform three trials.',
                'Record the best result.',
            ],
            'Alternate Hand Wall Toss Test' => [
                'Stand behind the designated line.',
                'Throw the tennis ball against the wall using your right hand.',
                'Catch the rebound with your left hand.',
                'Throw immediately with your left hand.',
                'Catch with your right hand.',
                'Continue alternating hands.',
                'Continue for 30 seconds.',
                'Count the number of successful catches.',
            ],
            default => null,
        };
    }
}
