<?php

namespace App\Tests\Controller;

use App\Entity\Activity;
use App\Entity\Service;
use App\Repository\ActivityRepository;
use App\Repository\UserRepository;
use App\Service\ActivityGoogleSyncService;
use App\Service\GoogleReviewsSynchronizer;
use Doctrine\ORM\EntityManagerInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use KnpU\OAuth2ClientBundle\Client\Provider\GoogleClient;
use League\OAuth2\Client\Token\AccessToken;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class GoogleControllerTest extends WebTestCase
{
    private function getAdminUser()
    {
        $userRepository = static::getContainer()->get(UserRepository::class);
        return $userRepository->findOneBy(['email' => 'admin@oxymaux.fr']);
    }

    private function createTestImage(): \Symfony\Component\HttpFoundation\File\UploadedFile
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'test_img') . '.png';
        $img = imagecreatetruecolor(1, 1);
        imagepng($img, $tmpFile);
        imagedestroy($img);

        return new \Symfony\Component\HttpFoundation\File\UploadedFile($tmpFile, 'simba.png', 'image/png', null, true);
    }

    // ---------------------------------------------------------
    // 1. TESTS DE SÉCURITÉ (ROLE_ADMIN requis partout)
    // ---------------------------------------------------------

    public function testGoogleRoutesAreProtectedFromAnonym(): void
    {
        $client = static::createClient();

        $client->request('GET', '/connect/google');
        $this->assertResponseStatusCodeSame(302);

        $client->request('GET', '/connect/google/check');
        $this->assertResponseStatusCodeSame(302);

        $client->request('POST', '/google/sync-update');
        $this->assertResponseStatusCodeSame(302);

        $client->request('GET', '/google/sync-reviews');
        $this->assertResponseStatusCodeSame(302);
    }

    // ---------------------------------------------------------
    // 2. TEST CONNEXION INITIALE (OAuth Redirection)
    // ---------------------------------------------------------

    public function testConnectRedirectsToGoogleOAuth(): void
    {
        $client = static::createClient();
        $client->loginUser($this->getAdminUser());

        $googleClientMock = $this->createMock(GoogleClient::class);
        $googleClientMock->expects($this->once())
            ->method('redirect')
            ->with(
                ['https://www.googleapis.com/auth/calendar'],
                ['access_type' => 'offline', 'prompt' => 'consent']
            )
            ->willReturn(new \Symfony\Component\HttpFoundation\RedirectResponse('https://accounts.google.com/o/oauth2/v2/auth'));

        $clientRegistryMock = $this->createMock(ClientRegistry::class);
        $clientRegistryMock->method('getClient')->with('google')->willReturn($googleClientMock);

        static::getContainer()->set(ClientRegistry::class, $clientRegistryMock);

        $client->request('GET', '/connect/google');
        $this->assertResponseRedirects('https://accounts.google.com/o/oauth2/v2/auth');
    }

    // ---------------------------------------------------------
    // 3. TEST RETOUR DE CONNEXION (OAuth Check Callback)
    // ---------------------------------------------------------

    public function testConnectCheckSavesTokenAndRedirects(): void
    {
        $client = static::createClient();
        $admin = $this->getAdminUser();
        $client->loginUser($admin);

        $adminId = $admin->getId();

        $accessTokenMock = $this->createMock(AccessToken::class);
        $accessTokenMock->method('getToken')->willReturn('fake_access_token_123');
        $accessTokenMock->method('getRefreshToken')->willReturn('fake_refresh_token_123');
        $accessTokenMock->method('getExpires')->willReturn(time() + 3600);

        $googleClientMock = $this->createMock(GoogleClient::class);
        $googleClientMock->method('getAccessToken')->willReturn($accessTokenMock);

        $clientRegistryMock = $this->createMock(ClientRegistry::class);
        $clientRegistryMock->method('getClient')->with('google')->willReturn($googleClientMock);

        static::getContainer()->set(ClientRegistry::class, $clientRegistryMock);

        $client->request('GET', '/connect/google/check');

        // ✅ Route mise à jour
        $this->assertResponseRedirects('/reservationsAdmin');
        $client->followRedirect();

        $this->assertSelectorTextContains('body', 'Google Agenda connecté avec succès');

        $userRepository = static::getContainer()->get(UserRepository::class);
        $freshAdmin = $userRepository->find($adminId);

        $this->assertNotNull($freshAdmin->getGoogleAccount());
        $this->assertSame('fake_access_token_123', $freshAdmin->getGoogleAccount()->getAccessToken());
    }

    // ---------------------------------------------------------
    // 4. TESTS SYNCHRONISATION AGENDA
    // ---------------------------------------------------------

    public function testSyncUpdateWhenNoActivitiesNeedSync(): void
    {
        $client = static::createClient();
        $client->loginUser($this->getAdminUser());

        $em = static::getContainer()->get(EntityManagerInterface::class);
        $activities = $em->getRepository(Activity::class)->findAll();
        foreach ($activities as $activity) {
            $activity->setGoogleNeedSync(false);
        }
        $em->flush();

        $client->request('POST', '/google/sync-update');

        // ✅ Route mise à jour
        $this->assertResponseRedirects('/reservationsAdmin');
        $client->followRedirect();
        $this->assertSelectorTextContains('body', 'Aucune activité ne nécessite une synchronisation');
    }

    public function testSyncUpdateExecutesCreationAndUpdates(): void
    {
        $client = static::createClient();
        $client->loginUser($this->getAdminUser());

        $em = static::getContainer()->get(EntityManagerInterface::class);

        $service = $em->getRepository(Service::class)->findOneBy([]);
        if (!$service) {
            $service = new Service();
            $service->setName('Service Test Google');
            $em->persist($service);
        }

        $activity = new Activity();
        $activity->setService($service);
        $activity->setDate(new \DateTime('+5 days'));
        $activity->setStart(new \DateTime('10:00:00'));
        $activity->setNbPlaces(3);
        $activity->setOpenToAll(true);
        $activity->setGoogleNeedSync(true);
        $activity->setGoogleEventId(null);
        $em->persist($activity);
        $em->flush();

        $activityId = $activity->getId();

        $syncServiceMock = $this->createMock(ActivityGoogleSyncService::class);
        $syncServiceMock->expects($this->once())->method('syncCreate');

        static::getContainer()->set(ActivityGoogleSyncService::class, $syncServiceMock);

        $client->request('POST', '/google/sync-update');

        // ✅ Route mise à jour
        $this->assertResponseRedirects('/reservationsAdmin');
        $client->followRedirect();
        $this->assertSelectorTextContains('body', 'activité(s) synchronisée(s) avec Google Agenda');

        $activityRepository = static::getContainer()->get(ActivityRepository::class);
        $freshActivity = $activityRepository->find($activityId);

        $this->assertFalse($freshActivity->isGoogleNeedSync());
    }

    // ---------------------------------------------------------
    // 5. TEST SYNCHRONISATION DES AVIS
    // ---------------------------------------------------------

    public function testSyncReviewsSuccessfully(): void
    {
        $client = static::createClient();
        $client->loginUser($this->getAdminUser());

        $reviewsSyncMock = $this->createMock(GoogleReviewsSynchronizer::class);
        $reviewsSyncMock->expects($this->once())->method('syncReviews');

        static::getContainer()->set(GoogleReviewsSynchronizer::class, $reviewsSyncMock);

        $client->request('GET', '/google/sync-reviews');

        $this->assertResponseRedirects('/');
        $client->followRedirect();
        $this->assertSelectorTextContains('body', 'Avis Google synchronisés avec succès');
    }
}
