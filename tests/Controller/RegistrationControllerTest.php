<?php

namespace App\Tests\Controller;

use App\Repository\UserRepository;
use App\Security\EmailVerifier;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface;

class RegistrationControllerTest extends WebTestCase
{
    protected function tearDown(): void
    {
        // 1. On ferme la session si le client existe et que le conteneur est accessible
        if (static::getContainer()->has('session')) {
            $session = static::getContainer()->get('session');
            if ($session->isStarted()) {
                $session->invalidate();
            }
        }

        // 2. Appel du parent pour le nettoyage standard de Symfony
        parent::tearDown();
    }
    // ---------------------------------------------------------
    // 1. ACCÈS ET RENDU DE LA PAGE
    // ---------------------------------------------------------

    public function testRegisterPageIsSuccessful(): void
    {
        $client = static::createClient();
        $client->request('GET', '/register');

        $this->assertResponseIsSuccessful();
        // ✅ Corrigé : On cible le terme réel présent dans ton HTML
        $this->assertSelectorTextContains('body', "S'inscrire");
    }

    // ---------------------------------------------------------
    // 2. SOUMISSION DU FORMULAIRE D'INSCRIPTION
    // ---------------------------------------------------------

    public function testRegistrationSubmitFormAndSendsEmail(): void
    {
        $client = static::createClient();

        // 1. On accède à la page pour générer la session et le jeton
        $crawler = $client->request('GET', '/register');

        // 2. On récupère le VRAI jeton CSRF depuis le champ caché du formulaire
        $csrfToken = $crawler->filter('input[name="registration_form[_token]"]')->attr('value');

        // 3. Préparation des données
        $uniqueEmail = 'test_' . time() . '@oxymaux.fr';

        $data = [
            'registration_form' => [
                'lastName' => 'Doe',
                'firstName' => 'John',
                'address' => '10 rue du Test',
                'postalCode' => '86000',
                'city' => 'Poitiers',
                'phone' => '0600000000',
                'email' => $uniqueEmail,
                'plainPassword' => 'XyZ7!mP9$qR2@kL5_vW8',
                'agreeTerms' => '1',
                '_token' => $csrfToken, // <-- On injecte le VRAI jeton ici
            ]
        ];

        // 4. Soumission
        $client->request('POST', '/register', $data);

        // 5. Assertion
        $this->assertResponseRedirects('/login');
    }

    // ---------------------------------------------------------
    // 3. VÉRIFICATION DE L'EMAIL (CALLBACK VALIDE)
    // ---------------------------------------------------------

    public function testVerifyEmailSuccess(): void
    {
        $client = static::createClient();

        $emailVerifierMock = $this->createMock(EmailVerifier::class);
        $emailVerifierMock->expects($this->once())
            ->method('handleEmailConfirmation');

        static::getContainer()->set(EmailVerifier::class, $emailVerifierMock);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $user = $userRepository->findOneBy(['isVerified' => false]) ?? $userRepository->findOneBy([]);

        $this->assertNotNull($user, 'Un utilisateur doit être disponible pour le test.');

        $client->request('GET', '/verify/email?id=' . $user->getId());

        $this->assertResponseRedirects('/login');
        $client->followRedirect();

        $this->assertSelectorTextContains('body', 'Votre adresse e-mail a été validée.');
    }

    // ---------------------------------------------------------
    // 4. VÉRIFICATION DE L'EMAIL (CAS D'ERREURS)
    // ---------------------------------------------------------

    public function testVerifyEmailWithoutIdRedirectsToRegister(): void
    {
        $client = static::createClient();
        $client->request('GET', '/verify/email');

        $this->assertResponseRedirects('/register');
    }

    public function testVerifyEmailWithInvalidIdRedirectsToRegister(): void
    {
        $client = static::createClient();
        $client->request('GET', '/verify/email?id=999999');

        $this->assertResponseRedirects('/register');
    }

    public function testVerifyEmailWithExpiredOrInvalidSignature(): void
    {
        $client = static::createClient();

        $emailVerifierMock = $this->createMock(EmailVerifier::class);
        $emailVerifierMock->expects($this->once())
            ->method('handleEmailConfirmation')
            ->willThrowException($this->createMock(VerifyEmailExceptionInterface::class));

        static::getContainer()->set(EmailVerifier::class, $emailVerifierMock);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $user = $userRepository->findOneBy([]);

        $client->request('GET', '/verify/email?id=' . $user->getId());

        $this->assertResponseRedirects('/register');
    }
}
