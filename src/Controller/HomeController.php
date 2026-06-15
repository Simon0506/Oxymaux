<?php

namespace App\Controller;

use App\Entity\Dog;
use App\Entity\Reservation;
use App\Entity\User;
use App\Form\DogType;
use App\Form\ProfileType;
use App\Form\UpdatePasswordType;
use App\Repository\ActivityRepository;
use App\Repository\DogRepository;
use App\Repository\GoogleReviewRepository;
use App\Repository\PartnerRepository;
use App\Repository\ReservationRepository;
use App\Repository\ServiceRepository;
use App\Repository\UserRepository;
use App\Service\AccountDeletionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Exception\InvalidCsrfTokenException;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(ServiceRepository $serviceRepository, PartnerRepository $partnerRepository, GoogleReviewRepository $googleReviewRepository): Response
    {
        $services = $serviceRepository->sortByPosition();
        $partners = $partnerRepository->findAll();
        $reviews = $googleReviewRepository->findBy([], ['publishTime' => 'DESC'], 4);
        return $this->render('home/home.html.twig', [
            'services' => $services,
            'partners' => $partners,
            'reviews' => $reviews,
        ]);
    }

    #[Route('/parcours', name: 'app_parcours')]
    public function parcours(): Response
    {
        return $this->render('home/parcours.html.twig');
    }

    #[Route('/planning/{month}', name: 'app_planning', requirements: ['month' => '\d{4}-\d{2}'], defaults: ['month' => null])]
    public function planning(?string $month, ActivityRepository $activityRepository, ServiceRepository $serviceRepository): Response
    {
        // 📅 Mois courant ou mois fourni dans l'URL
        $currentMonth = $month
            ? \DateTimeImmutable::createFromFormat('Y-m', $month)
            : new \DateTimeImmutable('first day of this month');

        // Sécurité si format invalide
        if (!$currentMonth) {
            throw $this->createNotFoundException('Mois invalide');
        }

        // 📌 Premier jour du mois
        $firstDayOfMonth = $currentMonth->modify('first day of this month');

        // 📌 Nombre de jours du mois
        $daysInMonth = (int) $currentMonth->format('t');

        // 📌 Jour de début du mois
        // 1 = lundi / 7 = dimanche
        $startingDay = (int) $firstDayOfMonth->format('N');

        // ⬅️➡️ Navigation
        $previousMonth = $currentMonth->modify('-1 month');
        $nextMonth = $currentMonth->modify('+1 month');

        // Activités du mois à afficher
        $activities = $activityRepository->findByMonth($currentMonth->format('Y-m'));
        $services = [];
        foreach ($activities as $activity) {
            $service = $activity->getService();
            if ($service && !in_array($service, $services)) {
                $services[] = $service;
            }
        }

        // Activités réservations complètes
        $fullActivities = [];
        foreach ($activities as $activity) {
            $reservations = array_filter($activity->getReservations()->toArray(), function (Reservation $reservation) {
                return $reservation->getStatus() === Reservation::STATUS_VALIDATED;
            });
            if ($activity->isOpenToAll() !== null && $activity->getNbPlaces() === count($reservations)) {
                $fullActivities[] = $activity->getId();
            }
        }


        // Prochaines dates d'activités pour la barre latérale
        $upcomingActivities = [];
        $allServices = $serviceRepository->findAll();
        foreach ($allServices as $service) {
            $upcomingActivity = $activityRepository->findUpcomingActivityByService($service->getId());
            $upcomingActivities[$service->getId()] = $upcomingActivity;
        }

        return $this->render('home/planning.html.twig', [
            'currentMonth' => $currentMonth,
            'previousMonth' => $previousMonth,
            'nextMonth' => $nextMonth,
            'daysInMonth' => $daysInMonth,
            'startingDay' => $startingDay,
            'activities' => $activities,
            'services' => $services,
            'allServices' => $allServices,
            'upcomingActivities' => $upcomingActivities,
            'fullActivities' => $fullActivities
        ]);
    }

    #[Route('/contact', name: 'app_contact', methods: ['GET', 'POST'])]
    public function contact(UserRepository $userRepository, Request $request, MailerInterface $mailer): Response
    {
        $admin = $userRepository->findAdmin();
        $name = $request->request->get('name');
        $email = $request->request->get('email');
        $subject = $request->request->get('subject');
        $message = $request->request->get('message');

        if ($name && $email && $subject && $message) {
            $mail = new Email();
            $mail->from($email);
            $mail->to('oxymaux@gmail.com');
            $mail->subject('Nouveau message de contact : ' . $subject);
            $mail->text("Vous avez reçu un nouveau message de contact.\n\nNom : $name\nEmail : $email\nSujet : $subject\nMessage : $message");
            $mailer->send($mail);
            $this->addFlash('success', 'Votre message a été envoyé avec succès !');
            return $this->redirectToRoute('app_contact');
        }
        return $this->render('home/contact.html.twig', [
            'admin' => $admin,
        ]);
    }

    #[Route('/account', name: 'app_account')]
    #[IsGranted('ROLE_USER')]
    public function account(Request $request, EntityManagerInterface $em, UserPasswordHasherInterface $passwordHasher): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }
        $passwordForm = $this->createForm(UpdatePasswordType::class, $user);
        $passwordForm->handleRequest($request);
        $profileForm = $this->createForm(ProfileType::class, $user);
        $profileForm->handleRequest($request);

        if ($profileForm->isSubmitted() && $profileForm->isValid()) {
            $em->persist($user);
            $em->flush();
            $this->addFlash('success', 'Vos informations ont été mises à jour avec succès !');
            return $this->redirectToRoute('app_account');
        } else if ($profileForm->isSubmitted() && !$profileForm->isValid()) {
            $this->addFlash('error', 'Une erreur est survenue lors de la mise à jour de vos informations. Veuillez vérifier les champs et réessayer.');
            return $this->redirectToRoute('app_account');
        }

        if ($passwordForm->isSubmitted()) {
            $currentPassword = $passwordForm->get('currentPassword')->getData();
            if (!$passwordHasher->isPasswordValid($user, $currentPassword)) {
                $passwordForm->get('currentPassword')->addError(new FormError('Le mot de passe actuel est incorrect.'));
            }

            if ($passwordForm->isValid()) {
                $newPassword = $passwordForm->get('newPassword')->getData();
                $hashedPassword = $passwordHasher->hashPassword($user, $newPassword);
                $user->setPassword($hashedPassword);
                $em->persist($user);
                $em->flush();
                $this->addFlash('success', 'Votre mot de passe a été mis à jour avec succès !');
                return $this->redirectToRoute('app_account');
            }
        }

        $newDog = new Dog();
        $dogForm = $this->createForm(DogType::class, $newDog);
        $dogForm->handleRequest($request);
        if ($dogForm->isSubmitted() && $dogForm->isValid()) {
            $newDog->setUser($user);
            $photoFile = $dogForm->get('photo')->getData();
            if ($photoFile && $photoFile->isValid()) {
                $extension = $photoFile->guessExtension() ?? $photoFile->getClientOriginalExtension();
                if (in_array(strtolower($extension), ['jpg', 'jpeg', 'png', 'gif'])) {
                    $originalFilename = pathinfo($photoFile->getClientOriginalName(), PATHINFO_FILENAME);
                    $safeFilename = transliterator_transliterate('Any-Latin; Latin-ASCII; [^A-Za-z0-9_] remove; Lower()', $originalFilename);
                    $newFilename = $safeFilename . '-' . uniqid() . '.' . strtolower($extension);
                    try {
                        $photoFile->move(
                            $this->getParameter('images_directory'),
                            $newFilename
                        );
                        $newDog->setPhoto($newFilename);
                    } catch (FileException $e) {
                        $this->addFlash('error', 'Une erreur est survenue lors du téléchargement de la photo du chien. Veuillez réessayer.');
                    }
                } else {
                    $this->addFlash('error', 'La photo du chien doit être une image (jpg, jpeg, png, gif).');
                }
            }
            $em->persist($newDog);
            $em->flush();
            $this->addFlash('success', 'Votre compagnon a été ajouté avec succès !');
            return $this->redirectToRoute('app_account');
        } else if ($dogForm->isSubmitted() && !$dogForm->isValid()) {
            $this->addFlash('error', 'Une erreur est survenue lors de l\'ajout de votre compagnon. Veuillez vérifier les champs et réessayer.');
            return $this->redirectToRoute('app_account');
        }

        return $this->render('home/account.html.twig', [
            'user' => $user,
            'passwordForm' => $passwordForm->createView(),
            'profileForm' => $profileForm->createView(),
            'dogForm' => $dogForm->createView(),
        ]);
    }

    #[Route('/delete-account', name: 'app_delete_account', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function deleteAccount(
        Request $request,
        TokenStorageInterface $tokenStorage,
        AccountDeletionService $accountDeletionService
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        // 1. Validation du jeton CSRF de sécurité
        $submittedToken = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('delete_account_' . $user->getId(), $submittedToken)) {
            throw new InvalidCsrfTokenException('Le jeton de sécurité a expiré, veuillez réessayer.');
        }

        // Sécurité : valeur par défaut sur 'keep' si le champ est manquant
        $choice = $request->request->get('reservations', 'keep');

        try {
            // 2. Exécution de la suppression via le Service (isAutomatic = false)
            $accountDeletionService->deleteAccount($user, $choice, false);
        } catch (\Exception $e) {
            $this->addFlash('error', 'Une erreur est survenue lors de la synchronisation. Veuillez retenter la suppression de votre compte. Si le problème persiste, contactez l\'administrateur.');
            return $this->redirectToRoute('app_account');
        }

        // 3. Déconnexion forcée de l'utilisateur de la session courante
        $tokenStorage->setToken(null);
        $request->getSession()->invalidate();

        $this->addFlash('success', 'Votre compte a été supprimé avec succès. Nous sommes désolés de vous voir partir !');
        return $this->redirectToRoute('app_home');
    }

    #[Route('/dog-edit/{id}', name: 'app_dog_edit', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function editDog(Request $request, EntityManagerInterface $em, DogRepository $dogRepository, int $id): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $dog = $dogRepository->find($id);
        if (!$dog) {
            throw $this->createNotFoundException('Chien non trouvé');
        }

        if ($dog->getUser()->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException('Vous n\'avez pas la permission de modifier ce chien');
        }
        $name = $request->request->get('name');
        $race = $request->request->get('race');
        $dateOfBirth = $request->request->get('dateOfBirth');
        $sexe = $request->request->get('sexe');
        if ($name) {
            $dog->setName($name);
            $em->persist($dog);
            $em->flush();
            $this->addFlash('success', 'Le nom de votre compagnon a bien été mis à jour !');
        }
        if ($race) {
            $dog->setRace($race);
            $em->persist($dog);
            $em->flush();
            $this->addFlash('success', 'La race de votre compagnon a bien été mise à jour !');
        }
        if ($dateOfBirth) {
            try {
                $date = new \DateTime($dateOfBirth);
                $dog->setDateOfBirth($date);
                $em->persist($dog);
                $em->flush();
                $this->addFlash('success', 'La date de naissance de votre compagnon a bien été mise à jour !');
            } catch (\Exception $e) {
                $this->addFlash('error', 'La date de naissance est invalide. Veuillez utiliser le format YYYY-MM-DD.');
                return $this->redirectToRoute('app_account');
            }
        }
        if ($sexe) {
            $dog->setSexe($sexe);
            $em->persist($dog);
            $em->flush();
            $this->addFlash('success', 'Le sexe de votre compagnon a bien été mis à jour !');
        }

        /** @var UploadedFile|null $imageFile */
        $imageFile = $request->files->get('photo');

        // On vérifie que le fichier est là ET valide (taille, upload PHP réussi)
        if ($imageFile && $imageFile->isValid()) {

            // 1. Extraction sécurisée de l'extension avant toute manipulation
            $extension = $imageFile->guessExtension() ?? $imageFile->getClientOriginalExtension();

            // Sécurité : On valide l'extension pour éviter l'upload de scripts malveillants
            if (!in_array(strtolower($extension), ['jpg', 'jpeg', 'png', 'gif'])) {
                $this->addFlash('error', 'Le fichier doit être une image (jpg, jpeg, png, gif).');
                return $this->redirectToRoute('app_account');
            }

            // 2. Ton super nettoyage de nom
            $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
            $safeFilename = transliterator_transliterate('Any-Latin; Latin-ASCII; [^A-Za-z0-9_] remove; Lower()', $originalFilename);
            $newFilename = $safeFilename . '-' . uniqid() . '.' . strtolower($extension);

            // 3. Déplacement sécurisé
            try {
                $imageFile->move(
                    $this->getParameter('images_directory'),
                    $newFilename
                );

                $dog->setPhoto($newFilename);
                $em->persist($dog);
                $em->flush();

                $this->addFlash('success', 'La photo de votre compagnon a bien été ajoutée !');
            } catch (FileException $e) {
                $this->addFlash('error', 'Une erreur est survenue lors du téléchargement de la photo. Veuillez réessayer.');
            }
        } elseif ($imageFile && !$imageFile->isValid()) {
            // Si le fichier est présent mais invalide (ex: image de 15Mo alors que PHP est limité à 2Mo)
            $this->addFlash('error', 'Le fichier est trop volumineux ou invalide.');
        }

        return $this->redirectToRoute('app_account');
    }

    #[Route('/dog-delete/{id}', name: 'app_dog_delete')]
    #[IsGranted('ROLE_USER')]
    public function deleteDog(EntityManagerInterface $em, DogRepository $dogRepository, int $id): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $dog = $dogRepository->find($id);
        if (!$dog) {
            throw $this->createNotFoundException('Chien non trouvé');
        }

        if ($dog->getUser()->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException('Vous n\'avez pas la permission de supprimer ce chien');
        }

        $em->remove($dog);
        $em->flush();

        $this->addFlash('success', 'Votre compagnon a été supprimé de la liste avec succès !');
        return $this->redirectToRoute('app_account');
    }

    #[Route('/clients', name: 'app_clients')]
    #[IsGranted('ROLE_ADMIN')]
    public function clients(UserRepository $userRepository): Response
    {
        $users = $userRepository->findAll();
        $clients = [];
        foreach ($users as $user) {
            if (in_array('ROLE_USER', $user->getRoles()) && !in_array('ROLE_ADMIN', $user->getRoles())) {
                $clients[] = $user;
            }
        }
        return $this->render('admin/clients.html.twig', [
            'clients' => $clients,
        ]);
    }

    #[Route('/client/{id}', name: 'admin_client_show')]
    #[IsGranted('ROLE_ADMIN')]
    public function showClient(UserRepository $userRepository, int $id): Response
    {
        $client = $userRepository->find($id);
        if (!$client) {
            throw $this->createNotFoundException('Client non trouvé');
        }
        if (!in_array('ROLE_USER', $client->getRoles()) || in_array('ROLE_ADMIN', $client->getRoles())) {
            throw $this->createNotFoundException('Client non trouvé');
        }
        return $this->render('admin/client_show.html.twig', [
            'client' => $client,
        ]);
    }

    #[Route('/client/{id}/reservations', name: 'admin_client_reservations')]
    #[IsGranted('ROLE_ADMIN')]
    public function clientReservations(UserRepository $userRepository, ReservationRepository $reservationRepository, int $id): Response
    {
        $client = $userRepository->find($id);
        if (!$client) {
            throw $this->createNotFoundException('Client non trouvé');
        }
        if (!in_array('ROLE_USER', $client->getRoles()) || in_array('ROLE_ADMIN', $client->getRoles())) {
            throw $this->createNotFoundException('Client non trouvé');
        }
        $reservations = $reservationRepository->findByUserId($client->getId());
        $reservationsValidated = array_filter($reservations, function (Reservation $reservation) {
            return $reservation->getStatus() === Reservation::STATUS_VALIDATED;
        });
        $activities = [];
        foreach ($reservationsValidated as $reservation) {
            $activity = $reservation->getActivity();
            if ($activity && !in_array($activity, $activities)) {
                $activities[] = $activity;
            }
        }
        return $this->render('admin/client_reservations.html.twig', [
            'client' => $client,
            'reservations' => $reservationsValidated,
            'activities' => $activities,
        ]);
    }
}
