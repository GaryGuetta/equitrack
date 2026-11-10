<?php

namespace App\Twig;

use Twig\Attribute\AsTwigFilter;

final class TimeExtension
{
    /** « à l'instant », « il y a 12 min », « il y a 3 h », « il y a 2 j ». */
    #[AsTwigFilter('ago')]
    public function ago(?\DateTimeInterface $date): string
    {
        if (null === $date) {
            return 'jamais';
        }

        $seconds = time() - $date->getTimestamp();

        return match (true) {
            $seconds < 60 => 'à l\'instant',
            $seconds < 3600 => \sprintf('il y a %d min', intdiv($seconds, 60)),
            $seconds < 86400 => \sprintf('il y a %d h', intdiv($seconds, 3600)),
            default => \sprintf('il y a %d j', intdiv($seconds, 86400)),
        };
    }

    /** 125 min → « 2 h 05 ». */
    #[AsTwigFilter('duration')]
    public function duration(int $minutes): string
    {
        return $minutes < 60 ? $minutes.' min' : \sprintf('%d h %02d', intdiv($minutes, 60), $minutes % 60);
    }
}
