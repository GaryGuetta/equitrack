<?php

namespace App\DataFixtures;

use App\Entity\Alert;
use App\Entity\Device;
use App\Entity\Horse;
use App\Entity\User;
use App\Enum\AlertType;
use App\Enum\BlanketWeight;
use App\Enum\Clip;
use App\Monitoring\ReadingIngestor;
use App\Simulation\HorseSimulator;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Compte de démonstration : demo@equitrack.fr / equitrack-demo, avec 14 jours d'historique simulé.
 * Les jetons des boîtiers sont fixes pour pouvoir tester l'API (voir README).
 */
class AppFixtures extends Fixture
{
    public const DEMO_EMAIL = 'demo@equitrack.fr';
    public const DEMO_PASSWORD = 'equitrack-demo';
    public const DEMO_TOKENS = ['EQT-TORNADE' => 'demo-token-tornade', 'EQT-CARAMEL' => 'demo-token-caramel'];

    /** Pré d'essai fictif dans la campagne tarn-et-garonnaise. */
    private const PADDOCK = [
        [44.05660, 1.42750], [44.05680, 1.42960], [44.05560, 1.43000], [44.05510, 1.42830], [44.05570, 1.42730],
    ];

    public function __construct(
        private readonly UserPasswordHasherInterface $hasher,
        private readonly ReadingIngestor $ingestor,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $user = (new User())->setEmail(self::DEMO_EMAIL)->setFirstName('Jean');
        $user->setPassword($this->hasher->hashPassword($user, self::DEMO_PASSWORD));
        $manager->persist($user);

        $tornade = (new Horse())
            ->setName('Tornade')->setBreed('Selle Français')->setBirthYear(2014)
            ->setClip(Clip::Partial)->setCurrentBlanket(BlanketWeight::Medium)
            ->setPaddock(self::PADDOCK)->setOwner($user);
        $caramel = (new Horse())
            ->setName('Caramel')->setBreed('Haflinger')->setBirthYear(2004)
            ->setClip(Clip::None)->setCurrentBlanket(BlanketWeight::Heavy)->setHasShelter(true)
            ->setPaddock(self::PADDOCK)->setOwner($user);
        $ulysse = (new Horse())
            ->setName('Ulysse')->setBreed('Pur-sang arabe')->setBirthYear(2019)
            ->setClip(Clip::Full)->setColdSensitive(true)->setCurrentBlanket(BlanketWeight::Heavy)
            ->setOwner($user);

        foreach ([$tornade, $caramel, $ulysse] as $horse) {
            $manager->persist($horse);
        }

        $now = new \DateTimeImmutable();
        $from = $now->modify('-14 days')->setTime(0, 0);
        $seed = 42;
        foreach ([[$tornade, 'EQT-TORNADE', 100], [$caramel, 'EQT-CARAMEL', 100]] as [$horse, $serial, $battery]) {
            $device = new Device($serial, self::DEMO_TOKENS[$serial]);
            $horse->setDevice($device);
            $manager->persist($device);

            $inputs = (new HorseSimulator($seed++))->simulate($horse, $from, $now, batteryStart: $battery);
            // Recharge tous les 7 jours : on ne garde que la décharge depuis la dernière recharge.
            foreach ($inputs as $i => $input) {
                $reading = $this->ingestor->toReading($horse, $input);
                $reading->setBattery((int) max(8, 100 - ($i % 5040) / 5040 * 85));
                // Caramel est trop couvert : on le voit transpirer les après-midi des 3 derniers jours.
                if ($caramel === $horse && $input->recordedAt > $now->modify('-3 days')
                    && (int) $input->recordedAt->format('G') >= 13 && (int) $input->recordedAt->format('G') < 17) {
                    $reading->setTemperature(round($input->temperature + 6, 1))->setHumidity(min(95, round($input->humidity + 22, 1)));
                }
                $manager->persist($reading);
                if (0 === $i % 1000) {
                    $manager->flush();
                }
            }
            $device->markSeen($inputs[array_key_last($inputs)]->recordedAt, $reading->getBattery());
        }

        $history = [
            [$tornade, AlertType::GeofenceExit, 'Tornade est sorti de son enclos (à 18 m de la clôture).', '-5 days 07:42', true],
            [$tornade, AlertType::RepeatedRolling, 'Tornade s\'est roulé 4 fois en 30 min. Surveillez un risque de colique et appelez le vétérinaire en cas de doute.', '-2 days 21:10', true],
            [$caramel, AlertType::WetBlanket, '91 % d\'humidité sous la couverture de Caramel : couverture mouillée ou cheval qui transpire.', '-1 day 15:20', false],
            [$caramel, AlertType::TooHot, '35,4 °C sous la couverture de Caramel : il a probablement trop chaud.', '-3 hours', false],
        ];
        foreach ($history as [$horse, $type, $message, $when, $done]) {
            $alert = new Alert($horse, $type, $message, new \DateTimeImmutable($when));
            if ($done) {
                $alert->acknowledge();
            }
            $manager->persist($alert);
        }

        $manager->flush();
    }
}
