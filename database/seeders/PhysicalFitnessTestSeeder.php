<?php

namespace Database\Seeders;

use App\Models\PftCategory;
use App\Models\PftComponent;
use App\Models\PftConfiguration;
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
        $common = [
            $this->field('score', 'Score', 'decimal', true),
            $this->field('date_tested', 'Date Tested', 'date', false),
            $this->field('remarks', 'Remarks', 'textarea', false),
        ];

        if ($testName === 'BMI Test') {
            return [
                $this->field('height', 'Height (cm)', 'decimal', true),
                $this->field('weight', 'Weight (kg)', 'decimal', true),
                $this->field('bmi', 'BMI', 'decimal', false),
                $this->field('date_tested', 'Date Tested', 'date', false),
                $this->field('remarks', 'Remarks', 'textarea', false),
            ];
        }

        return $common;
    }

    private function field(string $name, string $label, string $type, bool $required): array
    {
        return [
            'field_name' => $name,
            'field_label' => $label,
            'field_type' => $type,
            'options' => null,
            'placeholder' => null,
            'help_text' => null,
            'is_required' => $required,
        ];
    }
}
