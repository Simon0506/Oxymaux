<?php

namespace App\Service;

use App\Entity\User;
use App\Entity\Reservation;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class AccountDeletionService
{
    public function __construct(
        private EntityManagerInterface $em,
        private ActivityGoogleSyncService $activityGoogleSyncService,
        private MailerInterface $mailer
    ) {}

    /**
     * Supprime un compte utilisateur, nettoie ses réservations et envoie les notifications.
     * 
     * @param string $choice 'delete' pour annuler les activités futures, 'keep' pour les garder en historique.
     * @param bool $isAutomatic true si déclenché par le CRON (RGPD), false si demandé par l'utilisateur.
     */
    public function deleteAccount(User $user, string $choice = 'keep', bool $isAutomatic = false): void
    {
        $textChoiceForEmail = null;
        if ($choice === 'delete') {
            $textChoiceForEmail = 'Annuler les inscriptions aux activités futures.';
        } elseif ($choice === 'keep') {
            $textChoiceForEmail = 'Conserver les inscriptions aux activités futures.';
        }

        $activitiesToSync = [];
        foreach ($user->getDogs() as $dog) {
            $reservations = $dog->getReservations();

            // Filtrer les réservations validées et dans le futur
            $reservationsValidatedAndFuture = array_filter($reservations->toArray(), function (Reservation $reservation) {
                return $reservation->getStatus() === Reservation::STATUS_VALIDATED
                    && $reservation->getActivity()->getDate() >= new \DateTime();
            });

            foreach ($reservations as $reservation) {
                // Détacher le chien de la réservation pour l'historique de la gérante
                $reservation->setDog(null);

                // Anonymisation du libellé de la réservation pour la comptabilité / gestion
                $reservation->setName($dog->getFullName() . ' - Propriétaire : ' . $user->getFirstName() . ' ' . $user->getLastName() . ' (Compte supprimé)');

                // Si l'annulation est demandée (ou forcée par le CRON)
                if ($choice === 'delete' && in_array($reservation, $reservationsValidatedAndFuture)) {
                    $reservation->setStatus(Reservation::STATUS_CANCELLED);
                    if (!in_array($reservation->getActivity(), $activitiesToSync)) {
                        $activitiesToSync[] = $reservation->getActivity();
                    }
                }
                $this->em->persist($reservation);
            }
        }

        // Synchronisation avec Google Calendar si des activités ont été modifiées / annulées
        if (!empty($activitiesToSync)) {
            foreach ($activitiesToSync as $activity) {
                $this->activityGoogleSyncService->syncUpdate($activity);
            }
        }

        // Envoi des e-mails de notification (User + Admin)
        $this->sendEmails($user, $textChoiceForEmail, $isAutomatic);

        // Suppression finale de l'entité User (les chiens seront supprimés en cascade selon ta config)
        $this->em->remove($user);
        $this->em->flush();
    }

    private function sendEmails(User $user, ?string $textChoiceForEmail, bool $isAutomatic): void
    {
        // 1. E-mail à destination de l'utilisateur
        $mailToUser = new Email();
        $mailToUser->from('oxymaux@gmail.com');
        $mailToUser->to($user->getEmail());

        if ($isAutomatic) {
            $mailToUser->subject('Suppression de votre compte Oxymaux pour inactivité');
            $mailToUser->text("Bonjour " . $user->getFirstName() . ",\n\nConformément à la réglementation RGPD et après 3 ans d'inactivité sur notre plateforme, votre compte Oxymaux a été automatiquement supprimé.\n\nSi vous souhaitez de nouveau faire participer votre compagnon à nos activités, il vous suffira de recréer un compte.\n\nCordialement,\nL'équipe Oxymaux");
        } else {
            $mailToUser->subject('Confirmation de suppression de votre compte');
            $mailToUser->text("Bonjour " . $user->getFirstName() . ",\n\nVotre compte a été supprimé avec succès. Nous sommes désolés de vous voir partir !\n\nVous avez choisi de :\n" . $textChoiceForEmail . "\n\nSi toutefois vous changez d'avis, n'hésitez pas à nous recontacter.\n\nCordialement,\nL'équipe Oxymaux");
        }
        $this->mailer->send($mailToUser);

        // 2. E-mail à destination de l'administrateur (la gérante)
        $mailToAdmin = new Email();
        $mailToAdmin->from('oxymaux@gmail.com');
        $mailToAdmin->to('oxymaux@gmail.com');

        if ($isAutomatic) {
            $mailToAdmin->subject('[RGPD] Suppression automatique d\'un compte inactif');
            $mailToAdmin->text("Le compte de l'utilisateur " . $user->getFirstName() . " " . $user->getLastName() . " (" . $user->getEmail() . ") a été supprimé automatiquement après 3 ans d'inactivité. Les agendas Google ont été mis à jour.");
        } else {
            $mailToAdmin->subject('Un utilisateur a supprimé son compte');
            $mailToAdmin->text("L'utilisateur " . $user->getFirstName() . " " . $user->getLastName() . " a supprimé son compte manuellement.\n\nIl a choisi de :\n" . $textChoiceForEmail . "\n\nGoogle Agenda a été mis à jour en conséquence.");
        }
        $this->mailer->send($mailToAdmin);
    }
}
