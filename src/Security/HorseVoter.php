<?php

namespace App\Security;

use App\Entity\Horse;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Un cheval n'est visible et modifiable que par son propriétaire (ou un administrateur).
 *
 * @extends Voter<string, Horse>
 */
final class HorseVoter extends Voter
{
    public const MANAGE = 'HORSE_MANAGE';

    public function __construct(
        private readonly Security $security,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return self::MANAGE === $attribute && $subject instanceof Horse;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        return $subject->getOwner() === $user || $this->security->isGranted('ROLE_ADMIN');
    }
}
