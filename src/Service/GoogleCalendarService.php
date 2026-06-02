<?php

namespace App\Service;

use App\Entity\Activity;
use App\Entity\GoogleAccount;
use Doctrine\ORM\EntityManagerInterface;
use Google\Client;
use Google\Service\Calendar;
use Google\Service\Calendar\Event;
use Google\Service\Calendar\EventDateTime;

class GoogleCalendarService
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {}

    /**
     * 🔐 Client Google configuré + refresh automatique
     */
    private function getClient(GoogleAccount $googleAccount): Client
    {
        $client = new Client();

        $client->setClientId($_ENV['GOOGLE_CLIENT_ID']);
        $client->setClientSecret($_ENV['GOOGLE_CLIENT_SECRET']);

        // Important pour récupérer / utiliser le refresh token
        $client->setAccessType('offline');

        // Token actuel (on inclut TOUJOURS le refresh_token s'il existe)
        $client->setAccessToken([
            'access_token'  => $googleAccount->getAccessToken(),
            'refresh_token' => $googleAccount->getRefreshToken(),
            'expires_in'    => $googleAccount->getExpiresAt()
                ? max($googleAccount->getExpiresAt()->getTimestamp() - time(), 0)
                : 0,
        ]);

        /**
         * 🔁 Refresh automatique du token si expiré
         */
        if ($client->isAccessTokenExpired()) {

            if (!$googleAccount->getRefreshToken()) {
                throw new \Exception('Refresh token Google manquant en base de données.');
            }

            // On lance le refresh
            $newToken = $client->fetchAccessTokenWithRefreshToken(
                $googleAccount->getRefreshToken()
            );

            // Erreur Google API
            if (isset($newToken['error'])) {
                throw new \Exception(
                    'Erreur Google refresh token : ' . $newToken['error'] . ' - ' . ($newToken['error_description'] ?? '')
                );
            }

            // Mise à jour de l'access token
            if (isset($newToken['access_token'])) {
                $googleAccount->setAccessToken($newToken['access_token']);
            }

            // Mise à jour de l'expiration
            if (isset($newToken['expires_in'])) {
                $googleAccount->setExpiresAt(
                    (new \DateTimeImmutable())->modify('+' . $newToken['expires_in'] . ' seconds')
                );
            }

            /**
             * ⚠️ Google ne renvoie le refresh_token au rafraîchissement QUE si 
             * l'accès de l'application a été révoqué ou si l'option "prompt=consent" est utilisée.
             * Sinon, l'ancien reste valide.
             */
            if (isset($newToken['refresh_token'])) {
                $googleAccount->setRefreshToken($newToken['refresh_token']);
            } else {
                // Si Google ne le renvoie pas, on s'assure que le tableau $newToken 
                // contient quand même l'ancien pour le setAccessToken final
                $newToken['refresh_token'] = $googleAccount->getRefreshToken();
            }

            // Sauvegarde en base
            $this->entityManager->flush();

            // Réinjecte le tableau complet mis à jour dans le client
            $client->setAccessToken($newToken);
        }

        return $client;
    }

    /**
     * 📅 Service Google Calendar
     */
    private function getService(GoogleAccount $googleAccount): Calendar
    {
        return new Calendar(
            $this->getClient($googleAccount)
        );
    }

    /**
     * 🟢 CREATE EVENT
     */
    public function createEvent(
        GoogleAccount $googleAccount,
        Activity $activity
    ): string {

        $service = $this->getService($googleAccount);

        $event = new Event();

        if ($activity->isCanceled()) {
            $event->setSummary(
                $activity->getService()->getName() . ' (Annulée)'
            );
        } else {
            $event->setSummary(
                $activity->getService()->getName()
            );
        }

        $event->setDescription(
            $this->buildDescription($activity)
        );

        // START
        $start = new EventDateTime();

        $startDateTime = new \DateTimeImmutable(
            $activity->getDate()->format('Y-m-d') . ' ' .
                $activity->getStart()->format('H:i:s'),
            new \DateTimeZone('Europe/Paris')
        );
        $start->setDateTime(
            $startDateTime->format(DATE_RFC3339)
        );

        $event->setStart($start);

        // END
        $end = new EventDateTime();

        $endDateTime = new \DateTimeImmutable(
            $activity->getDate()->format('Y-m-d') . ' ' .
                $activity->getEnd()->format('H:i:s'),
            new \DateTimeZone('Europe/Paris')
        );
        $end->setDateTime(
            $endDateTime->format(DATE_RFC3339)
        );

        $event->setEnd($end);

        // Création Google
        $createdEvent = $service->events->insert(
            'primary',
            $event
        );

        return $createdEvent->getId();
    }

    /**
     * 🟡 UPDATE EVENT
     */
    public function updateEvent(
        GoogleAccount $googleAccount,
        Activity $activity
    ): void {

        if (!$activity->getGoogleEventId()) {
            return;
        }

        $service = $this->getService($googleAccount);

        $event = $service->events->get(
            'primary',
            $activity->getGoogleEventId()
        );

        // MAJ titre
        if ($activity->isCanceled()) {
            $event->setSummary(
                $activity->getService()->getName() . ' (Annulée)'
            );
        } else {
            $event->setSummary(
                $activity->getService()->getName()
            );
        }

        // MAJ description
        $event->setDescription(
            $this->buildDescription($activity)
        );

        // MAJ start
        $startDateTime = new \DateTimeImmutable(
            $activity->getDate()->format('Y-m-d') . ' ' .
                $activity->getStart()->format('H:i:s'),
            new \DateTimeZone('Europe/Paris')
        );

        $event->getStart()->setDateTime(
            $startDateTime->format(DATE_RFC3339)
        );

        // MAJ end
        $endDateTime = new \DateTimeImmutable(
            $activity->getDate()->format('Y-m-d') . ' ' .
                $activity->getEnd()->format('H:i:s'),
            new \DateTimeZone('Europe/Paris')
        );

        $event->getEnd()->setDateTime(
            $endDateTime->format(DATE_RFC3339)
        );

        // Update Google
        $service->events->update(
            'primary',
            $event->getId(),
            $event
        );
    }

    /**
     * 🔴 DELETE EVENT
     */
    public function deleteEvent(
        GoogleAccount $googleAccount,
        Activity $activity
    ): void {

        if (!$activity->getGoogleEventId()) {
            return;
        }

        $service = $this->getService($googleAccount);

        $service->events->delete(
            'primary',
            $activity->getGoogleEventId()
        );
    }

    /**
     * 🧾 Description propre Google Agenda
     */
    private function buildDescription(Activity $activity): string
    {
        $participants = [];

        foreach ($activity->getReservations() as $reservation) {

            // uniquement réservations validées
            if ($reservation->getStatus() !== 'validée') {
                continue;
            }

            if ($reservation->getDog()) {
                $participants[] =
                    $reservation->getDog()->getFullName()
                    . ' - Propriétaire : ' . $reservation->getDog()->getUser()->getFirstname()
                    . ' ' . $reservation->getDog()->getUser()->getLastname();
            } else {
                $participants[] = $reservation->getName();
            }
        }

        if ($activity->getNbPlaces() === 0) {
            return $activity->getComment() ? $activity->getComment() : 'Aucune information disponible.';
        }

        if (empty($participants)) {
            return 'Aucun participant inscrit pour le moment.';
        }

        return "Participants :\n\n- "
            . implode("\n- ", $participants);
    }
}
