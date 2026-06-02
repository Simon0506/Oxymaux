<?php

namespace App\Service;

use App\Entity\Activity;
use App\Repository\GoogleAccountRepository;

class ActivityGoogleSyncService
{
    public function __construct(
        private GoogleCalendarService $googleCalendarService,
        private GoogleAccountRepository $googleAccountRepository
    ) {}

    private function getAdminAccount()
    {
        $googleAccount = $this->googleAccountRepository->findAdminGoogleAccount();
        if (!$googleAccount) {
            return null;
        }
        return $googleAccount;
    }

    /**
     * 🔥 CREATE event si pas encore créé
     */
    public function syncCreate(Activity $activity): void
    {
        if ($activity->getGoogleEventId()) {
            return;
        }

        $googleAccount = $this->getAdminAccount();
        if (!$googleAccount) {
            throw new \Exception('Aucun compte Google connecté');
        }

        $eventId = $this->googleCalendarService->createEvent(
            $googleAccount,
            $activity
        );

        $activity->setGoogleEventId($eventId);
    }

    /**
     * ✏️ UPDATE event existant
     */
    public function syncUpdate(Activity $activity): void
    {
        if (!$activity->getGoogleEventId()) {
            return;
        }

        $googleAccount = $this->getAdminAccount();
        if (!$googleAccount) {
            return;
        }

        $this->googleCalendarService->updateEvent(
            $googleAccount,
            $activity
        );
    }

    /**
     * ❌ DELETE event
     */
    public function syncDelete(Activity $activity): void
    {
        if (!$activity->getGoogleEventId()) {
            return;
        }

        $googleAccount = $this->getAdminAccount();
        if (!$googleAccount) {
            return;
        }

        $this->googleCalendarService->deleteEvent(
            $googleAccount,
            $activity
        );

        $activity->setGoogleEventId(null);
    }
}
