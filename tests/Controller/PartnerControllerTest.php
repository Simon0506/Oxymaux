<?php

namespace App\Tests\Controller;

use App\Entity\Partner;
use App\Repository\PartnerRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;

class PartnerControllerTest extends WebTestCase
{
    private $client;
    private $entityManager;
    private $partnerRepository;
    private $userRepository;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->entityManager = static::getContainer()->get('doctrine')->getManager();
        $this->partnerRepository = static::getContainer()->get(PartnerRepository::class);
        $this->userRepository = static::getContainer()->get(UserRepository::class);

        // S'assurer qu'au moins un partenaire existe pour tester la modification et la suppression
        $partner = $this->partnerRepository->findOneBy([]);
        if (!$partner) {
            $partner = new Partner();
            $partner->setName('Partenaire Initial');
            // Ajoute ici d'autres set() nécessaires si ton entité Partner a des champs obligatoires
            $this->entityManager->persist($partner);
            $this->entityManager->flush();
        }
    }

    /**
     * Helper pour générer une image pixel PNG valide
     */
    private function generateTestImage(string $filename): UploadedFile
    {
        $tempFile = sys_get_temp_dir() . '/' . $filename;
        $img = imagecreatetruecolor(1, 1);
        imagepng($img, $tempFile);
        imagedestroy($img);

        return new UploadedFile(
            $tempFile,
            $filename,
            'image/png',
            null,
            true // Mode test actif
        );
    }

    /**
     * 1. Test des restrictions de sécurité (ROLE_ADMIN requis sur les routes)
     */
    public function testPartnerRoutesAreSecure(): void
    {
        // Un utilisateur anonyme doit être redirigé ou bloqué (403 / 302 vers login)
        $this->client->request('GET', '/partners/new');
        $this->assertTrue($this->client->getResponse()->isRedirect() || $this->client->getResponse()->getStatusCode() === Response::HTTP_FORBIDDEN);

        // Un utilisateur connecté sans ROLE_ADMIN doit recevoir une 403 Forbidden
        $regularUser = $this->userRepository->findOneBy(['email' => 'user@oxymaux.fr']);
        if ($regularUser) {
            $this->client->loginUser($regularUser);
            $this->client->request('GET', '/partners/new');
            $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        }
    }

    /**
     * 2. Création d'un partenaire avec upload de logo (Rôle Admin requis)
     */
    public function testNewPartner(): void
    {
        $adminUser = $this->userRepository->findOneBy(['email' => 'admin@oxymaux.fr']);
        $this->client->loginUser($adminUser);

        $crawler = $this->client->request('GET', '/partners/new');
        $this->assertResponseIsSuccessful();

        $photo = $this->generateTestImage('logo_partenaire.png');

        // On cible le bouton d'envoi du formulaire (adapte le texte si nécessaire)
        $buttonCrawler = $crawler->selectButton("Enregistrer");
        if ($buttonCrawler->count() === 0) {
            // Repli si le bouton n'a pas ce texte exact : on prend le formulaire par son nom
            $form = $crawler->filter('form[name="partner"]')->form();
        } else {
            $form = $buttonCrawler->form();
        }

        $this->client->submit($form, [
            'partner[name]' => 'SPA de France', // Adapte selon les vrais champs de ton PartnerType
            'partner[logo]' => $photo,
        ]);

        // Nettoyage de l'image temporaire
        if (file_exists($photo->getPathname())) {
            @unlink($photo->getPathname());
        }

        // Le contrôleur redirige vers 'app_home' en cas de succès
        $this->assertResponseRedirects('/');

        // Vérification de la création effective en BDD
        $newPartner = $this->partnerRepository->findOneBy(['name' => 'SPA de France']);
        $this->assertNotNull($newPartner);
        $this->assertNotNull($newPartner->getLogo());
    }

    /**
     * 3. Modification d'un partenaire existant
     */
    public function testEditPartner(): void
    {
        $adminUser = $this->userRepository->findOneBy(['email' => 'admin@oxymaux.fr']);
        $this->client->loginUser($adminUser);

        $partner = $this->partnerRepository->findOneBy([]);

        $crawler = $this->client->request('GET', sprintf('/partners/%d/edit', $partner->getId()));
        $this->assertResponseIsSuccessful();

        $buttonCrawler = $crawler->selectButton("Enregistrer");
        $form = $buttonCrawler->count() > 0 ? $buttonCrawler->form() : $crawler->filter('form[name="partner"]')->form();

        $this->client->submit($form, [
            'partner[name]' => 'Partenaire Modifie',
        ]);

        $this->assertResponseRedirects('/');

        // On recharge l'entité depuis la BDD pour vérifier le changement
        $updatedPartner = $this->partnerRepository->find($partner->getId());
        $this->assertEquals('Partenaire Modifie', $updatedPartner->getName());
    }

    /**
     * 4. Suppression d'un partenaire
     */
    public function testDeletePartner(): void
    {
        $adminUser = $this->userRepository->findOneBy(['email' => 'admin@oxymaux.fr']);
        $this->client->loginUser($adminUser);

        $partner = $this->partnerRepository->findOneBy([]);
        $partnerId = $partner->getId();

        // Appel direct de la route de suppression
        $this->client->request('GET', sprintf('/partners/%d/delete', $partnerId));

        $this->assertResponseRedirects('/');

        // Le partenaire ne doit plus exister en BDD
        $deletedPartner = $this->partnerRepository->find($partnerId);
        $this->assertNull($deletedPartner);
    }
}
