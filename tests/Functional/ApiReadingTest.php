<?php

namespace App\Tests\Functional;

use App\Entity\Alert;
use App\Entity\Reading;
use App\Enum\AlertType;
use App\Tests\Support\Factory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ApiReadingTest extends WebTestCase
{
    use Factory;

    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        self::resetDatabase($this->em);

        $user = self::createUser($this->em);
        self::createDevice($this->em, self::createHorse($this->em, $user), 'good-token');
        self::createDevice($this->em, null, 'orphan-token');
        $this->em->flush();
    }

    public function testRejectsMissingOrWrongToken(): void
    {
        $this->post(null, $this->batch([$this->reading()]));
        self::assertResponseStatusCodeSame(401);

        $this->post('wrong-token', $this->batch([$this->reading()]));
        self::assertResponseStatusCodeSame(401);
    }

    public function testStoresBatchInsidePaddockWithoutAlert(): void
    {
        $this->post('good-token', $this->batch([$this->reading('-4 minutes'), $this->reading('-2 minutes')]));

        self::assertResponseStatusCodeSame(201);
        self::assertSame(['accepted' => 2, 'alerts' => []], $this->json());
        self::assertSame(2, $this->em->getRepository(Reading::class)->count());
    }

    public function testGeofenceExitRaisesASingleCriticalAlert(): void
    {
        $outside = ['latitude' => 44.0240, 'longitude' => 1.3527];
        $this->post('good-token', $this->batch([$this->reading('-4 minutes', $outside), $this->reading('-2 minutes', $outside)]));

        self::assertResponseStatusCodeSame(201);
        $alerts = $this->json()['alerts'];
        self::assertCount(1, $alerts);
        self::assertSame(AlertType::GeofenceExit->value, $alerts[0]['type']);
        self::assertSame('critical', $alerts[0]['severity']);

        // Un second lot hors enclos ne répète pas l'alerte tant qu'elle n'est pas traitée.
        $this->post('good-token', $this->batch([$this->reading('now', $outside)]));
        self::assertSame([], $this->json()['alerts']);
        self::assertSame(1, $this->em->getRepository(Alert::class)->count());
    }

    public function testRepeatedRollingAndWetBlanket(): void
    {
        $readings = [];
        foreach (['-8', '-6', '-4'] as $minutes) {
            $readings[] = $this->reading("$minutes minutes", ['rolls' => 1, 'gait' => 'couche', 'humidity' => 91.0]);
        }
        $this->post('good-token', $this->batch($readings));

        $types = array_column($this->json()['alerts'], 'type');
        sort($types);
        self::assertSame([AlertType::WetBlanket->value, AlertType::RepeatedRolling->value], $types);
    }

    public function testInvalidPayloadIsRejected(): void
    {
        $this->post('good-token', $this->batch([$this->reading('now', ['humidity' => 150, 'gait' => 'pas'])]));
        self::assertResponseStatusCodeSame(422);

        $this->post('good-token', $this->batch([$this->reading('now', ['gait' => 'vol'])]));
        self::assertResponseStatusCodeSame(422);

        $this->post('good-token', ['readings' => []]);
        self::assertResponseStatusCodeSame(422);

        self::assertSame(0, $this->em->getRepository(Reading::class)->count());
    }

    public function testDeviceWithoutHorseIsRefused(): void
    {
        $this->post('orphan-token', $this->batch([$this->reading()]));

        self::assertResponseStatusCodeSame(409);
    }

    private function reading(string $when = 'now', array $overrides = []): array
    {
        return $overrides + [
            'recordedAt' => new \DateTimeImmutable($when)->format(\DATE_ATOM),
            'latitude' => 44.0225,
            'longitude' => 1.3527,
            'temperature' => 27.5,
            'humidity' => 60.0,
            'activity' => 22.0,
            'gait' => 'pas',
            'rolls' => 0,
            'battery' => 80,
        ];
    }

    private function batch(array $readings): array
    {
        return ['readings' => $readings];
    }

    private function post(?string $token, array $payload): void
    {
        $server = ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'];
        if (null !== $token) {
            $server['HTTP_AUTHORIZATION'] = 'Bearer '.$token;
        }
        $this->client->request('POST', '/api/v1/readings', server: $server, content: json_encode($payload));
    }

    private function json(): array
    {
        return json_decode($this->client->getResponse()->getContent(), true);
    }
}
