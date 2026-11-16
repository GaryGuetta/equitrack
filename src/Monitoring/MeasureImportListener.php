<?php

namespace App\Monitoring;

use App\Repository\ReadingRepository;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * Entretien de la base au fil de la navigation, sans tâche planifiée :
 * import des nouvelles mesures Supabase au plus toutes les 30 s, et une fois par jour
 * suppression des mesures de plus de 90 jours (la base gratuite Supabase est limitée à 500 Mo).
 * Une panne de l'un ou de l'autre ne bloque jamais la page.
 */
final class MeasureImportListener
{
    /** Durée de conservation des mesures, alignée sur l'export CSV (90 jours au plus). Les alertes sont gardées. */
    public const RETENTION = '-90 days';

    public function __construct(
        private readonly MeasureImporter $importer,
        private readonly ReadingRepository $readings,
        private readonly CacheInterface $cache,
        private readonly LoggerInterface $logger,
        private readonly ManagerRegistry $doctrine,
    ) {
    }

    #[AsEventListener(KernelEvents::REQUEST, priority: -10)]
    public function onRequest(RequestEvent $event): void
    {
        $path = $event->getRequest()->getPathInfo();
        if (!$event->isMainRequest() || str_starts_with($path, '/_') || str_starts_with($path, '/api/') || str_starts_with($path, '/assets/')) {
            return;
        }

        $this->every('mesures_import', 30, fn () => $this->importer->import());
        $this->every('mesures_purge', 86400, fn () => $this->readings->deleteOlderThan(new \DateTimeImmutable(self::RETENTION)));
    }

    private function every(string $key, int $seconds, callable $task): void
    {
        $this->cache->get($key, function ($item) use ($seconds, $task): bool {
            $item->expiresAfter($seconds);
            try {
                $task();
            } catch (\Throwable $e) {
                $this->logger->error('Entretien de la base ({task}) impossible : {message}', ['task' => $item->getKey(), 'message' => $e->getMessage(), 'exception' => $e]);
                $this->doctrine->resetManager(); // un échec d'écriture ferme l'EntityManager : la page en a besoin intact
            }

            return true;
        });
    }
}
