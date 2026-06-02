<?php

namespace App\Tests\Controller;

use App\Entity\Dog;
use App\Repository\UserRepository;
use App\Repository\DogRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class HomeControllerTest extends WebTestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();

        // 1. On s'assure d'abord de nettoyer l'EntityManager s'il a été démarré
        if (self::$kernel !== null) {
            $container = static::getContainer();
            if ($container->has('doctrine')) {
                $container->get('doctrine')->getManager()->clear();
            }
        }

        // 2. LA CLÉ : On éteint proprement le Kernel pour le test suivant
        static::ensureKernelShutdown();
    }

    // ==========================================
    // 1. PAGES PUBLIQUES
    // ==========================================

    public function testIndexPageIsSuccessful(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');
        $this->assertResponseIsSuccessful();
    }

    public function testParcoursPageIsSuccessful(): void
    {
        $client = static::createClient();
        $client->request('GET', '/parcours');
        $this->assertResponseIsSuccessful();
    }

    public function testPlanningPageWithAndWithoutMonth(): void
    {
        $client = static::createClient();

        $client->request('GET', '/planning');
        $this->assertResponseIsSuccessful();

        $client->request('GET', '/planning/2026-06');
        $this->assertResponseIsSuccessful();

        $client->request('GET', '/planning/invalid-month');
        $this->assertResponseStatusCodeSame(404);
    }

    public function testContactFormSendsEmail(): void
    {
        $client = static::createClient();

        // 1. On crée un espion (Mock) pour le MailerInterface
        $mailerMock = $this->createMock(\Symfony\Component\Mailer\MailerInterface::class);

        // On s'attend explicitement à ce que la méthode "send" soit appelée EXACTEMENT 1 fois
        $mailerMock->expects($this->once())
            ->method('send')
            ->with($this->callback(function (\Symfony\Component\Mime\Email $email) {
                // On fait nos assertions directement au moment où le contrôleur appelle le mailer
                $this->assertSame('contact@oxymaux.fr', $email->getTo()[0]->getAddress());
                $this->assertSame('john@example.com', $email->getFrom()[0]->getAddress());
                $this->assertStringContainsString('Nouveau message de contact', $email->getSubject());
                return true;
            }));

        // 2. On injecte de force notre espion dans le conteneur de Symfony
        static::getContainer()->set(\Symfony\Component\Mailer\MailerInterface::class, $mailerMock);

        // 3. On envoie la requête POST brute
        $client->request('POST', '/contact', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'subject' => 'general',
            'message' => 'Bonjour, je souhaiterais avoir des informations.'
        ]);

        // 4. On vérifie la fin du cycle (redirection)
        $this->assertResponseRedirects('/contact');
        $client->followRedirect();
        $this->assertSelectorTextContains('body', 'Votre message a été envoyé avec succès !');
    }
    // ==========================================
    // 2. ESPACE CLIENT (ROLE_USER)
    // ==========================================

    public function testAccountPageIsProtected(): void
    {
        $client = static::createClient();
        $client->request('GET', '/account');
        $this->assertResponseRedirects();
    }

    public function testAccountPageOpenForUser(): void
    {
        $client = static::createClient();
        $userRepository = static::getContainer()->get(UserRepository::class);

        $testUser = $userRepository->findOneBy(['email' => 'user@oxymaux.fr']);
        $client->loginUser($testUser);

        $client->request('GET', '/account');
        $this->assertResponseIsSuccessful();
    }

    public function testAddDogSuccessfully(): void
    {
        $client = static::createClient();
        $userRepository = static::getContainer()->get(UserRepository::class);
        $testUser = $userRepository->findOneBy(['email' => 'user@oxymaux.fr']);
        $client->loginUser($testUser);

        $crawler = $client->request('GET', '/account');

        // On récupère directement le formulaire par sa balise form (puisqu'il y en a trois dans la page, on cible le bon)
        // dogForm génère un attribut name="dog" par défaut sur la balise <form>
        $form = $crawler->filter('form[name="dog"]')->form();

        $photo = new UploadedFile(
            __DIR__ . '/../fixtures/test_dog.jpg',
            'test_dog.jpg',
            'image/jpeg',
            null,
            true
        );

        // On passe les valeurs directement dans le tableau de soumission
        $client->submit($form, [
            'dog[name]' => 'Rex',
            'dog[race]' => 'Berger Allemand',
            'dog[dateOfBirth]' => '2022-01-15',
            'dog[sexe]' => 'Male',
            'dog[photo]' => $photo,
        ]);

        $this->assertResponseRedirects('/account');
    }

    public function testEditDogSecurityAndSuccess(): void
    {
        $client = static::createClient();
        $userRepository = static::getContainer()->get(UserRepository::class);
        $dogRepository = static::getContainer()->get(DogRepository::class);
        $em = static::getContainer()->get(\Doctrine\ORM\EntityManagerInterface::class);

        $testUser = $userRepository->findOneBy(['email' => 'user@oxymaux.fr']);
        $otherUser = $userRepository->findOneBy(['email' => 'other_user@oxymaux.fr']);

        $dog = $dogRepository->findOneBy(['user' => $testUser]);
        if (!$dog) {
            $dog = new Dog();
            $dog->setName('Rex');
            $dog->setRace('Berger');
            $dog->setSexe('M');
            $dog->setUser($testUser);
            $em->persist($dog);
            $em->flush();
        }

        $client->loginUser($otherUser);
        $client->request('POST', '/dog-edit/' . $dog->getId(), ['name' => 'Hacker Name']);
        $this->assertResponseStatusCodeSame(403);

        $client->loginUser($testUser);
        $client->request('POST', '/dog-edit/' . $dog->getId(), [
            'name' => 'NouveauNom'
        ]);

        $this->assertResponseRedirects('/account');
    }

    public function testDeleteDog(): void
    {
        $client = static::createClient();
        $userRepository = static::getContainer()->get(UserRepository::class);
        $dogRepository = static::getContainer()->get(DogRepository::class);
        $em = static::getContainer()->get(\Doctrine\ORM\EntityManagerInterface::class);

        $testUser = $userRepository->findOneBy(['email' => 'user@oxymaux.fr']);

        $dog = $dogRepository->findOneBy(['user' => $testUser]);
        if (!$dog) {
            $dog = new Dog();
            $dog->setName('Rex');
            $dog->setRace('Berger');
            $dog->setSexe('M');
            $dog->setUser($testUser);
            $em->persist($dog);
            $em->flush();
        }

        $dogId = $dog->getId();

        $client->loginUser($testUser);
        $client->request('GET', '/dog-delete/' . $dogId);

        $this->assertResponseRedirects('/account');
        $this->assertNull($dogRepository->find($dogId));
    }

    // ==========================================
    // 3. ESPACE ADMIN (ROLE_ADMIN)
    // ==========================================

    public function testAdminPagesAreProtectedForSimpleUser(): void
    {
        $client = static::createClient();
        $userRepository = static::getContainer()->get(UserRepository::class);

        $testUser = $userRepository->findOneBy(['email' => 'user@oxymaux.fr']);
        $client->loginUser($testUser);

        $client->request('GET', '/clients');
        $this->assertResponseStatusCodeSame(403);

        $client->request('GET', '/client/1');
        $this->assertResponseStatusCodeSame(403);
    }

    public function testClientsPageIsSuccessfulForAdmin(): void
    {
        $client = static::createClient();
        $userRepository = static::getContainer()->get(UserRepository::class);

        $adminUser = $userRepository->findOneBy(['email' => 'admin@oxymaux.fr']);
        $client->loginUser($adminUser);

        $client->request('GET', '/clients');
        $this->assertResponseIsSuccessful();
    }

    public function testShowClientPageWithNonAdminUserTarget(): void
    {
        $client = static::createClient();
        $userRepository = static::getContainer()->get(UserRepository::class);

        $adminUser = $userRepository->findOneBy(['email' => 'admin@oxymaux.fr']);
        $client->loginUser($adminUser);

        $simpleUser = $userRepository->findOneBy(['email' => 'user@oxymaux.fr']);
        $client->request('GET', '/client/' . $simpleUser->getId());
        $this->assertResponseIsSuccessful();

        $otherAdmin = $userRepository->findOneBy(['email' => 'other_admin@oxymaux.fr']);
        if ($otherAdmin) {
            $client->request('GET', '/client/' . $otherAdmin->getId());
            $this->assertResponseStatusCodeSame(404);
        }
    }
}
