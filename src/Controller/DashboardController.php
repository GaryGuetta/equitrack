<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\AlertRepository;
use App\Repository\HorseRepository;
use App\Repository\ReadingRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final class DashboardController extends AbstractController
{
    #[Route('/tableau-de-bord', name: 'app_dashboard')]
    public function index(
        #[CurrentUser] User $user,
        HorseRepository $horses,
        ReadingRepository $readings,
        AlertRepository $alerts,
    ): Response {
        $myHorses = $horses->findForOwner($user);

        return $this->render('dashboard/index.html.twig', [
            'horses' => $myHorses,
            'latest' => $readings->findLatestForHorses($myHorses),
            'openAlertsByHorse' => $alerts->countOpenByHorse($user),
            'openAlerts' => $alerts->findForOwner($user, openOnly: true, limit: 5),
        ]);
    }
}
