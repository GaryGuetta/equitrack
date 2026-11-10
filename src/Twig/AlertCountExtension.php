<?php

namespace App\Twig;

use App\Entity\User;
use App\Repository\AlertRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Attribute\AsTwigFunction;

final class AlertCountExtension
{
    public function __construct(
        private readonly Security $security,
        private readonly AlertRepository $alerts,
    ) {
    }

    /** Nombre d'alertes non traitées de l'utilisateur connecté, pour le badge de navigation. */
    #[AsTwigFunction('open_alert_count')]
    public function openAlertCount(): int
    {
        $user = $this->security->getUser();

        return $user instanceof User ? $this->alerts->countOpenForOwner($user) : 0;
    }
}
