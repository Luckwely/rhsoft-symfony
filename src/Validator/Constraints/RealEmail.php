<?php

namespace App\Validator\Constraints;

use Symfony\Component\Validator\Constraint;


class RealEmail extends Constraint
{
    public string $message = 'L\'adresse email "{{ value }}" semble invalide (domaine introuvable).';

    public function validatedBy(): string
    {
        return static::class.'Validator';
    }
}
