<?php

namespace App\MessageHandler;

use App\Message\SendBookingRemindersMessage;
use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[AsMessageHandler]
class SendBookingRemindersHandler
{
    public function __construct(
        private ReservationRepository $reservationRepository,
        private EntityManagerInterface $em,
        private MailerInterface $mailer,
        private UrlGeneratorInterface $urlGenerator
    ) {}

    public function __invoke(SendBookingRemindersMessage $message): void
    {
        $reservationsToRemind = $this->reservationRepository->findReservationsForReminder();

        // Tableau pour regrouper les réservations par l'ID de l'utilisateur
        $groupedReservations = [];

        foreach ($reservationsToRemind as $reservation) {
            $dog = $reservation->getDog();

            // 1. Gestion du cas "Participant sans compte"
            if (!$dog) {
                $reservation->setReminderSent(true);
                continue;
            }

            $user = $dog->getUser();
            $activity = $reservation->getActivity();

            if (!$user || !$activity) {
                continue;
            }

            // On regroupe les infos utiles par l'ID unique de l'utilisateur
            $userId = $user->getId();
            if (!isset($groupedReservations[$userId])) {
                $groupedReservations[$userId] = [
                    'user' => $user,
                    'details' => []
                ];
            }

            // On stocke la chaîne formatée pour chaque chien/activité de cet utilisateur
            $groupedReservations[$userId]['details'][] = sprintf(
                '<li><strong>%s</strong> : séance le %s</li>',
                $dog->getName(),
                $activity->getFullDate() ?? 'date non définie'
            );

            // On prépare le passage du témoin à true
            $reservation->setReminderSent(true);
        }

        // 2. Envoi d'un SEUL mail par utilisateur regroupé
        $personalSpaceUrl = $this->urlGenerator->generate('app_reservations', [], UrlGeneratorInterface::ABSOLUTE_URL);

        foreach ($groupedReservations as $group) {
            $user = $group['user'];

            $activitiesListHtml = '<ul>' . implode('', $group['details']) . '</ul>';

            $email = (new Email())
                ->from('oxymaux@gmail.com')
                ->to($user->getEmail())
                ->subject('Rappel : Vos prochaines séances Oxymaux')
                ->html(sprintf(
                    '<p>Bonjour %s,</p>
                    <p>Voici un rappel des séances à venir :</p>
                    %s
                    <p>Si vous avez un empêchement, merci de nous prévenir au plus vite afin de libérer les places pour d\'autres binômes. Vous pouvez modifier vos réservations en ligne depuis votre <a href="%s">espace personnel</a>, ou bien me contacter directement.</p>
                    <p>À très bientôt,<br>Noémie, gérante d\'Oxymaux</p>',
                    $user->getFirstName(),
                    $activitiesListHtml,
                    $personalSpaceUrl
                ));

            $this->mailer->send($email);
        }

        // 3. On sauvegarde tout en base de données
        $this->em->flush();
    }
}
