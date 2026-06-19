<?php

namespace Database\Seeders;

use App\Models\EvaluationInterpretationRange;
use App\Models\EvaluationRatingScale;
use App\Models\EvaluationScaleSet;
use App\Models\EvaluationScoringRule;
use App\Models\EvaluationStatement;
use App\Models\EvaluationStatementCategory;
use App\Models\EvaluationTemplate;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class StudentPortalSystemEvaluationSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $template = EvaluationTemplate::query()->updateOrCreate(
                ['name' => 'Student Portal System Evaluation'],
                [
                    'description' => 'Measures student satisfaction with the portal’s usability, reliability, information quality, features, security, and overall experience.',
                    'status' => 'active',
                ],
            );

            $scaleSet = EvaluationScaleSet::query()->updateOrCreate(
                [
                    'template_id' => $template->id,
                    'name' => 'Agreement Scale',
                ],
                [
                    'description' => 'Five-point agreement scale used by all scored questions.',
                    'is_default' => true,
                    'status' => 'active',
                    'sort_order' => 1,
                ],
            );

            EvaluationScaleSet::query()
                ->where('template_id', $template->id)
                ->whereKeyNot($scaleSet->id)
                ->update(['is_default' => false]);

            foreach ($this->ratingScale() as $sortOrder => $scale) {
                EvaluationRatingScale::query()->updateOrCreate(
                    [
                        'template_id' => $template->id,
                        'scale_set_id' => $scaleSet->id,
                        'value' => $scale['value'],
                    ],
                    [
                        'statement_id' => null,
                        'label' => $scale['label'],
                        'interpretation' => $scale['description'],
                        'sort_order' => $sortOrder + 1,
                        'status' => 'active',
                    ],
                );
            }

            $itemNumber = 1;

            foreach ($this->sections() as $sectionOrder => $section) {
                $category = EvaluationStatementCategory::query()->updateOrCreate(
                    [
                        'template_id' => $template->id,
                        'name' => $section['name'],
                    ],
                    [
                        'scale_set_id' => $scaleSet->id,
                        'description' => $section['description'],
                        'sort_order' => $sectionOrder + 1,
                        'status' => 'active',
                    ],
                );

                foreach ($section['questions'] as $questionOrder => $question) {
                    $statement = EvaluationStatement::query()->updateOrCreate(
                        [
                            'template_id' => $template->id,
                            'original_item_number' => $itemNumber,
                        ],
                        [
                            'category_id' => $category->id,
                            'scale_set_id' => $scaleSet->id,
                            'statement' => $question,
                            'help_text' => null,
                            'statement_type' => 'rating_scale',
                            'is_required' => true,
                            'weight' => 1,
                            'is_visible' => true,
                            'scoring_enabled' => true,
                            'is_read_only' => false,
                            'settings_json' => [
                                'likert_preview_type' => 'choices',
                                'min_value' => 1,
                                'max_value' => 5,
                            ],
                            'sort_order' => $questionOrder + 1,
                            'status' => 'active',
                        ],
                    );

                    EvaluationScoringRule::query()->updateOrCreate(
                        [
                            'template_id' => $template->id,
                            'category_id' => $category->id,
                            'statement_id' => $statement->id,
                        ],
                        [
                            'formula_type' => 'sum',
                            'multiplier' => 1,
                            'status' => 'active',
                        ],
                    );

                    $itemNumber++;
                }

                foreach ($this->interpretationRanges() as $rangeOrder => $range) {
                    EvaluationInterpretationRange::query()->updateOrCreate(
                        [
                            'template_id' => $template->id,
                            'category_id' => $category->id,
                            'min_value' => $range['min'],
                            'max_value' => $range['max'],
                        ],
                        [
                            'interpretation' => $range['interpretation'],
                            'suggested_intervention' => $range['suggested_intervention'],
                            'sort_order' => $rangeOrder + 1,
                            'status' => 'active',
                        ],
                    );
                }
            }

            foreach ($this->feedbackQuestions() as $feedbackOrder => $question) {
                EvaluationStatement::query()->updateOrCreate(
                    [
                        'template_id' => $template->id,
                        'original_item_number' => $itemNumber,
                    ],
                    [
                        'category_id' => null,
                        'scale_set_id' => null,
                        'statement' => $question,
                        'help_text' => 'Optional feedback helps improve the student portal.',
                        'statement_type' => 'long_answer',
                        'is_required' => false,
                        'weight' => 0,
                        'is_visible' => true,
                        'scoring_enabled' => false,
                        'is_read_only' => false,
                        'settings_json' => [
                            'placeholder' => 'Write your feedback here...',
                        ],
                        'sort_order' => $feedbackOrder + 1,
                        'status' => 'active',
                    ],
                );

                $itemNumber++;
            }
        });
    }

    private function ratingScale(): array
    {
        return [
            ['value' => 1, 'label' => 'Strongly Disagree', 'description' => 'The statement does not describe my experience.'],
            ['value' => 2, 'label' => 'Disagree', 'description' => 'The statement rarely describes my experience.'],
            ['value' => 3, 'label' => 'Neutral', 'description' => 'I neither agree nor disagree with the statement.'],
            ['value' => 4, 'label' => 'Agree', 'description' => 'The statement generally describes my experience.'],
            ['value' => 5, 'label' => 'Strongly Agree', 'description' => 'The statement fully describes my experience.'],
        ];
    }

    private function sections(): array
    {
        return [
            [
                'name' => 'Usability and Accessibility',
                'description' => 'Ease of learning, navigating, and using the student portal.',
                'questions' => [
                    'The student portal is easy to navigate.',
                    'Labels, instructions, and menu items are clear and understandable.',
                    'I can quickly find the information or feature that I need.',
                    'The portal works well on the device that I normally use.',
                ],
            ],
            [
                'name' => 'Performance and Reliability',
                'description' => 'Speed, availability, and consistency of portal services.',
                'questions' => [
                    'Pages and records load within a reasonable amount of time.',
                    'The portal is available when I need to use it.',
                    'Transactions and submitted forms are processed correctly.',
                    'Error messages clearly explain what went wrong and what I should do next.',
                ],
            ],
            [
                'name' => 'Information and Features',
                'description' => 'Quality, usefulness, and completeness of portal information and services.',
                'questions' => [
                    'The information displayed in the portal is accurate and up to date.',
                    'The available portal features meet my academic and student-service needs.',
                    'Grades, schedules, clearances, and other student records are easy to understand.',
                    'The portal reduces the need to visit university offices for routine transactions.',
                ],
            ],
            [
                'name' => 'Security and Overall Satisfaction',
                'description' => 'Confidence, trust, and overall satisfaction with the student portal.',
                'questions' => [
                    'I feel that my personal and academic information is secure in the portal.',
                    'I am confident that the portal saves and protects the information I submit.',
                    'Overall, I am satisfied with my experience using the student portal.',
                    'I would recommend using the student portal to other students.',
                ],
            ],
        ];
    }

    private function interpretationRanges(): array
    {
        return [
            [
                'min' => 4,
                'max' => 7,
                'interpretation' => 'Very Poor',
                'suggested_intervention' => 'Prioritize immediate review and corrective improvements for this area.',
            ],
            [
                'min' => 8,
                'max' => 11,
                'interpretation' => 'Needs Improvement',
                'suggested_intervention' => 'Identify recurring concerns and implement targeted usability or service improvements.',
            ],
            [
                'min' => 12,
                'max' => 15,
                'interpretation' => 'Satisfactory',
                'suggested_intervention' => 'Maintain current performance while addressing specific student feedback.',
            ],
            [
                'min' => 16,
                'max' => 18,
                'interpretation' => 'Very Good',
                'suggested_intervention' => 'Sustain effective practices and continue incremental enhancements.',
            ],
            [
                'min' => 19,
                'max' => 20,
                'interpretation' => 'Excellent',
                'suggested_intervention' => 'Maintain the current standard and use this area as a model for other services.',
            ],
        ];
    }

    private function feedbackQuestions(): array
    {
        return [
            'Which student portal feature is most useful to you, and why?',
            'What change or additional feature would most improve your student portal experience?',
        ];
    }
}
