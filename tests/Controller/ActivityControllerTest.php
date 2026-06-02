<?php

namespace App\Tests\Controller;

use App\Entity\Activity;
use App\Entity\Service;
use App\Repository\ActivityRepository;
use App\Repository\UserRepository;
use App\Service\ActivityGoogleSyncService;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class ActivityControllerTest extends WebTestCase
{
    private $client;
    private $entityManager;
    private $activityRepository;
    private $userRepository;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->entityManager = static::getContainer()->get('doctrine')->getManager();
        $this->activityRepository = static::getContainer()->get(ActivityRepository::class);
        $this->userRepository = static::getContainer()->get(UserRepository::class);

        // SÉCURITÉ : Si aucune activité n'existe (car absente des fixtures), on en crée une à la volée pour les tests
        $activity = $this->activityRepository->findOneBy([]);
        if (!$activity) {
            $service = $this->entityManager->getRepository(Service::class)->findOneBy([]);
            if ($service) {
                $activity = new Activity();
                $activity->setService($service);
                $activity->setNbPlaces(6);
                $activity->setDate(new \DateTime('2026-06-15'));
                $activity->setStart(new \DateTime('14:00'));
                $activity->setEnd(new \DateTime('15:00'));
                $activity->setGoogleNeedSync(false);
                $activity->setCanceled(false);

                $this->entityManager->persist($activity);
                $this->entityManager->flush();
            }
        }
    }

    /**
     * 1. Test de la vue détails d'une activité
     */
    public function testShowActivityNotFound(): void
    {
        $this->client->request('GET', '/activity/999999');
        $this->assertEquals(Response::HTTP_NOT_FOUND, $this->client->getResponse()->getStatusCode());
    }

    public function testShowActivitySuccessAnonyme(): void
    {
        $activity = $this->activityRepository->findOneBy([]);
        if (!$activity) {
            $this->markTestSkipped('Impossible de tester : aucune activité disponible.');
        }

        $this->client->request('GET', '/activity/' . $activity->getId());
        $this->assertResponseIsSuccessful();
    }

    /**
     * 2. Test des restrictions d'accès (ROLE_ADMIN requis)
     */
    public function testAdminRoutesAreSecure(): void
    {
        // Test anonyme -> Redirection ou refusé
        $this->client->request('GET', '/newActivity');
        $this->assertTrue($this->client->getResponse()->isRedirect() || $this->client->getResponse()->getStatusCode() === Response::HTTP_FORBIDDEN);

        // Test connecté avec ROLE_USER -> Interdit (403)
        $regularUser = $this->userRepository->findOneBy(['email' => 'user@oxymaux.fr']);
        if (!$regularUser) {
            $this->markTestSkipped('Utilisateur user@oxymaux.fr introuvable.');
        }
        
        $this->client->loginUser($regularUser);
        $this->client->request('GET', '/newActivity');
        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    /**
     * 3. Création d'une activité
     */
    public function testAddActivitySuccess(): void
    {
        $adminUser = $this->userRepository->findOneBy(['email' => 'admin@oxymaux.fr']);
        if (!$adminUser) {
            $this->markTestSkipped('Administrateur introuvable.');
        }
        $this->client->loginUser($adminUser);

        $service = $this->entityManager->getRepository(Service::class)->findOneBy([]);
        if (!$service) {
            $this->markTestSkipped('Aucun service disponible.');
        }

        $crawler = $this->client->request('GET', '/newActivity?date=2026-06-15');
        $this->assertResponseIsSuccessful();

        $form = $crawler->filter('form[name="activity"]')->form([
            'activity[service]' => $service->getId(),
            'activity[nbPlaces]' => 10,
            'activity[start]' => '10:00',
            'activity[end]' => '11:00',
        ]);

        $this->client->submit($form);
        $this->assertResponseRedirects('/reservationsAdmin?date=2026-06-15');
    }

    /**
     * 4. Modification d'une activité SANS changement d'horaires
     */
    public function testEditActivityWithoutChanges(): void
    {
        $adminUser = $this->userRepository->findOneBy(['email' => 'admin@oxymaux.fr']);
        if (!$adminUser) {
            $this->markTestSkipped('Administrateur introuvable.');
        }
        $this->client->loginUser($adminUser);

        $activity = $this->activityRepository->findOneBy([]);
        if (!$activity) {
            $this->markTestSkipped('Aucune activité disponible.');
        }

        $crawler = $this->client->request('GET', sprintf('/activity/%d/edit', $activity->getId()));
        $this->assertResponseIsSuccessful();

        $form = $crawler->filter('form[name="activity"]')->form([
            'activity[service]' => $activity->getService() ? $activity->getService()->getId() : 1,
            'activity[nbPlaces]' => $activity->getNbPlaces(),
            'activity[start]' => $activity->getStart() ? $activity->getStart()->format('H:i') : '10:00',
            'activity[end]' => $activity->getEnd() ? $activity->getEnd()->format('H:i') : '11:00',
        ]);

        $this->client->submit($form);
        $this->assertResponseRedirects();
    }

    /**
     * 5. Modification d'une activité AVEC changement d'horaires
     */
    public function testEditActivityWithTimeChangeAndNotifications(): void
    {
        $adminUser = $this->userRepository->findOneBy(['email' => 'admin@oxymaux.fr']);
        if (!$adminUser) {
            $this->markTestSkipped('Administrateur introuvable.');
        }
        $this->client->loginUser($adminUser);

        $activity = $this->activityRepository->findOneBy([]);
        if (!$activity) {
            $this->markTestSkipped('Aucune activité disponible.');
        }

        $crawler = $this->client->request('GET', sprintf('/activity/%d/edit', $activity->getId()));
        $this->assertResponseIsSuccessful();

        $form = $crawler->filter('form[name="activity"]')->form([
            'activity[service]' => $activity->getService() ? $activity->getService()->getId() : 1,
            'activity[nbPlaces]' => $activity->getNbPlaces(),
            'activity[start]' => '17:00', 
            'activity[end]' => '18:00',
        ]);
        
        $this->client->submit($form);
        $this->assertResponseRedirects();
    }

    /**
     * 6. Suppression d'une activité et Mock du Google Sync Service
     */
    public function testDeleteActivity(): void
    {
        $adminUser = $this->userRepository->findOneBy(['email' => 'admin@oxymaux.fr']);
        if (!$adminUser) {
            $this->markTestSkipped('Administrateur introuvable.');
        }
        $this->client->loginUser($adminUser);

        $activity = $this->activityRepository->findOneBy([]);
        if (!$activity) {
            $this->markTestSkipped('Aucune activité disponible.');
        }
        $activityId = $activity->getId();

        $googleSyncMock = $this->createMock(ActivityGoogleSyncService::class);
        $googleSyncMock->expects($this->once())->method('syncDelete')->with($this->isInstanceOf(Activity::class));
        static::getContainer()->set(ActivityGoogleSyncService::class, $googleSyncMock);

        $this->client->request('POST', sprintf('/activity/%d/delete', $activityId), ['reason' => 'Mauvais temps']);

        $this->assertResponseRedirects();
        
        $deletedActivity = $this->activityRepository->find($activityId);
        $this->assertNull($deletedActivity);
    }

    /**
     * 7. Annulation d'une activité
     */
    public function testCancelActivity(): void
    {
        $adminUser = $this->userRepository->findOneBy(['email' => 'admin@oxymaux.fr']);
        if (!$adminUser) {
            $this->markTestSkipped('Administrateur introuvable.');
        }
        $this->client->loginUser($adminUser);

        $activity = $this->activityRepository->findOneBy([]);
        if (!$activity) {
            $this->markTestSkipped('Aucune activité disponible.');
        }
        
        $this->client->request('POST', sprintf('/activity/%d/cancel', $activity->getId()), ['reason' => 'Absence du moniteur']);
        
        $this->assertResponseRedirects();
        
        $this->entityManager->refresh($activity);
        
        $this->assertTrue($activity->isCanceled() || $activity->getCanceled() === true);
        $this->assertEquals('Absence du moniteur', $activity->getReasonCancel());
        $this->assertTrue($activity->isGoogleNeedSync());
    }
}