<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class IncreasingVotersCount extends Constraint
{
    public string $message = 'Le nombre de votants ({{ value }}) ne peut pas être inférieur au précédent relevé.';

    public function getTargets(): string|array
    {
        return self::CLASS_CONSTRAINT;
    }
}
