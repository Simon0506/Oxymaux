<?php

namespace App\Tests\Controller;

use App\Entity\Animal;
use App\Entity\Category;
use App\Repository\AnimalRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;

class AnimalControllerTest extends WebTestCase
{
    private $client;
    private $entityManager;
    private $animalRepository;
    private $userRepository;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->entityManager = static::getContainer()->get('doctrine')->getManager();
        $this->animalRepository = static::getContainer()->get(AnimalRepository::class);
        $this->userRepository = static::getContainer()->get(UserRepository::class);

        // S'assurer qu'une catégorie et un animal existent pour les tests
        $category = $this->entityManager->getRepository(Category::class)->findOneBy([]);
        if (!$category) {
            $category = new Category();
            $category->setName('Félins');
            $this->entityManager->persist($category);
            $this->entityManager->flush();
        }

        $animal = $this->animalRepository->findOneBy([]);
        if (!$animal) {
            $animal = new Animal();
            $animal->setName('Fripouille');
            $animal->setCategory($category);
            $this->entityManager->persist($animal);
            $this->entityManager->flush();
        }
    }

    /**
     * 1. Test de la page publique de la liste des animaux
     */
    public function testAnimauxPublicPage(): void
    {
        $this->client->request('GET', '/animaux');
        $this->assertResponseIsSuccessful();
    }

    /**
     * 2. Test des restrictions de sécurité (ROLE_ADMIN requis)
     */
    public function testAdminRoutesAreSecure(): void
    {
        $this->client->request('GET', '/animaux/new');
        $this->assertTrue($this->client->getResponse()->isRedirect() || $this->client->getResponse()->getStatusCode() === Response::HTTP_FORBIDDEN);

        $regularUser = $this->userRepository->findOneBy(['email' => 'user@oxymaux.fr']);
        if ($regularUser) {
            $this->client->loginUser($regularUser);
            $this->client->request('GET', '/animaux/new');
            $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        }
    }

    /**
     * 3. Création d'un animal avec une catégorie existante et upload d'image
     */
    public function testNewAnimalWithExistingCategory(): void
    {
        $adminUser = $this->userRepository->findOneBy(['email' => 'admin@oxymaux.fr']);
        $this->client->loginUser($adminUser);

        $category = $this->entityManager->getRepository(Category::class)->findOneBy([]);

        $crawler = $this->client->request('GET', '/animaux/new');
        $this->assertResponseIsSuccessful();

        // 🖼️ GÉNÉRATION D'UNE VRAIE IMAGE VALIDE (1x1 pixel PNG)
        $tempFile = sys_get_temp_dir() . '/fripouille.png';
        $img = imagecreatetruecolor(1, 1);
        imagepng($img, $tempFile);
        imagedestroy($img);

        $photo = new UploadedFile(
            $tempFile,
            'fripouille.png',
            'image/png',
            null,
            true // Mode test actif
        );

        $form = $crawler->filter('form[name="animal"]')->form([
            'animal[name]' => 'Simba',
            'animal[category]' => $category->getId(),
            'animal[newCategory]' => '',
            'animal[image]' => $photo,
        ]);

        $this->client->submit($form);

        // Nettoyage du fichier temporaire après soumission
        if (file_exists($tempFile)) {
            @unlink($tempFile);
        }

        // Vérification en BDD : l'animal doit maintenant exister !
        $newAnimal = $this->animalRepository->findOneBy(['name' => 'Simba']);
        $this->assertNotNull($newAnimal, "Le formulaire a échoué. L'animal Simba n'a pas été créé.");
    }

    /**
     * 4. Création d'un animal avec une NOUVELLE catégorie
     */
    public function testNewAnimalWithNewCategoryCreation(): void
    {
        $adminUser = $this->userRepository->findOneBy(['email' => 'admin@oxymaux.fr']);
        $this->client->loginUser($adminUser);

        // 🧹 NETTOYAGE DE SÉCURITÉ : On supprime l'animal "Rango" s'il traîne d'un ancien test
        $oldAnimal = $this->animalRepository->findOneBy(['name' => 'Rango']);
        if ($oldAnimal) {
            $this->entityManager->remove($oldAnimal);
            $this->entityManager->flush();
        }

        $crawler = $this->client->request('GET', '/animaux/new');
        $this->assertResponseIsSuccessful();

        // On génère l'image pixel temporaire
        $tempFile = sys_get_temp_dir() . '/rango_new.png';
        $img = imagecreatetruecolor(1, 1);
        imagepng($img, $tempFile);
        imagedestroy($img);

        $photo = new UploadedFile(
            $tempFile,
            'rango_new.png',
            'image/png',
            null,
            true
        );

        $uniqueCategoryName = 'NouvelleCat-' . uniqid();

        // On cible précisément le bouton d'envoi
        $buttonCrawler = $crawler->selectButton("Enregistrer l'animal");
        $form = $buttonCrawler->form();

        $this->client->submit($form, [
            'animal[name]' => 'Rango',
            'animal[category]' => '',
            'animal[newCategory]' => $uniqueCategoryName,
            'animal[image]' => $photo,
        ]);

        if (file_exists($tempFile)) {
            @unlink($tempFile);
        }

        // Si ça échoue encore ici, c'est le moment d'afficher l'erreur exacte
        if ($this->client->getResponse()->getStatusCode() === Response::HTTP_OK) {
            $errorCrawler = new \Symfony\Component\DomCrawler\Crawler($this->client->getResponse()->getContent());
            $formErrors = $errorCrawler->filter('.form-error-message, .invalid-feedback, ul li');
            if ($formErrors->count() > 0) {
                echo "\n[Erreur de Validation détectée] : " . $formErrors->first()->text() . "\n";
            }
        }

        $this->assertResponseRedirects('/animaux');

        // Vérifications finales en base de données
        $createdCategory = $this->entityManager->getRepository(Category::class)->findOneBy(['name' => $uniqueCategoryName]);
        $this->assertNotNull($createdCategory, "La catégorie n'a pas été créée en BDD.");

        $animal = $this->animalRepository->findOneBy(['name' => 'Rango']);
        $this->assertNotNull($animal);
        $this->assertEquals($createdCategory->getId(), $animal->getCategory()->getId());
    }

    /**
     * 5. Modification d'un animal existant
     */
    public function testEditAnimal(): void
    {
        $adminUser = $this->userRepository->findOneBy(['email' => 'admin@oxymaux.fr']);
        $this->client->loginUser($adminUser);

        $animal = $this->animalRepository->findOneBy([]);

        $crawler = $this->client->request('GET', sprintf('/animaux/%d/edit', $animal->getId()));
        $this->assertResponseIsSuccessful();

        $form = $crawler->filter('form[name="animal"]')->form([
            'animal[name]' => 'NomModifie',
        ]);

        $this->client->submit($form);
        $this->assertResponseRedirects('/animaux');

        $updatedAnimal = $this->animalRepository->find($animal->getId());
        $this->assertEquals('NomModifie', $updatedAnimal->getName());
    }

    /**
     * 6. Suppression d'un animal
     */
    public function testDeleteAnimal(): void
    {
        $adminUser = $this->userRepository->findOneBy(['email' => 'admin@oxymaux.fr']);
        $this->client->loginUser($adminUser);

        $animal = $this->animalRepository->findOneBy([]);
        $animalId = $animal->getId();

        $this->client->request('GET', sprintf('/animaux/%d/delete', $animalId));
        $this->assertResponseRedirects('/animaux');

        $deletedAnimal = $this->animalRepository->find($animalId);
        $this->assertNull($deletedAnimal);
    }
}
