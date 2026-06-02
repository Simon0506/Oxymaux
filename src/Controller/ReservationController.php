<?php

namespace App\Controller;

use App\Entity\Reservation;
use App\Entity\User;
use App\Form\ReservationType;
use App\Repository\ActivityRepository;
use App\Repository\DogRepository;
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

final class ReservationController extends AbstractController
{
    // Liste des réservations d'un utilisateur, séparées en passées et à venir
    #[Route('/reservations', name: 'app_reservations')]
    #[IsGranted('ROLE_USER')]
    public function reservations(ReservationRepository $reservationRepository): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }
        $today = new \DateTime();
        $reservations = $reservationRepository->findByUserId($user->getId());
        $activities = [];
        foreach ($reservations as $reservation) {
            $activity = $reservation->getActivity();
            if (!in_array($activity, $activities)) {
                $activities[] = $activity;
            }
        }
        $activitiesPast = array_filter($activities, function ($activity) use ($today) {
            return $activity->getDate()->format('Y-m-d') < $today->format('Y-m-d');
        });
        usort($activitiesPast, function ($a, $b) {
            return $b->getDate() <=> $a->getDate() ?: $b->getStart() <=> $a->getStart();
        });
        $activitiesUpcoming = array_filter($activities, function ($activity) use ($today) {
            return $activity->getDate()->format('Y-m-d') >= $today->format('Y-m-d');
        });
        usort($activitiesUpcoming, function ($a, $b) {
            return $a->getDate() <=> $b->getDate() ?: $a->getStart() <=> $b->getStart();
        });
        return $this->render('home/reservations.html.twig', [
            'activitiesPast' => $activitiesPast,
            'activitiesUpcoming' => $activitiesUpcoming,
            'reservations' => $reservations,
        ]);
    }


    // Liste de toutes les réservations en attente de validation, avec possibilité de filtrer par date d'activité et de valider ou refuser chaque réservation
    #[Route('/reservationsAdmin', name: 'app_reservations_admin')]
    #[IsGranted('ROLE_ADMIN')]
    public function reservationsAdmin(ReservationRepository $reservationRepository, Request $request, ActivityRepository $activityRepository, EntityManagerInterface $em): Response
    {
        $reservationsPending = $reservationRepository->findBy(['status' => Reservation::STATUS_PENDING]);
        $today = new \DateTime();
        foreach ($reservationsPending as $reservation) {
            if ($reservation->getActivity()->getDate()->format('Y-m-d') < $today->format('Y-m-d')) {
                $reservation->setStatus(Reservation::STATUS_CANCELLED);
                $em->persist($reservation);
            }
        }
        $em->flush();
        usort($reservationsPending, function (Reservation $a, Reservation $b) {
            return $a->getActivity()->getDate() <=> $b->getActivity()->getDate();
        });

        $date = new \DateTime(
            $request->query->get('date', 'now')
        );
        $activities = $activityRepository->findBy(['date' => $date]);
        usort($activities, function ($a, $b) {
            return $a->getStart() <=> $b->getStart();
        });
        $activitiesNeedingSync = $activityRepository->findBy(['googleNeedSync' => true]);

        return $this->render('admin/reservations.html.twig', [
            'reservations' => $reservationsPending,
            'activities' => $activities,
            'date' => $date,
            'activitiesNeedingSync' => $activitiesNeedingSync,
        ]);
    }


    // Permet à un utilisateur de s'inscrire à une activité, avec vérification des places disponibles et envoi d'une notification par email à l'administrateur pour validation de la réservation
    #[Route('/activity/{id}/register', name: 'app_activity_register', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function registerActivity(int $id, ActivityRepository $activityRepository, ReservationRepository $reservationRepository, DogRepository $dogRepository, Request $request, EntityManagerInterface $em, MailerInterface $mailer): Response
    {
        $activity = $activityRepository->find($id);
        if (!$activity) {
            throw $this->createNotFoundException('Activité non trouvée');
        }

        // Vérifier si l'utilisateur est déjà inscrit        
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }
        $dog = $dogRepository->find($request->request->get('dog'));
        if (!$dog || $dog->getUser()->getId() !== $user->getId()) {
            $this->addFlash('error', 'Chien non trouvé ou n\'appartenant pas à votre compte.');
            return $this->redirectToRoute('app_activity', ['id' => $id]);
        }
        $existingReservation = $reservationRepository->findOneBy([
            'dog' => $dog,
            'activity' => $activity,
        ]);

        if ($existingReservation && $existingReservation->getStatus() === Reservation::STATUS_VALIDATED) {
            $this->addFlash('error', $dog->getName() . ' est déjà inscrit à cette activité.');
            return $this->redirectToRoute('app_activity', ['id' => $id]);
        } elseif ($existingReservation && $existingReservation->getStatus() === Reservation::STATUS_PENDING) {
            $this->addFlash('error', 'Votre demande d\'inscription à cette activité pour ' . $dog->getName() . ' est en cours de validation. Vous recevrez une notification par email une fois que votre inscription aura été validée ou refusée.');
            return $this->redirectToRoute('app_activity', ['id' => $id]);
        } elseif ($existingReservation && $existingReservation->getStatus() === Reservation::STATUS_REFUSED) {
            $this->addFlash('error', 'Votre précédente demande d\'inscription à cette activité pour ' . $dog->getName() . ' a été refusée. Vous ne pouvez pas vous réinscrire pour le moment. Pour toute question, veuillez nous contacter.');
            return $this->redirectToRoute('app_activity', ['id' => $id]);
        }

        // Vérifier les places disponibles
        $reservationsValides = array_filter($activity->getReservations()->toArray(), function (Reservation $reservation) {
            return $reservation->getStatus() === Reservation::STATUS_VALIDATED;
        });
        if (!$activity->isOpenToAll() && $activity->getNbPlaces() <= count($reservationsValides)) {
            $this->addFlash('error', 'Désolé, cette activité est complète.');
            return $this->redirectToRoute('app_activity', ['id' => $id]);
        }

        if ($existingReservation && $existingReservation->getStatus() === Reservation::STATUS_CANCELLED) {
            $reservation = $existingReservation;
        } else {
            $reservation = new Reservation();
        }
        $reservation->setDog($dog);
        $reservation->setActivity($activity);
        $reservation->setStatus(Reservation::STATUS_PENDING);
        $reservation->setCreatedAt(new \DateTimeImmutable());
        $em->persist($reservation);
        $em->flush();

        $mail = new TemplatedEmail();
        $mail->from('oxymaux@gmail.com');
        $mail->to('oxymaux@gmail.com');
        $mail->subject('Nouvelle demande d\'inscription à une activité : ' . $activity->getService()->getName());
        $mail->htmlTemplate('emails/new_reservation.html.twig');
        $mail->context([
            'dog' => $dog,
            'activity' => $activity,
        ]);
        $mailer->send($mail);

        $this->addFlash('success', 'Votre demande d\'inscription pour ' . $dog->getName() . ' a été envoyée avec succès ! Vous recevrez une notification par email une fois que l\'inscription aura été validée ou refusée.');
        return $this->redirectToRoute('app_activity', ['id' => $id]);
    }


    // Permet à un administrateur d'ajouter une inscription à une activité pour un utilisateur, avec vérification des places disponibles et envoi d'une notification par email à l'utilisateur pour confirmer son inscription
    #[Route('/activity/{id}/registerAdmin', name: 'app_activity_register_admin', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function registerAdmin(ActivityRepository $activityRepository, EntityManagerInterface $em, MailerInterface $mailer, int $id, Request $request): Response
    {
        $activity = $activityRepository->find($id);
        if (!$activity) {
            throw $this->createNotFoundException('Activité non trouvée');
        }
        $reservations = array_filter($activity->getReservations()->toArray(), function (Reservation $reservation) {
            return $reservation->getStatus() === Reservation::STATUS_VALIDATED;
        });
        if (!$activity->isOpenToAll() && ($activity->getNbPlaces() == 0 || $activity->getNbPlaces() <= count($reservations))) {
            $this->addFlash('error', 'Désolé, cette activité est complète. Impossible d\'ajouter une nouvelle inscription.');
            return $this->redirectToRoute('app_reservations_admin', ['date' => $activity->getDate()->format('Y-m-d')]);
        }
        $nbPlacesRestantes = $activity->getNbPlaces() !== null ? $activity->getNbPlaces() - count($reservations) : null;
        $reservation = new Reservation();
        $form = $this->createForm(ReservationType::class, $reservation);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            if ($nbPlacesRestantes <= 0 and !$activity->isOpenToAll()) {
                $this->addFlash('error', 'Désolé, cette activité est complète. Impossible d\'ajouter une nouvelle inscription.');
                return $this->redirectToRoute('app_reservations_admin', ['date' => $activity->getDate()->format('Y-m-d')]);
            }
            if ($reservation->getDog() != null) {

                $existingReservationValid = $activity->getReservations()->filter(function (Reservation $r) use ($reservation) {
                    return $r->getDog() === $reservation->getDog() && $r->getStatus() === Reservation::STATUS_VALIDATED;
                })->first();

                if ($existingReservationValid) {
                    $this->addFlash('error', 'Le chien ' . $reservation->getDog()->getFullName() . ' de ' . $reservation->getDog()->getUser()->getFirstname() . ' ' . $reservation->getDog()->getUser()->getLastname() . ' est déjà inscrit à cette activité.');
                    return $this->redirectToRoute('app_reservations_admin', ['date' => $activity->getDate()->format('Y-m-d')]);
                }

                $existingReservationNotValid = $activity->getReservations()->filter(function (Reservation $r) use ($reservation) {
                    return $r->getDog() === $reservation->getDog() && $r->getStatus() !== Reservation::STATUS_VALIDATED;
                })->first();

                if ($existingReservationNotValid) {
                    $existingReservationNotValid->setStatus(Reservation::STATUS_VALIDATED);
                    $activity->setGoogleNeedSync(true);
                    $em->persist($existingReservationNotValid);
                    $em->flush();
                    $mail = new TemplatedEmail();
                    $mail->from('oxymaux@gmail.com');
                    $mail->to($existingReservationNotValid->getDog()->getUser()->getEmail());
                    $mail->subject('Votre chien ' . $existingReservationNotValid->getDog()->getName() . ' a été inscrit à l\'activité "' . $activity->getService()->getName() . '" !');
                    $mail->htmlTemplate('emails/confirmation_reservation.html.twig');
                    $mail->context([
                        'reservation' => $existingReservationNotValid,
                    ]);
                    $mailer->send($mail);
                    $this->addFlash('success', 'L\'inscription de ' . ($reservation->getDog() ? $reservation->getDog()->getFullName() . ', chien de ' . $reservation->getDog()->getUser()->getFirstname() . ' ' . $reservation->getDog()->getUser()->getLastname() : $reservation->getName()) . ', a été validée avec succès.');
                    return $this->redirectToRoute('app_reservations_admin', ['date' => $activity->getDate()->format('Y-m-d')]);
                }
            }
            $reservation->setActivity($activity);
            $reservation->setStatus(Reservation::STATUS_VALIDATED);
            $reservation->setCreatedAt(new \DateTimeImmutable());
            $activity->setGoogleNeedSync(true);
            $em->persist($reservation);
            $em->flush();
            if ($reservation->getDog()) {
                $mail = new TemplatedEmail();
                $mail->from('oxymaux@gmail.com');
                $mail->to($reservation->getDog()->getUser()->getEmail());
                $mail->subject('Votre chien ' . $reservation->getDog()->getName() . ' a été inscrit à l\'activité "' . $activity->getService()->getName() . '" !');
                $mail->htmlTemplate('emails/confirmation_reservation.html.twig');
                $mail->context([
                    'reservation' => $reservation,
                ]);
                $mailer->send($mail);
            }
            $this->addFlash('success', 'L\'inscription de ' . ($reservation->getDog() ? $reservation->getDog()->getFullName() . ', chien de ' . $reservation->getDog()->getUser()->getFirstname() . ' ' . $reservation->getDog()->getUser()->getLastname() : $reservation->getName()) . ', a été ajoutée avec succès.');
            return $this->redirectToRoute('app_reservations_admin', ['date' => $activity->getDate()->format('Y-m-d')]);
        } elseif ($form->isSubmitted()) {
            $this->addFlash('error', 'Erreur lors de l\'ajout de l\'inscription. Veuillez vérifier les données saisies.');
            return $this->redirectToRoute('app_activity_register_admin', ['id' => $id]);
        }
        return $this->render('admin/activity_register.html.twig', [
            'activity' => $activity,
            'reservations' => $reservations,
            'form' => $form->createView(),
        ]);
    }


    // Permet à un administrateur de valider une réservation en attente, avec envoi d'une notification par email à l'utilisateur pour confirmer la validation de sa réservation
    #[Route('/reservation/{id}/validate', name: 'app_reservation_validate')]
    #[IsGranted('ROLE_ADMIN')]
    public function validateReservation(ReservationRepository $reservationRepository, EntityManagerInterface $em, MailerInterface $mailer, int $id): Response
    {
        $reservation = $reservationRepository->find($id);
        if (!$reservation) {
            throw $this->createNotFoundException('Réservation non trouvée');
        }
        $reservation->setStatus(Reservation::STATUS_VALIDATED);
        $reservation->getActivity()->setGoogleNeedSync(true);
        $em->persist($reservation);
        $em->flush();
        $mail = new TemplatedEmail();
        $mail->from('oxymaux@gmail.com');
        $mail->to($reservation->getDog()->getUser()->getEmail());
        $mail->subject('Votre réservation pour l\'activité "' . $reservation->getActivity()->getService()->getName() . '" a été validée !');
        $mail->htmlTemplate('emails/confirmation_reservation.html.twig');
        $mail->context([
            'reservation' => $reservation,
        ]);
        $mailer->send($mail);
        $this->addFlash('success', 'La réservation de ' . $reservation->getDog()->getFullName() . ', chien de ' . $reservation->getDog()->getUser()->getFirstname() . ' ' . $reservation->getDog()->getUser()->getLastname() . ' a été validée avec succès.');
        return $this->redirectToRoute('app_reservations_admin', ['date' => $reservation->getActivity()->getDate()->format('Y-m-d')]);
    }


    // Permet à un administrateur de refuser une réservation en attente, avec envoi d'une notification par email à l'utilisateur pour l'informer du refus de sa réservation et de la raison du refus si elle a été spécifiée
    #[Route('/reservation/{id}/reject', name: 'app_reservation_reject')]
    #[IsGranted('ROLE_ADMIN')]
    public function rejectReservation(ReservationRepository $reservationRepository, EntityManagerInterface $em, MailerInterface $mailer, Request $request, int $id): Response
    {
        $reservation = $reservationRepository->find($id);
        if (!$reservation) {
            throw $this->createNotFoundException('Réservation non trouvée');
        }
        $reservation->setStatus(Reservation::STATUS_REFUSED);
        $em->persist($reservation);
        $em->flush();
        $reason = $request->request->get('reason');
        $mail = new TemplatedEmail();
        $mail->from('oxymaux@gmail.com');
        $mail->to($reservation->getDog()->getUser()->getEmail());
        $mail->subject('Votre réservation pour l\'activité "' . $reservation->getActivity()->getService()->getName() . '" a été refusée !');
        $mail->htmlTemplate('emails/refus_reservation.html.twig');
        $mail->context([
            'reservation' => $reservation,
            'reason' => $reason,
        ]);
        $mailer->send($mail);
        $this->addFlash('success', 'La réservation de ' . $reservation->getDog()->getFullName() . ', chien de ' . $reservation->getDog()->getUser()->getFirstname() . ' ' . $reservation->getDog()->getUser()->getLastname() . ' a été refusée avec succès.');
        return $this->redirectToRoute('app_reservations_admin', ['date' => $reservation->getActivity()->getDate()->format('Y-m-d')]);
    }


    // Permet à un administrateur d'annuler une réservation validée, avec envoi d'une notification par email à l'utilisateur pour l'informer de l'annulation de sa réservation et de la raison de l'annulation si elle a été spécifiée
    #[Route('/reservation/{id}/cancel', name: 'app_reservation_cancel')]
    #[IsGranted('ROLE_ADMIN')]
    public function cancelReservation(ReservationRepository $reservationRepository, EntityManagerInterface $em, Request $request, MailerInterface $mailer, int $id): Response
    {
        $reservation = $reservationRepository->find($id);
        if (!$reservation) {
            throw $this->createNotFoundException('Réservation non trouvée');
        }
        $reservation->setStatus(Reservation::STATUS_CANCELLED);
        $reservation->getActivity()->setGoogleNeedSync(true);
        $em->persist($reservation);
        $em->flush();
        if ($reservation->getDog()) {
            $reason = $request->request->get('reason');
            $mail = new TemplatedEmail();
            $mail->from('oxymaux@gmail.com');
            $mail->to($reservation->getDog()->getUser()->getEmail());
            $mail->subject('Votre réservation pour l\'activité "' . $reservation->getActivity()->getService()->getName() . '" a été annulée !');
            $mail->htmlTemplate('emails/annulation_reservation.html.twig');
            $mail->context([
                'reservation' => $reservation,
                'reason' => $reason,
            ]);
            $mailer->send($mail);
        }
        $this->addFlash('success', 'La réservation de ' . ($reservation->getDog() ? $reservation->getDog()->getFullName() . ', chien de ' . $reservation->getDog()->getUser()->getFirstname() . ' ' . $reservation->getDog()->getUser()->getLastname() : $reservation->getName()) . ' a été annulée avec succès.');
        return $this->redirectToRoute('app_reservations_admin', ['date' => $reservation->getActivity()->getDate()->format('Y-m-d')]);
    }


    // Permet à un utilisateur d'annuler sa propre réservation validée, avec envoi d'une notification par email à l'utilisateur pour confirmer l'annulation de sa réservation et de la raison de l'annulation si elle a été spécifiée, et envoi d'une notification à l'administrateur pour l'informer de l'annulation de la réservation par l'utilisateur
    #[Route('/reservation/{id}/user-cancel', name: 'app_reservation_user_cancel')]
    #[IsGranted('ROLE_USER')]
    public function userCancelReservation(ReservationRepository $reservationRepository, ActivityGoogleSyncService $activityGoogleSyncService, EntityManagerInterface $em, Request $request, MailerInterface $mailer, int $id): Response
    {
        $reservation = $reservationRepository->find($id);
        if (!$reservation) {
            throw $this->createNotFoundException('Réservation non trouvée');
        }
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }
        if ($reservation->getDog()->getUser()->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException('Vous n\'êtes pas autorisé à annuler cette réservation.');
        }
        $previousStatus = $reservation->getStatus();
        if ($previousStatus !== Reservation::STATUS_VALIDATED && $previousStatus !== Reservation::STATUS_PENDING) {
            $this->addFlash('error', 'Vous ne pouvez annuler que les réservations validées ou en attente de validation.');
            return $this->redirectToRoute('app_reservations');
        }
        $reservation->setStatus(Reservation::STATUS_CANCELLED);
        $em->persist($reservation);
        $em->flush();
        $activityGoogleSyncService->syncUpdate($reservation->getActivity());
        $reason = $request->request->get('reason');

        // Envoyer une notification à Noémie (gérante d'Oxymaux)
        if ($previousStatus === Reservation::STATUS_VALIDATED) {
            $mailToAdmin = new TemplatedEmail();
            $mailToAdmin->from('oxymaux@gmail.com');
            $mailToAdmin->to('oxymaux@gmail.com');
            $mailToAdmin->subject('Une réservation a été annulée par un utilisateur');
            $mailToAdmin->htmlTemplate('emails/notification_annulation.html.twig');
            $mailToAdmin->context([
                'reservation' => $reservation,
                'reason' => $reason,
            ]);
            $mailer->send($mailToAdmin);
        }

        // Envoyer une notification à l'utilisateur pour confirmer l'annulation de sa réservation
        $mailToUser = new TemplatedEmail();
        $mailToUser->from('oxymaux@gmail.com');
        $mailToUser->to($reservation->getDog()->getUser()->getEmail());
        $mailToUser->subject('Votre réservation pour l\'activité "' . $reservation->getActivity()->getService()->getName() . '" a été annulée !');
        $mailToUser->htmlTemplate('emails/annulation_reservation.html.twig');
        $mailToUser->context([
            'reservation' => $reservation,
            'reason' => $reason,
        ]);
        $mailer->send($mailToUser);

        $this->addFlash('success', 'Votre réservation a été annulée avec succès. Vous recevrez une notification par email avec les détails de l\'annulation. Une notification a également été envoyée à Noémie (gérante d\'Oxymaux) pour l\'informer de votre annulation.');
        return $this->redirectToRoute('app_reservations');
    }


    // Permet d'annuler automatiquement une réservation en attente si l'activité associée est passée, sans envoyer de notification à l'utilisateur car la réservation est annulée après la date de l'activité
    #[Route('/reservation/{id}/auto-cancel', name: 'app_reservation_auto_cancel', methods: ['POST'])]
    public function autoCancelReservation(ReservationRepository $reservationRepository, EntityManagerInterface $em, int $id): Response
    {
        $reservation = $reservationRepository->find($id);
        if (!$reservation) {
            throw $this->createNotFoundException('Réservation non trouvée');
        }
        if ($reservation->getStatus() !== Reservation::STATUS_PENDING) {
            throw $this->createAccessDeniedException('Seules les réservations en attente de validation peuvent être annulées automatiquement.');
        }
        $today = new \DateTime();
        $activityDate = $reservation->getActivity()->getDate();
        if ($activityDate->format('Y-m-d') >= $today->format('Y-m-d')) {
            throw $this->createAccessDeniedException('L\'activité associée à cette réservation n\'est pas encore passée. Impossible d\'annuler la réservation automatiquement.');
        }
        $reservation->setStatus(Reservation::STATUS_CANCELLED);
        $em->persist($reservation);
        $em->flush();

        return $this->redirectToRoute('app_reservations');
    }
}
