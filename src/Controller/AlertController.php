<?php

namespace App\Controller;

use App\Entity\Alert;
use App\Entity\User;
use App\Repository\AlertRepository;
use App\Security\HorseVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/alertes')]
final class AlertController extends AbstractController
{
    #[Route('', name: 'app_alert_index', methods: ['GET'])]
    public function index(#[CurrentUser] User $user, Request $request, AlertRepository $alerts): Response
    {
        $showAll = 'toutes' === $request->query->get('filtre');

        return $this->render('alert/index.html.twig', [
            'alerts' => $alerts->findForOwner($user, openOnly: !$showAll),
            'showAll' => $showAll,
        ]);
    }

    #[Route('/{id}/traiter', name: 'app_alert_acknowledge', methods: ['POST'])]
    #[IsCsrfTokenValid('submit')]
    #[IsGranted(HorseVoter::MANAGE, subject: new Expression('args["alert"].getHorse()'))]
    public function acknowledge(Alert $alert, Request $request, EntityManagerInterface $em): Response
    {
        $alert->acknowledge();
        $em->flush();

        $this->addFlash('success', 'Alerte marquée comme traitée.');

        return $this->redirect($this->safeReferer($request) ?? $this->generateUrl('app_alert_index'));
    }

    private function safeReferer(Request $request): ?string
    {
        $referer = $request->headers->get('referer');

        return null !== $referer && str_starts_with($referer, $request->getSchemeAndHttpHost().'/') ? $referer : null;
    }
}
