<?php

namespace App\Security;

use App\Repository\DeviceRepository;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Http\AccessToken\AccessTokenHandlerInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;

/**
 * Authentifie un boîtier à partir de l'en-tête « Authorization: Bearer <jeton> ».
 */
final class DeviceAccessTokenHandler implements AccessTokenHandlerInterface
{
    public function __construct(
        private readonly DeviceRepository $devices,
    ) {
    }

    public function getUserBadgeFrom(#[\SensitiveParameter] string $accessToken): UserBadge
    {
        $device = $this->devices->findOneByPlainToken($accessToken)
            ?? throw new BadCredentialsException('Jeton de boîtier invalide.');

        return new UserBadge($device->getSerial(), static fn () => $device);
    }
}
