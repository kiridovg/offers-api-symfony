<?php

namespace App\Validator;

use App\Dto\Request\OfferInput;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

final class UniqueOfferIdsValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof UniqueOfferIds) {
            throw new UnexpectedTypeException($constraint, UniqueOfferIds::class);
        }

        if (null === $value) {
            return;
        }

        if (!\is_array($value)) {
            throw new UnexpectedValueException($value, 'array');
        }

        $seen = [];

        foreach ($value as $index => $offer) {
            if (!$offer instanceof OfferInput) {
                continue;
            }

            if (isset($seen[$offer->externalId])) {
                $this->context->buildViolation($constraint->message)
                    ->atPath(\sprintf('[%s].externalId', $index))
                    ->setParameter('{{ value }}', $this->formatValue($offer->externalId))
                    ->addViolation();

                continue;
            }

            $seen[$offer->externalId] = true;
        }
    }
}
