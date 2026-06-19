<?php

namespace Database\Seeders;

use App\Models\EvaluationInterpretationRange;
use App\Models\EvaluationRatingScale;
use App\Models\EvaluationScoringRule;
use App\Models\EvaluationStatement;
use App\Models\EvaluationStatementCategory;
use App\Models\EvaluationTemplate;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class Dass21EvaluationTemplateSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $template = EvaluationTemplate::query()->updateOrCreate(
                ['name' => 'DASS-21 Assessment'],
                [
                    'description' => 'Depression Anxiety Stress Scale - 21 Items',
                    'status' => 'active',
                ],
            );

            foreach ($this->ratingScale() as $sortOrder => $scale) {
                EvaluationRatingScale::query()->updateOrCreate(
                    [
                        'template_id' => $template->id,
                        'statement_id' => null,
                        'value' => $scale['value'],
                    ],
                    [
                        'label' => $scale['label'],
                        'interpretation' => $scale['description'],
                        'sort_order' => $sortOrder + 1,
                        'status' => 'active',
                    ],
                );
            }

            $categories = [];

            foreach (array_keys($this->categoryItems()) as $sortOrder => $categoryName) {
                $categories[$categoryName] = EvaluationStatementCategory::query()->updateOrCreate(
                    [
                        'template_id' => $template->id,
                        'name' => $categoryName,
                    ],
                    [
                        'description' => "DASS-21 {$categoryName} subscale",
                        'sort_order' => $sortOrder + 1,
                        'status' => 'active',
                    ],
                );
            }

            $itemCategories = $this->itemCategories();
            $statements = [];

            foreach ($this->statements() as $itemNumber => $statementText) {
                $categoryName = $itemCategories[$itemNumber];

                $statements[$itemNumber] = EvaluationStatement::query()->updateOrCreate(
                    [
                        'template_id' => $template->id,
                        'original_item_number' => $itemNumber,
                    ],
                    [
                        'category_id' => $categories[$categoryName]->id,
                        'statement' => $statementText,
                        'help_text' => null,
                        'statement_type' => 'rating_scale',
                        'is_required' => true,
                        'weight' => 1,
                        'is_visible' => true,
                        'scoring_enabled' => true,
                        'is_read_only' => false,
                        'settings_json' => [
                            'original_item_number' => $itemNumber,
                            'subscale' => $categoryName,
                            'min_value' => 0,
                            'max_value' => 3,
                        ],
                        'sort_order' => $itemNumber,
                        'status' => 'active',
                    ],
                );

                EvaluationScoringRule::query()->updateOrCreate(
                    [
                        'template_id' => $template->id,
                        'category_id' => $categories[$categoryName]->id,
                        'statement_id' => $statements[$itemNumber]->id,
                    ],
                    [
                        'formula_type' => 'sum',
                        'multiplier' => 2,
                        'status' => 'active',
                    ],
                );
            }

            foreach ($this->interpretationRanges() as $categoryName => $ranges) {
                foreach ($ranges as $sortOrder => $range) {
                    EvaluationInterpretationRange::query()->updateOrCreate(
                        [
                            'template_id' => $template->id,
                            'category_id' => $categories[$categoryName]->id,
                            'min_value' => $range['min'],
                            'max_value' => $range['max'],
                        ],
                        [
                            'interpretation' => $range['interpretation'],
                            'sort_order' => $sortOrder + 1,
                            'status' => 'active',
                        ],
                    );
                }
            }
        });
    }

    private function ratingScale(): array
    {
        return [
            [
                'value' => 0,
                'label' => 'Never',
                'description' => 'Did not apply to me at all',
            ],
            [
                'value' => 1,
                'label' => 'Sometimes',
                'description' => 'Applied to me to some degree, or some of the time',
            ],
            [
                'value' => 2,
                'label' => 'Often',
                'description' => 'Applied to me to a considerable degree, or a good part of time',
            ],
            [
                'value' => 3,
                'label' => 'Almost Always',
                'description' => 'Applied to me very much, or most of the time',
            ],
        ];
    }

    private function categoryItems(): array
    {
        return [
            'Depression' => [3, 5, 10, 13, 16, 17, 21],
            'Anxiety' => [2, 4, 7, 9, 15, 19, 20],
            'Stress' => [1, 6, 8, 11, 12, 14, 18],
        ];
    }

    private function itemCategories(): array
    {
        $items = [];

        foreach ($this->categoryItems() as $category => $itemNumbers) {
            foreach ($itemNumbers as $itemNumber) {
                $items[$itemNumber] = $category;
            }
        }

        ksort($items);

        return $items;
    }

    private function statements(): array
    {
        return [
            1 => 'I found it hard to wind down.',
            2 => 'I was aware of dryness of my mouth.',
            3 => 'I couldn’t seem to experience any positive feeling at all.',
            4 => 'I experienced breathing difficulty.',
            5 => 'I found it difficult to work up the initiative to do things.',
            6 => 'I tended to over-react to situations.',
            7 => 'I experienced trembling.',
            8 => 'I felt that I was using a lot of nervous energy.',
            9 => 'I was worried about situations in which I might panic and make a fool of myself.',
            10 => 'I felt that I had nothing to look forward to.',
            11 => 'I found myself getting agitated.',
            12 => 'I found it difficult to relax.',
            13 => 'I felt down-hearted and blue.',
            14 => 'I was intolerant of anything that kept me from getting on with what I was doing.',
            15 => 'I felt I was close to panic.',
            16 => 'I was unable to become enthusiastic about anything.',
            17 => 'I felt I wasn’t worth much as a person.',
            18 => 'I felt that I was rather touchy.',
            19 => 'I was aware of the action of my heart in the absence of physical exertion.',
            20 => 'I felt scared without any good reason.',
            21 => 'I felt that life was meaningless.',
        ];
    }

    private function interpretationRanges(): array
    {
        return [
            'Depression' => [
                ['min' => 0, 'max' => 9, 'interpretation' => 'Normal'],
                ['min' => 10, 'max' => 13, 'interpretation' => 'Mild'],
                ['min' => 14, 'max' => 20, 'interpretation' => 'Moderate'],
                ['min' => 21, 'max' => 27, 'interpretation' => 'Severe'],
                ['min' => 28, 'max' => 42, 'interpretation' => 'Extremely Severe'],
            ],
            'Anxiety' => [
                ['min' => 0, 'max' => 7, 'interpretation' => 'Normal'],
                ['min' => 8, 'max' => 9, 'interpretation' => 'Mild'],
                ['min' => 10, 'max' => 14, 'interpretation' => 'Moderate'],
                ['min' => 15, 'max' => 19, 'interpretation' => 'Severe'],
                ['min' => 20, 'max' => 42, 'interpretation' => 'Extremely Severe'],
            ],
            'Stress' => [
                ['min' => 0, 'max' => 14, 'interpretation' => 'Normal'],
                ['min' => 15, 'max' => 18, 'interpretation' => 'Mild'],
                ['min' => 19, 'max' => 25, 'interpretation' => 'Moderate'],
                ['min' => 26, 'max' => 33, 'interpretation' => 'Severe'],
                ['min' => 34, 'max' => 42, 'interpretation' => 'Extremely Severe'],
            ],
        ];
    }
}
