<?php

namespace App\Controller;

use App\Advice\BlanketAdvisor;
use App\Entity\Alert;
use App\Entity\Device;
use App\Entity\Horse;
use App\Entity\Reading;
use App\Entity\User;
use App\Form\HorseType;
use App\Geo\GeoMath;
use App\Monitoring\DaySummary;
use App\Monitoring\TimeSeries;
use App\Repository\AlertRepository;
use App\Repository\ReadingRepository;
use App\Security\HorseVoter;
use App\Weather\WeatherProvider;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/chevaux')]
final class HorseController extends AbstractController
{
    private const PERIODS = [
        '24h' => ['since' => '-24 hours', 'bucket' => 10, 'label' => '24 heures'],
        '7j' => ['since' => '-7 days', 'bucket' => 60, 'label' => '7 jours'],
    ];

    #[Route('/nouveau', name: 'app_horse_new', methods: ['GET', 'POST'])]
    public function new(#[CurrentUser] User $user, Request $request, EntityManagerInterface $em): Response
    {
        $horse = (new Horse())->setOwner($user);
        $form = $this->createForm(HorseType::class, $horse);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($horse);
            $em->flush();
            $this->addFlash('success', \sprintf('%s a été ajouté. Associez-lui un boîtier pour recevoir ses données.', $horse->getName()));

            return $this->redirectToRoute('app_horse_show', ['id' => $horse->getId()]);
        }

        return $this->render('horse/form.html.twig', ['form' => $form, 'horse' => $horse]);
    }

    #[Route('/{id}', name: 'app_horse_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted(HorseVoter::MANAGE, 'horse')]
    public function show(
        Horse $horse,
        ReadingRepository $readings,
        AlertRepository $alerts,
        WeatherProvider $weather,
        BlanketAdvisor $advisor,
        #[MapQueryParameter] string $periode = '24h',
    ): Response {
        $period = self::PERIODS[$periode] ?? self::PERIODS[$periode = '24h'];
        $now = new \DateTimeImmutable();

        $today = $readings->findBetween($horse, $now->setTime(0, 0));
        $history = $readings->findBetween($horse, $now->modify($period['since']));
        $latest = $readings->findLatest($horse);

        $position = $latest?->hasPosition() ? [$latest->getLatitude(), $latest->getLongitude()] : GeoMath::centroid($horse->getPaddock());
        $forecast = null !== $position ? $weather->forecast($position[0], $position[1]) : null;

        return $this->render('horse/show.html.twig', [
            'horse' => $horse,
            'latest' => $latest,
            'summary' => DaySummary::fromReadings($today),
            'track' => array_values(array_map(
                static fn (Reading $r) => [$r->getLatitude(), $r->getLongitude()],
                array_filter($today, static fn (Reading $r) => $r->hasPosition()),
            )),
            'insidePaddock' => $latest?->hasPosition() && $horse->hasPaddock()
                ? GeoMath::containsPoint($horse->getPaddock(), $latest->getLatitude(), $latest->getLongitude())
                : null,
            'series' => TimeSeries::bucket($history, $period['bucket']),
            'periode' => $periode,
            'periods' => self::PERIODS,
            'forecast' => $forecast,
            'hasPosition' => null !== $position,
            'advice' => $advisor->advise($horse, $forecast, $latest),
            'alerts' => $alerts->findRecentForHorse($horse, 8),
        ]);
    }

    #[Route('/{id}/modifier', name: 'app_horse_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    #[IsGranted(HorseVoter::MANAGE, 'horse')]
    public function edit(Horse $horse, Request $request, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(HorseType::class, $horse);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Fiche mise à jour.');

            return $this->redirectToRoute('app_horse_show', ['id' => $horse->getId()]);
        }

        return $this->render('horse/form.html.twig', ['form' => $form, 'horse' => $horse]);
    }

    #[Route('/{id}/supprimer', name: 'app_horse_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(HorseVoter::MANAGE, 'horse')]
    #[IsCsrfTokenValid('submit')]
    public function delete(Horse $horse, EntityManagerInterface $em): Response
    {
        // Suppression explicite : SQLite n'applique pas toujours les ON DELETE CASCADE.
        foreach ([Reading::class, Alert::class] as $class) {
            $em->createQuery("DELETE FROM $class x WHERE x.horse = :horse")->setParameter('horse', $horse)->execute();
        }
        $horse->getDevice()?->setHorse(null);
        $em->remove($horse);
        $em->flush();

        $this->addFlash('success', \sprintf('%s et son historique ont été supprimés.', $horse->getName()));

        return $this->redirectToRoute('app_dashboard');
    }

    /**
     * Associe un boîtier au cheval, ou régénère son jeton. Le jeton n'est affiché qu'une fois.
     */
    #[Route('/{id}/boitier', name: 'app_horse_device', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(HorseVoter::MANAGE, 'horse')]
    #[IsCsrfTokenValid('submit')]
    public function device(Horse $horse, EntityManagerInterface $em): Response
    {
        $token = Device::generateToken();
        $device = $horse->getDevice();
        if (null === $device) {
            $device = new Device(Device::generateSerial(), $token);
            $horse->setDevice($device);
            $em->persist($device);
        } else {
            $device->rotateToken($token);
        }
        $em->flush();

        $this->addFlash('device_token', $token);

        return $this->redirectToRoute('app_horse_show', ['id' => $horse->getId(), '_fragment' => 'boitier']);
    }

    /**
     * Historique brut en CSV, pour le vétérinaire ou l'analyse des essais.
     */
    #[Route('/{id}/export.csv', name: 'app_horse_export', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted(HorseVoter::MANAGE, 'horse')]
    public function export(Horse $horse, ReadingRepository $readings, SluggerInterface $slugger, #[MapQueryParameter] int $jours = 7): StreamedResponse
    {
        $jours = max(1, min(90, $jours));
        $rows = $readings->findBetween($horse, new \DateTimeImmutable("-$jours days"));

        $response = new StreamedResponse(static function () use ($rows): void {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM : Excel lit l'UTF-8 correctement
            fputcsv($out, ['date', 'latitude', 'longitude', 'temp_couverture_c', 'humidite_pct', 'activite', 'allure', 'roulades', 'pouls_bpm', 'batterie_pct'], ';', '"', '');
            foreach ($rows as $r) {
                fputcsv($out, [
                    $r->getRecordedAt()->format('Y-m-d H:i:s'), $r->getLatitude(), $r->getLongitude(),
                    $r->getTemperature(), $r->getHumidity(), $r->getActivity(),
                    $r->getGait()?->label(), $r->getRolls(), $r->getHeartRate(), $r->getBattery(),
                ], ';', '"', '');
            }
            fclose($out);
        });
        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', HeaderUtils::makeDisposition(
            HeaderUtils::DISPOSITION_ATTACHMENT,
            \sprintf('equitrack-%s-%dj.csv', $slugger->slug($horse->getName())->lower(), $jours),
        ));

        return $response;
    }
}
