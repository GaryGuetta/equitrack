<?php

namespace App\Controller\Api;

use App\Api\ReadingBatch;
use App\Entity\Alert;
use App\Entity\Device;
use App\Monitoring\ReadingIngestor;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * Point d'entrée des boîtiers : POST /api/v1/readings avec « Authorization: Bearer <jeton> ».
 */
final class ReadingController extends AbstractController
{
    #[Route('/api/v1/readings', name: 'api_readings_create', methods: ['POST'], format: 'json')]
    public function create(
        #[CurrentUser] Device $device,
        #[MapRequestPayload] ReadingBatch $batch,
        ReadingIngestor $ingestor,
    ): JsonResponse {
        if (null === $device->getHorse()) {
            return $this->json(['error' => 'Ce boîtier n\'est associé à aucun cheval.'], Response::HTTP_CONFLICT);
        }

        $alerts = $ingestor->ingest($device, $batch);

        return $this->json([
            'accepted' => \count($batch->readings),
            'alerts' => array_map(static fn (Alert $a) => [
                'type' => $a->getType()->value,
                'severity' => $a->getSeverity()->value,
                'message' => $a->getMessage(),
            ], $alerts),
        ], Response::HTTP_CREATED);
    }
}
