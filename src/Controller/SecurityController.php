<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    #[Route(path: '/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils, Request $request): Response
    {
        $referer = $request->headers->get('referer');

        // Pages à ignorer
        $ignoredRoutes = [
            '/login',
            '/logout',
            '/register',
        ];

        if ($referer) {

            $shouldStore = true;

            foreach ($ignoredRoutes as $route) {
                if (str_contains($referer, $route)) {
                    $shouldStore = false;
                    break;
                }
            }

            if ($shouldStore) {
                $request->getSession()->set(
                    'previous_page',
                    $referer
                );
            }
        }
        // get the login error if there is one
        $error = $authenticationUtils->getLastAuthenticationError();

        // last username entered by the user
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('security/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
        ]);
    }

    #[Route(path: '/logout', name: 'app_logout')]
    public function logout(): void
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }
}
