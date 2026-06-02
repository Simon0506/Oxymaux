<?php

namespace App\Tests\Controller;

use App\Repository\UserRepository;
use App\Repository\ServiceRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class ServicesControllerTest extends WebTestCase
{
    private function getAdminUser()
    {
        $userRepository = static::getContainer()->get(UserRepository::class);
        return $userRepository->findOneBy(['email' => 'admin@oxymaux.fr']);
    }

    private function createTestImage(): UploadedFile
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'test_img') . '.png';
        $img = imagecreatetruecolor(1, 1);
        imagepng($img, $tmpFile);
        imagedestroy($img);

        return new UploadedFile($tmpFile, 'simba.png', 'image/png', null, true);
    }

    // ---------------------------------------------------------
    // 1. TESTS ACCÈS PUBLIC
    // ---------------------------------------------------------

    public function testServicesPageIsSuccessful(): void
    {
        $client = static::createClient();
        $client->request('GET', '/services');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Mes services');
    }

    // ---------------------------------------------------------
    // 2. TESTS SÉCURITÉ / RESTRICTIONS DROITS
    // ---------------------------------------------------------

    public function testAdminRoutesAreProtectedFromAnonym(): void
    {
        $client = static::createClient();

        $client->request('GET', '/services/new');
        $this->assertResponseStatusCodeSame(302);

        $client->request('GET', '/services/1/edit');
        $this->assertResponseStatusCodeSame(302);

        $client->request('POST', '/services/1/delete');
        $this->assertResponseStatusCodeSame(302);
    }

    // ---------------------------------------------------------
    // 3. TESTS ACTIONS CRÉATION ET MODIFICATION (ADMIN)
    // ---------------------------------------------------------

    public function testAdminCanCreateNewServiceWithFiles(): void
    {
        $client = static::createClient();
        $admin = $this->getAdminUser();
        $client->loginUser($admin);

        $crawler = $client->request('GET', '/services/new');
        $this->assertResponseIsSuccessful();

        $image = $this->createTestImage();

        $form = $crawler->selectButton('Enregistrer le service')->form([
            'service[name]' => 'Nouveau Service Test',
            'service[description]' => 'Une super description de test.',
            'service[image]' => $image,
            'service[logo]' => $image,
        ]);

        $client->submit($form);

        $this->assertResponseRedirects('/services');
        $client->followRedirect();

        // ✅ Vérification textuelle du message flash de création
        $this->assertSelectorTextContains('body', 'Le service a été créé avec succès.');
    }

    public function testAdminCanEditService(): void
    {
        $client = static::createClient();
        $admin = $this->getAdminUser();
        $client->loginUser($admin);

        $serviceRepository = static::getContainer()->get(ServiceRepository::class);
        $service = $serviceRepository->findOneBy([]);

        $this->assertNotNull($service, 'Il doit y avoir au moins un service issu des Fixtures.');

        $crawler = $client->request('GET', sprintf('/services/%d/edit', $service->getId()));
        $this->assertResponseIsSuccessful();

        $form = $crawler->selectButton('Enregistrer les modifications')->form([
            'service[name]' => 'Service Modifié',
        ]);

        $client->submit($form);

        $this->assertResponseRedirects('/services');
        $client->followRedirect();

        // ✅ Vérification textuelle du message flash de modification
        $this->assertSelectorTextContains('body', 'Le service a été mis à jour avec succès.');
    }

    // ---------------------------------------------------------
    // 4. TESTS TRI ET SUPPRESSION
    // ---------------------------------------------------------

    public function testAdminCanSortServices(): void
    {
        $client = static::createClient();
        $admin = $this->getAdminUser();
        $client->loginUser($admin);

        $serviceRepository = static::getContainer()->get(ServiceRepository::class);
        $services = $serviceRepository->findAll();

        $postData = ['services' => []];
        foreach ($services as $index => $service) {
            $postData['services'][$service->getId()] = ['position' => $index + 1];
        }

        $client->request('POST', '/services/sort', $postData);

        $this->assertResponseRedirects('/services');
        $client->followRedirect();

        // ✅ Vérification textuelle du message flash de tri
        $this->assertSelectorTextContains('body', 'Les services ont été réorganisés avec succès.');
    }

    public function testAdminCanDeleteService(): void
    {
        $client = static::createClient();
        $admin = $this->getAdminUser();
        $client->loginUser($admin);

        $serviceRepository = static::getContainer()->get(ServiceRepository::class);
        $service = $serviceRepository->findOneBy([]);

        $this->assertNotNull($service, 'Le service de test doit exister');

        // 💡 On sauvegarde l'ID avant la suppression car l'objet va perdre son ID en BDD
        $serviceId = $service->getId();

        $client->request('POST', sprintf('/services/%d/delete', $serviceId));

        $this->assertResponseRedirects('/services');
        $client->followRedirect();

        $this->assertSelectorTextContains('body', 'Le service a été supprimé avec succès.');

        // 💡 On utilise la variable $serviceId isolée pour vérifier la suppression
        $deletedService = $serviceRepository->find($serviceId);
        $this->assertNull($deletedService);
    }
}
