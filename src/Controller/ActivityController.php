<?php

namespace App\Controller;

use App\Entity\Activity;
use App\Entity\Reservation;
use App\Entity\User;
use App\Form\ActivityType;
use App\Repository\ActivityRepository;
use App\Repository\ReservationRepository;
use App\Service\ActivityGoogleSyncService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class ActivityController extends AbstractController
{
    // Affiche les détails d'une activité, la liste de ses réservations triées par statut, le nombre de places restantes, et indique si l'utilisateur connecté est déjà inscrit à cette activité
    #[Route('/activity/{id}', name: 'app_activity', requirements: ['id' => '\d+'])]
    public function showActivity(int $id, ActivityRepository $activityRepository, ReservationRepository $reservationRepository): Response
    {
        $activity = $activityRepository->find($id);
        if (!$activity) {
            throw $this->createNotFoundException('Activité non trouvée');
        }
        $user = $this->getUser() instanceof User ? $this->getUser() : null;
        $reservations = $reservationRepository->findByActivitySortedByStatus($id);
        $dogs = [];
        foreach ($reservations as $reservation) {
            $dogs[] = $reservation->getDog();
        }
        $reservationsValid = array_filter($reservations, function (Reservation $reservation) {
            return $reservation->getStatus() === Reservation::STATUS_VALIDATED;
        });
        $nbPlacesRestantes = $activity->getNbPlaces() - count($reservationsValid);
        return $this->render('home/activity.html.twig', [
            'activity' => $activity,
            'reservations' => $reservations,
            'reservationsValid' => $reservationsValid,
            'nbPlacesRestantes' => $nbPlacesRestantes,
            'user' => $user,
            'dogs' => $dogs,
        ]);
    }


    // Permet à un administrateur d'ajouter une nouvelle activité
    #[Route('/newActivity', name: 'app_add_activity', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function addActivity(Request $request, EntityManagerInterface $em): Response
    {
        $activity = new Activity();
        $date = new \DateTime($request->query->get('date'));
        if ($date) {
            $activity->setDate($date);
        }
        $form = $this->createForm(ActivityType::class, $activity);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $activity->setGoogleNeedSync(true);
            $em->persist($activity);
            $em->flush();
            $this->addFlash('success', 'L\'activité a été ajoutée avec succès.');
            return $this->redirectToRoute('app_reservations_admin', ['date' => $activity->getDate()->format('Y-m-d')]);
        } elseif ($form->isSubmitted()) {
            $this->addFlash('error', 'Erreur lors de l\'ajout de l\'activité. Veuillez vérifier les données saisies.');
            return $this->redirectToRoute('app_add_activity');
        }
        return $this->render('admin/activity_new.html.twig', [
            'form' => $form->createView(),
            'date' => $date,
        ]);
    }


    // Permet à un administrateur de modifier une activité existante, avec envoi d'une notification par email à tous les utilisateurs inscrits à cette activité pour les informer de la modification de l'activité
    #[Route('/activity/{id}/edit', name: 'app_activity_edit', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function editActivity(ActivityRepository $activityRepository, EntityManagerInterface $em, MailerInterface $mailer, int $id, Request $request): Response
    {
        $activity = $activityRepository->find($id);
        $form = $this->createForm(ActivityType::class, $activity);
        $form->handleRequest($request);
        $oldActivity = clone $activity;

        if ($form->isSubmitted() && $form->isValid()) {
            $activity->setGoogleNeedSync(true);
            $em->persist($activity);
            $em->flush();
            if ($oldActivity->getService() == $activity->getService() && $oldActivity->getDate() == $activity->getDate() && $oldActivity->getStart() == $activity->getStart() && $oldActivity->getEnd() == $activity->getEnd()) {
                $this->addFlash('success', 'L\'activité a été modifiée avec succès. Aucun changement d\'horaire détecté, aucun email n\'a été envoyé aux utilisateurs inscrits à cette activité.');
                return $this->redirectToRoute('app_reservations_admin', ['date' => $activity->getDate()->format('Y-m-d')]);
            }
            if ($activity->getReservations()->count() > 0) {
                $emails = [];
                foreach ($activity->getReservations() as $reservation) {
                    if ($reservation->getDog()) {
                        $userEmail = $reservation->getDog()->getUser()->getEmail();
                        if (!in_array($userEmail, $emails)) {
                            $emails[] = $userEmail;
                        }
                    }
                }
                $mail = new TemplatedEmail();
                $mail->from('oxymaux@gmail.com');
                $mail->to('oxymaux@gmail.com');
                $mail->bcc(...$emails);
                $mail->subject('Modification de l\'activité ' . $oldActivity->getService()->getName());
                $mail->htmlTemplate('emails/activity_modified.html.twig');
                $mail->context([
                    'activity' => $activity,
                    'oldActivity' => $oldActivity,
                ]);
                $mailer->send($mail);
                $this->addFlash('info', 'L\'activité a été modifiée avec succès. Les utilisateurs inscrits à cette activité ont été informés de la modification par email.');
            } else {
                $this->addFlash('success', 'L\'activité a été modifiée avec succès. Aucun utilisateur n\'est inscrit à cette activité, aucun email n\'a été envoyé.');
            }
            return $this->redirectToRoute('app_reservations_admin', ['date' => $activity->getDate()->format('Y-m-d')]);
        } elseif ($form->isSubmitted()) {
            $this->addFlash('error', 'Erreur lors de la modification de l\'activité. Veuillez vérifier les données saisies.');
            return $this->redirectToRoute('app_activity_edit', ['id' => $id]);
        }
        return $this->render('admin/activity_edit.html.twig', [
            'form' => $form->createView(),
            'activity' => $activity,
        ]);
    }

    #[Route('/activity/{id}/delete', name: 'app_activity_delete')]
    #[IsGranted('ROLE_ADMIN')]
    public function deleteActivity(ActivityRepository $activityRepository, EntityManagerInterface $em, ActivityGoogleSyncService $activityGoogleSyncService, MailerInterface $mailer, Request $request, int $id): Response
    {
        $activity = $activityRepository->find($id);
        if (!$activity) {
            throw $this->createNotFoundException('Activité non trouvée');
        }

        $reason = $request->request->get('reason');
        $reservations = $activity->getReservations();

        $emails = [];

        foreach ($reservations as $reservation) {

            if ($reservation->getDog()) {
                $userEmail = $reservation->getDog()->getUser()->getEmail();
                if (!in_array($userEmail, $emails)) {
                    $emails[] = $userEmail;
                }
            }
        }

        $mail = new TemplatedEmail();

        $mail->from('oxymaux@gmail.com');

        $mail->to('oxymaux@gmail.com');

        $mail->bcc(...$emails);

        $mail->subject(
            'Annulation de l\'activité '
                . $activity->getService()->getName()
        );

        $mail->htmlTemplate('emails/activity_cancelled.html.twig');

        $mail->context([
            'activity' => $activity,
            'reason' => $reason
        ]);

        $mailer->send($mail);
        // Supprimer l'événement Google Agenda associé
        $activityGoogleSyncService->syncDelete($activity);
        $date = $activity->getDate()->format('Y-m-d');
        $em->remove($activity);
        $em->flush();
        $this->addFlash('success', 'L\'activité a été supprimée avec succès.');
        return $this->redirectToRoute('app_reservations_admin', ['date' => $date]);
    }

    #[Route('/activity/{id}/cancel', name: 'app_activity_cancel')]
    #[IsGranted('ROLE_ADMIN')]
    public function cancelActivity(ActivityRepository $activityRepository, EntityManagerInterface $em,  MailerInterface $mailer, Request $request, int $id): Response
    {
        $activity = $activityRepository->find($id);
        if (!$activity) {
            throw $this->createNotFoundException('Activité non trouvée');
        }

        $reason = $request->request->get('reason');
        $reservations = $activity->getReservations();

        $emails = [];

        foreach ($reservations as $reservation) {
            $reservation->setStatus(Reservation::STATUS_CANCELLED);

            if ($reservation->getDog()) {
                $userEmail = $reservation->getDog()->getUser()->getEmail();
                if (!in_array($userEmail, $emails)) {
                    $emails[] = $userEmail;
                }
            }
        }

        $mail = new TemplatedEmail();

        $mail->from('oxymaux@gmail.com');

        $mail->to('oxymaux@gmail.com');

        $mail->bcc(...$emails);

        $mail->subject(
            'Annulation de l\'activité '
                . $activity->getService()->getName()
        );

        $mail->htmlTemplate('emails/activity_cancelled.html.twig');

        $mail->context([
            'activity' => $activity,
            'reason' => $reason
        ]);

        $mailer->send($mail);
        // Annuler l'événement Google Agenda associé
        $activity->setGoogleNeedSync(true);
        $activity->setReasonCancel($reason);
        $activity->setCanceled(true);
        $em->persist($activity);
        $em->flush();
        $this->addFlash('success', 'L\'activité a été annulée avec succès.');
        return $this->redirectToRoute('app_reservations_admin', ['date' => $activity->getDate()->format('Y-m-d')]);
    }
}
