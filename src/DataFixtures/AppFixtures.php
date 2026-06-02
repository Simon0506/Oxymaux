<?php

namespace App\DataFixtures;

use App\Entity\Dog;
use App\Entity\Service;
use App\Entity\User;
use App\Entity\Category;
use App\Entity\Animal;
use App\Entity\PriceKm;
use App\Entity\Activity; // 💡 Ajout de l'entité Activity
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    private UserPasswordHasherInterface $passwordHasher;

    public function __construct(UserPasswordHasherInterface $passwordHasher)
    {
        $this->passwordHasher = $passwordHasher;
    }

    public function load(ObjectManager $manager): void
    {
        // ---------------------------------------------------------
        // 1. CRÉATION DES UTILISATEURS
        // ---------------------------------------------------------

        $user = new User();
        $user->setEmail('user@oxymaux.fr');
        $user->setRoles(['ROLE_USER']);
        $user->setFirstname('John');
        $user->setLastname('Doe');
        $user->setPassword($this->passwordHasher->hashPassword($user, 'password123'));
        $manager->persist($user);

        $otherUser = new User();
        $otherUser->setEmail('other_user@oxymaux.fr');
        $otherUser->setRoles(['ROLE_USER']);
        $otherUser->setFirstname('Jane');
        $otherUser->setLastname('Smith');
        $otherUser->setPassword($this->passwordHasher->hashPassword($otherUser, 'password123'));
        $manager->persist($otherUser);

        $admin = new User();
        $admin->setEmail('admin@oxymaux.fr');
        $admin->setRoles(['ROLE_ADMIN']);
        $admin->setFirstname('Boss');
        $admin->setLastname('Oxymaux');
        $admin->setPassword($this->passwordHasher->hashPassword($admin, 'password123'));
        $manager->persist($admin);

        $otherAdmin = new User();
        $otherAdmin->setEmail('other_admin@oxymaux.fr');
        $otherAdmin->setRoles(['ROLE_ADMIN']);
        $otherAdmin->setFirstname('Assistant');
        $otherAdmin->setLastname('Admin');
        $otherAdmin->setPassword($this->passwordHasher->hashPassword($otherAdmin, 'password123'));
        $manager->persist($otherAdmin);

        // ---------------------------------------------------------
        // 2. CRÉATION DES CHIENS
        // ---------------------------------------------------------

        $dog = new Dog();
        $dog->setName('Rex');
        $dog->setRace('Berger Allemand');
        $dog->setSexe('M');
        $dog->setDateOfBirth(\DateTime::createFromFormat('!Y-m-d', '2022-03-15'));
        $dog->setUser($user);
        $manager->persist($dog);

        // ---------------------------------------------------------
        // 3. CRÉATION DE SERVICES ET ACTIVITÉS FACTICES
        // ---------------------------------------------------------

        $services = [];
        for ($i = 1; $i <= 3; $i++) {
            $service = new Service();
            $service->setName('Service numéro ' . $i);
            $service->setDescription('Description du service ' . $i);
            if (method_exists($service, 'setPosition')) {
                $service->setPosition($i);
            }
            $manager->persist($service);
            $services[] = $service;
        }

        // 🔄 Ajout d'une activité liée au dernier service pour le bon fonctionnement des tests
        $activity = new Activity();
        $activity->setService($services[0]);
        $activity->setDate(new \DateTime('+2 days')); // Une date future pour éviter l'auto-cancel
        $activity->setStart(new \DateTime('14:00:00'));
        $activity->setNbPlaces(5);
        $activity->setOpenToAll(false);
        $activity->setGoogleNeedSync(false);
        $manager->persist($activity);

        // ---------------------------------------------------------
        // 4. CRÉATION DES CATÉGORIES ET ANIMAUX (Pour AnimalControllerTest)
        // ---------------------------------------------------------

        $categoryFelins = new Category();
        $categoryFelins->setName('Félins');
        $manager->persist($categoryFelins);

        $categoryCanides = new Category();
        $categoryCanides->setName('Canidés');
        $manager->persist($categoryCanides);

        $animalInitial = new Animal();
        $animalInitial->setName('Simba');
        $animalInitial->setCategory($categoryFelins);
        $animalInitial->setImage('simba.png');
        $manager->persist($animalInitial);

        // ---------------------------------------------------------
        // 5. CRÉATION DES FRAIS KILOMÉTRIQUES (Pour PriceKmControllerTest)
        // ---------------------------------------------------------

        $priceKm = new PriceKm();
        $priceKm->setMinLength(0);
        $priceKm->setMaxLength(20);
        $priceKm->setPrice(0.65); // Exemple de tarif : 0.65€/km pour la tranche 0-20km
        $manager->persist($priceKm);

        // ---------------------------------------------------------
        // ENREGISTREMENT GLOBAL
        // ---------------------------------------------------------
        $manager->flush();
    }
}
