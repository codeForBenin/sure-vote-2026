<?php

namespace App\Validator;

use App\Entity\Participation;
use App\Repository\ParticipationRepository;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class IncreasingVotersCountValidator extends ConstraintValidator
{
    public function __construct(
        private ParticipationRepository $participationRepository
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof IncreasingVotersCount) {
            throw new UnexpectedTypeException($constraint, IncreasingVotersCount::class);
        }

        /** @var Participation $participation */
        $participation = $this->context->getObject();

        if (!$participation instanceof Participation) {
            return;
        }

        $bureau = $participation->getBureauDeVote();
        if (!$bureau) {
            return;
        }

        // Trouver le dernier relevé pour ce bureau
        $lastParticipation = $this->participationRepository->findOneBy(
            ['bureauDeVote' => $bureau],
            ['heurePointage' => 'DESC']
        );

        if ($lastParticipation && $participation->getNombreVotants() < $lastParticipation->getNombreVotants()) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ value }}', (string) $participation->getNombreVotants())
                ->atPath('nombreVotants')
                ->addViolation();
        }
    }
}
