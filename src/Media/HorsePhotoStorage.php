<?php

namespace App\Media;

use App\Entity\Horse;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Enregistre la photo d'un cheval sur le serveur du site, pas dans Supabase (offre gratuite, quota limité).
 * Une photo de téléphone de plusieurs Mo devient un WebP d'environ 60 Ko, 800 px de côté au plus.
 */
class HorsePhotoStorage
{
    private const MAX_SIDE = 800;
    private const QUALITY = 80;

    public function __construct(
        #[Autowire('%kernel.project_dir%/public/uploads/chevaux')]
        private readonly string $directory,
    ) {
    }

    public function store(Horse $horse, UploadedFile $file): void
    {
        // Une photo de 12 Mpx décompressée occupe près de 60 Mo.
        ini_set('memory_limit', '512M');

        $data = file_get_contents($file->getPathname());
        $image = false !== $data ? @imagecreatefromstring($data) : false;
        if (false === $image) {
            throw new \InvalidArgumentException('Image illisible.');
        }

        $image = match (self::jpegOrientation($data)) {
            3 => imagerotate($image, 180, 0),
            6 => imagerotate($image, -90, 0),
            8 => imagerotate($image, 90, 0),
            default => $image,
        };

        $scale = min(1, self::MAX_SIDE / max(imagesx($image), imagesy($image)));
        if ($scale < 1) {
            $image = imagescale($image, (int) round(imagesx($image) * $scale), (int) round(imagesy($image) * $scale), \IMG_BICUBIC);
        }

        if (!is_dir($this->directory)) {
            mkdir($this->directory, 0o775, true);
        }
        $name = bin2hex(random_bytes(8)).'.webp';
        imagewebp($image, $this->directory.'/'.$name, self::QUALITY);

        $this->remove($horse);
        $horse->setPhoto($name);
    }

    public function remove(Horse $horse): void
    {
        if (null !== $horse->getPhoto()) {
            @unlink($this->directory.'/'.basename($horse->getPhoto()));
            $horse->setPhoto(null);
        }
    }

    /**
     * Sens de prise de vue noté par le téléphone (balise EXIF 0x0112), lu à la main : l'extension exif n'est pas installée.
     */
    private static function jpegOrientation(string $data): int
    {
        if (!str_starts_with($data, "\xFF\xD8")) {
            return 1;
        }

        try {
            for ($offset = 2; $offset + 4 < \strlen($data) && "\xFF" === $data[$offset]; $offset += 2 + unpack('n', $data, $offset + 2)[1]) {
                if ("\xE1" !== $data[$offset + 1] || "Exif\0\0" !== substr($data, $offset + 4, 6)) {
                    continue;
                }
                $tiff = $offset + 10;
                $little = 'II' === substr($data, $tiff, 2);
                $u16 = static fn (int $at): int => unpack($little ? 'v' : 'n', $data, $at)[1];
                $ifd = $tiff + unpack($little ? 'V' : 'N', $data, $tiff + 4)[1];
                for ($i = 0, $count = $u16($ifd); $i < $count; ++$i) {
                    if (0x0112 === $u16($ifd + 2 + $i * 12)) {
                        return $u16($ifd + 2 + $i * 12 + 8);
                    }
                }

                return 1;
            }
        } catch (\ValueError) {
            // Fichier tronqué : on garde l'image telle quelle.
        }

        return 1;
    }
}
