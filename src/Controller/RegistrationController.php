<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationFormType;
use App\Repository\UserRepository;
use App\Security\EmailVerifier;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mime\Address;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface;

class RegistrationController extends AbstractController
{
    public function __construct(private EmailVerifier $emailVerifier) {}

    #[Route('/register', name: 'app_register')]
    public function register(Request $request, UserPasswordHasherInterface $userPasswordHasher, EntityManagerInterface $entityManager): Response
    {
        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var string $plainPassword */
            $plainPassword = $form->get('plainPassword')->getData();

            // encode the plain password
            $user->setPassword($userPasswordHasher->hashPassword($user, $plainPassword));

            $entityManager->persist($user);
            $entityManager->flush();

            // generate a signed url and email it to the user
            $this->emailVerifier->sendEmailConfirmation(
                'app_verify_email',
                $user,
                (new TemplatedEmail())
                    ->from(new Address('contact@oxymaux17.com', 'Oxymaux'))
                    ->to((string) $user->getEmail())
                    ->subject('Veuillez confirmer votre adresse e-mail')
                    ->htmlTemplate('registration/confirmation_email.html.twig')
            );

            // do anything else you need here, like send an email

            $this->addFlash('success', 'Vous allez recevoir un e-mail de confirmation pour valider votre adresse e-mail.');
            return $this->redirectToRoute('app_login');
        }

        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form,
        ]);
    }

    #[Route('/verify/email', name: 'app_verify_email')]
    public function verifyUserEmail(Request $request, TranslatorInterface $translator, UserRepository $userRepository): Response
    {
        $id = $request->query->get('id');

        if (null === $id) {
            return $this->redirectToRoute('app_register');
        }

        $user = $userRepository->find($id);

        if (null === $user) {
            return $this->redirectToRoute('app_register');
        }

        // validate email confirmation link, sets User::isVerified=true and persists
        try {
            $this->emailVerifier->handleEmailConfirmation($request, $user);
        } catch (VerifyEmailExceptionInterface $exception) {
            $this->addFlash('verify_email_error', $translator->trans($exception->getReason(), [], 'VerifyEmailBundle'));

            return $this->redirectToRoute('app_register');
        }

        // @TODO Change the redirect on success and handle or remove the flash message in your templates
        $this->addFlash('success', 'Votre adresse e-mail a été validée.');

        return $this->redirectToRoute('app_login');
    }

    #[Route('/verify/resend', name: 'app_resend_verification', methods: ['GET', 'POST'])]
    public function resendVerification(Request $request, UserRepository $userRepository, CacheInterface $cache): Response
    {
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('resend_verification', $request->request->get('_token'))) {
                throw new \Symfony\Component\Security\Core\Exception\InvalidCsrfTokenException('Le jeton CSRF est invalide.');
            }

            $email = strtolower(trim((string) $request->request->get('email', '')));
            $rateLimitKey = 'resend_verification_' . hash('sha256', (string) ($request->getClientIp() ?? 'unknown'));
            $now = time();
            $lastRequestAt = $cache->get($rateLimitKey, static function (ItemInterface $item): int {
                $item->expiresAfter(3600);
                return 0;
            });

            if (!is_int($lastRequestAt) || $lastRequestAt <= $now - 60) {
                $cache->delete($rateLimitKey);
                $cache->get($rateLimitKey, static function (ItemInterface $item) use ($now): int {
                    $item->expiresAfter(3600);
                    return $now;
                });

                $user = filter_var($email, FILTER_VALIDATE_EMAIL) ? $userRepository->findOneBy(['email' => $email]) : null;
                if ($user instanceof User && !$user->isVerified()) {
                    $this->emailVerifier->sendEmailConfirmation(
                        'app_verify_email',
                        $user,
                        (new TemplatedEmail())
                            ->from(new Address('contact@oxymaux17.com', 'Oxymaux'))
                            ->to((string) $user->getEmail())
                            ->subject('Veuillez confirmer votre adresse e-mail')
                            ->htmlTemplate('registration/confirmation_email.html.twig')
                    );
                }
            }

            $this->addFlash('success', 'Si cette adresse correspond à un compte non confirmé, un nouvel e-mail de confirmation vient d’être envoyé.');
            return $this->redirectToRoute('app_resend_verification');
        }

        return $this->render('registration/resend_verification.html.twig');
    }
}
