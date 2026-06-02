<?php

namespace App\Tests\Controller;

use App\Entity\Reservation;
use App\Repository\ReservationRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class ReservationControllerTest extends WebTestCase
{
    // Ce trait magique permet de tester les envois de mails (assertEmailCount, etc.)
    use \Symfony\Bundle\FrameworkBundle\Test\MailerAssertionsTrait;

    private $client;
    private $entityManager;
    private $reservationRepository;
    private $userRepository;

    protected function setUp(): void
    {
        $this->client = static::createClient();

        // 🔄 Rechargement automatique des fixtures pour garantir une base propre
        $container = static::getContainer();
        $kernel = $container->get('kernel');
        $application = new \Symfony\Bundle\FrameworkBundle\Console\Application($kernel);
        $application->setAutoExit(false);

        $input = new \Symfony\Component\Console\Input\ArrayInput([
            'command' => 'doctrine:fixtures:load',
            '--no-interaction' => true,
            '--env' => 'test'
        ]);
        $output = new \Symfony\Component\Console\Output\NullOutput();
        $application->run($input, $output);

        $this->entityManager = $container->get('doctrine')->getManager();
        $this->reservationRepository = $container->get(ReservationRepository::class);
        $this->userRepository = $container->get(UserRepository::class);
    }

    /**
     * 1. Vérification de la sécurité des routes principales
     */
    public function testReservationRoutesAreSecure(): void
    {
        // Anonyme -> redirection vers login ou 403 sur l'espace utilisateur
        $this->client->request('GET', '/reservations');
        $this->assertTrue($this->client->getResponse()->isRedirect() || $this->client->getResponse()->getStatusCode() === Response::HTTP_FORBIDDEN);

        // Simple utilisateur connecté -> Bloqué (403) sur le panel Admin
        $regularUser = $this->userRepository->findOneBy(['email' => 'user@oxymaux.fr']);
        $this->client->loginUser($regularUser);
        $this->client->request('GET', '/reservationsAdmin');
        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    /**
     * 2. Affichage de la liste des réservations côté Utilisateur
     */
    public function testReservationsUserView(): void
    {
        $regularUser = $this->userRepository->findOneBy(['email' => 'user@oxymaux.fr']);
        $this->client->loginUser($regularUser);

        $this->client->request('GET', '/reservations');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('html'); // Vérifie que Twig compile bien la page sans erreur
    }

    /**
     * 3. Affichage du tableau de bord des réservations côté Admin
     */
    public function testReservationsAdminView(): void
    {
        $adminUser = $this->userRepository->findOneBy(['email' => 'admin@oxymaux.fr']);
        $this->client->loginUser($adminUser);

        $this->client->request('GET', '/reservationsAdmin');
        $this->assertResponseIsSuccessful();
    }

    /**
     * 4. Validation d'une réservation par l'administrateur avec notification e-mail
     */
    public function testValidateReservationByAdmin(): void
    {
        $adminUser = $this->userRepository->findOneBy(['email' => 'admin@oxymaux.fr']);
        $this->client->loginUser($adminUser);

        // 📝 Étape préliminaire : Pour avoir une réservation à valider, on en crée une en statut PENDING
        // (Tu peux aussi l'ajouter directement dans tes AppFixtures si tu préfères)
        $regularUser = $this->userRepository->findOneBy(['email' => 'user@oxymaux.fr']);
        $dog = $regularUser->getDogs()->first();

        // On récupère une activité factice créée par tes fixtures
        $activity = $this->entityManager->getRepository(\App\Entity\Activity::class)->findOneBy([]);
        $this->assertNotNull($activity, "Aucune activité trouvée pour le test. Vérifie tes fixtures.");

        $reservation = new Reservation();
        $reservation->setDog($dog);
        $reservation->setActivity($activity);
        $reservation->setStatus(Reservation::STATUS_PENDING);
        $reservation->setCreatedAt(new \DateTimeImmutable());
        $this->entityManager->persist($reservation);
        $this->entityManager->flush();

        // Execution de l'action de validation
        $this->client->request('GET', sprintf('/reservation/%d/validate', $reservation->getId()));

        // Redirection attendue vers le panel admin filtré par date
        $this->assertResponseRedirects();

        // Rechargement et contrôle du statut en BDD
        $updatedReservation = $this->reservationRepository->find($reservation->getId());
        $this->assertEquals(Reservation::STATUS_VALIDATED, $updatedReservation->getStatus());
        $this->assertTrue($updatedReservation->getActivity()->isGoogleNeedSync());

        // ✉️ Vérification de l'envoi de l'e-mail de confirmation
        $this->assertEmailCount(1);
    }

    /**
     * 5. Refus d'une réservation par l'administrateur avec motif
     */
    public function testRejectReservationByAdmin(): void
    {
        $adminUser = $this->userRepository->findOneBy(['email' => 'admin@oxymaux.fr']);
        $this->client->loginUser($adminUser);

        $regularUser = $this->userRepository->findOneBy(['email' => 'user@oxymaux.fr']);
        $dog = $regularUser->getDogs()->first();
        $activity = $this->entityManager->getRepository(\App\Entity\Activity::class)->findOneBy([]);

        $reservation = new Reservation();
        $reservation->setDog($dog);
        $reservation->setActivity($activity);
        $reservation->setStatus(Reservation::STATUS_PENDING);
        $reservation->setCreatedAt(new \DateTimeImmutable());
        $this->entityManager->persist($reservation);
        $this->entityManager->flush();

        // Simulation d'une requête POST contenant le motif du refus (reason)
        $this->client->request('POST', sprintf('/reservation/%d/reject', $reservation->getId()), [
            'reason' => 'Pas de place disponible pour cette tranche horaire.'
        ]);

        $this->assertResponseRedirects();

        $updatedReservation = $this->reservationRepository->find($reservation->getId());
        $this->assertEquals(Reservation::STATUS_REFUSED, $updatedReservation->getStatus());
        $this->assertEmailCount(1);
    }

    /**
     * 6. Annulation de sa propre réservation par un utilisateur
     */
    public function testUserCanCancelOwnReservation(): void
    {
        $regularUser = $this->userRepository->findOneBy(['email' => 'user@oxymaux.fr']);
        $this->client->loginUser($regularUser);

        $dog = $regularUser->getDogs()->first();
        $activity = $this->entityManager->getRepository(\App\Entity\Activity::class)->findOneBy([]);

        $reservation = new Reservation();
        $reservation->setDog($dog);
        $reservation->setActivity($activity);
        // On la déclare validée pour tester le cas le plus complet (envoi de mail à l'admin + au user)
        $reservation->setStatus(Reservation::STATUS_VALIDATED);
        $reservation->setCreatedAt(new \DateTimeImmutable());
        $this->entityManager->persist($reservation);
        $this->entityManager->flush();

        $this->client->request('POST', sprintf('/reservation/%d/user-cancel', $reservation->getId()), [
            'reason' => 'Empêchement de dernière minute.'
        ]);

        $this->assertResponseRedirects('/reservations');

        $updatedReservation = $this->reservationRepository->find($reservation->getId());
        $this->assertEquals(Reservation::STATUS_CANCELLED, $updatedReservation->getStatus());

        // ✉️ Deux e-mails doivent être partis (un pour Noémie, un pour le client)
        $this->assertEmailCount(2);
    }
}
