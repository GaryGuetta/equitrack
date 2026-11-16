<?php

namespace App\Tests\Functional;

use App\Entity\Alert;
use App\Entity\Horse;
use App\Entity\Reading;
use App\Entity\User;
use App\Enum\AlertType;
use App\Enum\Gait;
use App\Tests\Support\Factory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class WebPagesTest extends WebTestCase
{
    use Factory;

    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private User $owner;
    private Horse $horse;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        self::resetDatabase($this->em);

        $this->owner = self::createUser($this->em);
        $this->horse = self::createHorse($this->em, $this->owner);
        self::createDevice($this->em, $this->horse, 'token');
        foreach (['-30 minutes', '-20 minutes', '-10 minutes'] as $i => $when) {
            $reading = (new Reading($this->horse, new \DateTimeImmutable($when)))
                ->setPosition(44.0225, 1.3527 + $i * 0.0003)
                ->setTemperature(28.0)->setHumidity(60.0)
                ->setActivity(25.0)->setGait(Gait::Walk);
            $this->em->persist($reading);
        }
        $this->em->flush();
    }

    public function testHomePageIsPublic(): void
    {
        $this->client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.slogan', 'Parce que son bien-être');
    }

    public function testPrivatePagesRequireLogin(): void
    {
        $this->client->request('GET', '/tableau-de-bord');

        self::assertResponseRedirects('/connexion');
    }

    public function testLoginPage(): void
    {
        $this->client->request('GET', '/connexion');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('input[name="_username"]');
    }

    public function testDashboardListsOwnHorses(): void
    {
        $this->client->loginUser($this->owner);
        $this->client->request('GET', '/tableau-de-bord');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.horse-card h3', 'Tornade');
        self::assertSelectorTextContains('.horse-card', 'Pas');
    }

    public function testHorsePageShowsSummaryAndAdvice(): void
    {
        $this->client->loginUser($this->owner);
        $this->client->request('GET', '/chevaux/'.$this->horse->getId());

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Tornade');
        self::assertSelectorTextContains('.grid-4', 'Dans l\'enclos');
        // Météo désactivée en test : sans prévision, pas de conseil.
        self::assertSelectorTextContains('.advice', 'Pas assez d\'informations');

        $this->client->request('GET', '/chevaux/'.$this->horse->getId().'?periode=7j');
        self::assertResponseIsSuccessful();
    }

    public function testCannotSeeSomeoneElsesHorse(): void
    {
        $stranger = self::createUser($this->em, 'autre@example.com');
        $this->em->flush();

        $this->client->loginUser($stranger);
        $this->client->request('GET', '/chevaux/'.$this->horse->getId());

        self::assertResponseStatusCodeSame(403);
    }

    public function testCreateHorseWithPaddock(): void
    {
        $this->client->loginUser($this->owner);
        $this->client->request('GET', '/chevaux/nouveau');
        $this->client->submitForm('Ajouter le cheval', [
            'horse[name]' => 'Caramel',
            'horse[clip]' => 'aucune',
            'horse[currentBlanket]' => 'legere',
            'horse[paddock]' => json_encode(self::PADDOCK),
        ]);

        $caramel = $this->em->getRepository(Horse::class)->findOneBy(['name' => 'Caramel']);
        self::assertNotNull($caramel);
        self::assertResponseRedirects('/chevaux/'.$caramel->getId());
        self::assertCount(4, $caramel->getPaddock());
        self::assertSame($this->owner->getId(), $caramel->getOwner()->getId());
    }

    public function testPaddockNeedsThreePoints(): void
    {
        $this->client->loginUser($this->owner);
        $this->client->request('GET', '/chevaux/nouveau');
        $this->client->submitForm('Ajouter le cheval', [
            'horse[name]' => 'Caramel',
            'horse[paddock]' => json_encode([[44.0, 1.0], [44.1, 1.0]]),
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.form-errors', 'au moins 3 points');
    }

    public function testAcknowledgeAlert(): void
    {
        $alert = new Alert($this->horse, AlertType::GeofenceExit, 'Tornade est sorti.', new \DateTimeImmutable());
        $this->em->persist($alert);
        $this->em->flush();

        $this->client->loginUser($this->owner);
        $this->client->request('GET', '/alertes');
        self::assertSelectorTextContains('.nav .count', '1');

        $this->client->submitForm('Traitée');

        self::assertResponseRedirects();
        $this->em->clear();
        self::assertFalse($this->em->find(Alert::class, $alert->getId())->isOpen());
    }

    public function testCsvExport(): void
    {
        $this->client->loginUser($this->owner);
        $this->client->request('GET', '/chevaux/'.$this->horse->getId().'/export.csv');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type', 'text/csv; charset=UTF-8');
        self::assertStringContainsString('attachment; filename=equitrack-tornade-7j.csv', $this->client->getResponse()->headers->get('content-disposition'));
    }
}
