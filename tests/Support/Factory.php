<?php

namespace App\Tests\Support;

use App\Entity\Device;
use App\Entity\Horse;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;

/**
 * Base de test vierge et quelques objets prêts à l'emploi.
 */
trait Factory
{
    /** Carré d'environ 110 m de côté. */
    protected const PADDOCK = [[44.0220, 1.3520], [44.0230, 1.3520], [44.0230, 1.3534], [44.0220, 1.3534]];

    protected static function resetDatabase(EntityManagerInterface $em): void
    {
        $schemaTool = new SchemaTool($em);
        $metadata = $em->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);
    }

    protected static function createUser(EntityManagerInterface $em, string $email = 'jean@example.com'): User
    {
        // Mot de passe non utilisé : les tests se connectent avec loginUser().
        $user = (new User())->setEmail($email)->setFirstName('Jean')->setPassword('!');
        $em->persist($user);

        return $user;
    }

    protected static function createHorse(EntityManagerInterface $em, User $owner, string $name = 'Tornade'): Horse
    {
        $horse = (new Horse())->setName($name)->setOwner($owner)->setPaddock(self::PADDOCK);
        $em->persist($horse);

        return $horse;
    }

    protected static function createDevice(EntityManagerInterface $em, ?Horse $horse, string $token): Device
    {
        $device = new Device(Device::generateSerial(), $token);
        $horse?->setDevice($device);
        $em->persist($device);

        return $device;
    }
}
