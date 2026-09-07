<?php

namespace App\Twig;

use App\Repository\ActivityRepository;
use App\Repository\ReservationRepository;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class AdminNotificationExtension extends AbstractExtension
{
    public function __construct(
        private ReservationRepository $reservationRepository,
        private ActivityRepository $activityRepository,
    ) {}

    public function getFunctions(): array
    {
        return [
            new TwigFunction('admin_action_count', [$this, 'getActionCount']),
        ];
    }

    public function getActionCount(): int
    {
        return $this->reservationRepository->countPending()
            + $this->activityRepository->countNeedingGoogleSync();
    }
}
