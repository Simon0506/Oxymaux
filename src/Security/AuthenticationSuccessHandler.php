<?php

namespace App\Security;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

class AuthenticationSuccessHandler implements AuthenticationSuccessHandlerInterface
{
    use TargetPathTrait;

    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private EntityManagerInterface $em
    ) {}

    public function onAuthenticationSuccess(
        Request $request,
        TokenInterface $token
    ): ?Response {

        $user = $token->getUser();
        if ($user instanceof User) {
            // On stocke la date de dernière connexion
            $user->setLastLoginAt(new \DateTimeImmutable());
            // On réinitialise la date d'avertissement pour la suppression automatique
            $user->setWarnedForDeletionAt(null);
            $this->em->flush();
        }

        // Cas d'une page sécurisée
        if ($targetPath = $this->getTargetPath(
            $request->getSession(),
            'main'
        )) {
            return new RedirectResponse($targetPath);
        }

        // Cas d'un login volontaire
        $previousPage = $request->getSession()->get('previous_page');

        if ($previousPage) {

            $request->getSession()->remove('previous_page');

            return new RedirectResponse($previousPage);
        }

        // Fallback
        return new RedirectResponse(
            $this->urlGenerator->generate('app_home')
        );
    }
}
