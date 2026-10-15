<?php

namespace App\Enums;

enum GradeEvaluationMode: string
{
    case DisabledVisible = 'disabled_visible';
    case DisabledHidden = 'disabled_hidden';
    case EnabledRequired = 'enabled_required';
    case EnabledOptional = 'enabled_optional';

    public function evaluationEnabled(bool $bypass): bool
    {
        return ! $bypass && in_array($this, [self::EnabledRequired, self::EnabledOptional], true);
    }

    public function gradesLocked(bool $bypass, bool $requiresEvaluation): bool
    {
        return ! $bypass && ($this === self::DisabledHidden || ($this === self::EnabledRequired && $requiresEvaluation));
    }
}
