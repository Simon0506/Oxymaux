<?php

namespace App\Tests\Controller;

use App\Repository\PriceKmRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class PriceKmControllerTest extends WebTestCase
{
    private $client;
    private $entityManager;
    private $priceKmRepository;
    private $userRepository;

    protected function setUp(): void
    {
        $this->client = static::createClient();

        // 🔄 Rechargement automatique des fixtures pour garantir une base propre
        $container = static::getContainer();
        $kernel = $container->get('kernel');

        // 💡 ICI : Utilisation de la classe de Console spécifique à FrameworkBundle
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
        $this->priceKmRepository = $container->get(PriceKmRepository::class);
        $this->userRepository = $container->get(UserRepository::class);
    }

    /**
     * 1. Test des restrictions de sécurité (ROLE_ADMIN requis)
     */
    public function testPriceKmRoutesAreSecure(): void
    {
        // Utilisateur anonyme -> Redirection ou 403 Forbidden
        $this->client->request('GET', '/price-km/add');
        $this->assertTrue($this->client->getResponse()->isRedirect() || $this->client->getResponse()->getStatusCode() === Response::HTTP_FORBIDDEN);

        // Simple utilisateur connecté -> 403 Forbidden
        $regularUser = $this->userRepository->findOneBy(['email' => 'user@oxymaux.fr']);
        if ($regularUser) {
            $this->client->loginUser($regularUser);
            $this->client->request('GET', '/price-km/add');
            $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        }
    }

    /**
     * 2. Ajout de frais de déplacement (Rôle Admin)
     */
    public function testAddPriceKm(): void
    {
        $adminUser = $this->userRepository->findOneBy(['email' => 'admin@oxymaux.fr']);
        $this->client->loginUser($adminUser);

        $crawler = $this->client->request('GET', '/price-km/add');
        $this->assertResponseIsSuccessful();

        // Récupération du formulaire par son nom HTML généré par PriceKmType
        $form = $crawler->filter('form[name="price_km"]')->form();

        $this->client->submit($form, [
            'price_km[minLength]' => 21,
            'price_km[maxLength]' => 50,
            'price_km[price]'     => 0.85,
        ]);

        // Redirection attendue vers la page des services
        $this->assertResponseRedirects('/services');

        // Vérification de la création effective en base de données
        $newPriceKm = $this->priceKmRepository->findOneBy(['minLength' => 21, 'maxLength' => 50]);
        $this->assertNotNull($newPriceKm);
        $this->assertEquals(0.85, $newPriceKm->getPrice());
    }

    /**
     * 3. Modification de frais de déplacement existants
     */
    public function testEditPriceKm(): void
    {
        $adminUser = $this->userRepository->findOneBy(['email' => 'admin@oxymaux.fr']);
        $this->client->loginUser($adminUser);

        // On récupère la tranche (0-20km) injectée par les fixtures
        $priceKm = $this->priceKmRepository->findOneBy([]);
        $this->assertNotNull($priceKm, "Aucun tarif kilométrique trouvé dans les fixtures.");

        $crawler = $this->client->request('GET', sprintf('/price-km/%d/edit', $priceKm->getId()));
        $this->assertResponseIsSuccessful();

        $form = $crawler->filter('form[name="price_km"]')->form();

        $this->client->submit($form, [
            'price_km[minLength]' => 0,
            'price_km[maxLength]' => 25, // Modification de 20 à 25
            'price_km[price]'     => 0.70, // Modification de 0.65 à 0.70
        ]);

        $this->assertResponseRedirects('/services');

        // On recharge l'entité depuis la base pour s'assurer des modifications
        $updatedPriceKm = $this->priceKmRepository->find($priceKm->getId());
        $this->assertEquals(25, $updatedPriceKm->getMaxLength());
        $this->assertEquals(0.70, $updatedPriceKm->getPrice());
    }

    /**
     * 4. Suppression de frais de déplacement
     */
    public function testDeletePriceKm(): void
    {
        $adminUser = $this->userRepository->findOneBy(['email' => 'admin@oxymaux.fr']);
        $this->client->loginUser($adminUser);

        // On prend l'unique élément généré par les fixtures
        $priceKm = $this->priceKmRepository->findOneBy([]);
        $priceKmId = $priceKm->getId();

        // Appel direct de l'URL de suppression
        $this->client->request('GET', sprintf('/price-km/%d/delete', $priceKmId));

        $this->assertResponseRedirects('/services');

        // L'enregistrement doit avoir disparu de la base de données
        $deletedPriceKm = $this->priceKmRepository->find($priceKmId);
        $this->assertNull($deletedPriceKm);
    }
}
