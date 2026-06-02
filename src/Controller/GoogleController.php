<?php

namespace App\Controller;

use App\Entity\GoogleAccount;
use App\Entity\User;
use App\Repository\ActivityRepository;
use App\Service\ActivityGoogleSyncService;
use App\Service\GoogleReviewsSynchronizer;
use Doctrine\ORM\EntityManagerInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
class GoogleController extends AbstractController
{
    #[Route('/connect/google', name: 'connect_google')]
    public function connect(ClientRegistry $clientRegistry): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        return $clientRegistry->getClient('google')->redirect(
            [
                'https://www.googleapis.com/auth/calendar'
            ],
            [
                'access_type' => 'offline',
                'prompt' => 'consent'
            ]
        );
    }

    #[Route('/connect/google/check', name: 'connect_google_check')]
    public function connectCheck(
        ClientRegistry $clientRegistry,
        EntityManagerInterface $entityManager
    ): Response {
        $client = $clientRegistry->getClient('google');
        $accessToken = $client->getAccessToken();

        /** @var User $admin */
        $admin = $this->getUser();

        $googleAccount = $admin->getGoogleAccount();

        if (!$googleAccount) {
            $googleAccount = new GoogleAccount();
            $googleAccount->setAdmin($admin);
            $admin->setGoogleAccount($googleAccount);

            $entityManager->persist($googleAccount);
        }

        $googleAccount->setAccessToken($accessToken->getToken());

        if ($accessToken->getRefreshToken()) {
            $googleAccount->setRefreshToken($accessToken->getRefreshToken());
        }

        $googleAccount->setExpiresAt(
            (new \DateTimeImmutable())->setTimestamp($accessToken->getExpires())
        );

        $entityManager->flush();

        $this->addFlash('success', 'Google Agenda connecté avec succès');

        return $this->redirectToRoute('app_reservations_admin');
    }

    #[Route('/google/sync-update', name: 'app_google_sync_update', methods: ['POST'])]
    public function syncUpdate(EntityManagerInterface $entityManager, ActivityGoogleSyncService $activityGoogleSyncService, ActivityRepository $activityRepository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $activitiesNeedingSync = $activityRepository->findBy(['googleNeedSync' => true]);

        if (count($activitiesNeedingSync) === 0) {
            $this->addFlash('info', 'Aucune activité ne nécessite une synchronisation avec Google Agenda.');
            return $this->redirectToRoute('app_reservations_admin');
        }

        $successCount = 0;
        $errorCount = 0;
        foreach ($activitiesNeedingSync as $activity) {
            try {
                if (!$activity->getGoogleEventId()) {
                    $activityGoogleSyncService->syncCreate($activity);
                } else {
                    $activityGoogleSyncService->syncUpdate($activity);
                }
                $activity->setGoogleNeedSync(false);
                $successCount++;
            } catch (\Exception $e) {
                $errorCount++;
                $this->addFlash('error', 'Erreur lors de la synchronisation de l\'activité : ' . $activity->getService()->getName());
            }
        }

        $entityManager->flush();

        if ($successCount > 0) {
            $this->addFlash(
                'success',
                $successCount . ' activité(s) synchronisée(s) avec Google Agenda'
            );
        }

        if ($errorCount > 0) {
            $this->addFlash(
                'warning',
                $errorCount . ' activité(s) n\'ont pas pu être synchronisées'
            );
        }

        return $this->redirectToRoute('app_reservations_admin');
    }

    #[Route('/google/sync-reviews', name: 'app_google_sync_reviews')]
    public function syncReviews(EntityManagerInterface $entityManager, GoogleReviewsSynchronizer $googleReviewsSynchronizer): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        try {
            $googleReviewsSynchronizer->syncReviews();
            $entityManager->flush();

            $this->addFlash('success', 'Avis Google synchronisés avec succès');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de la synchronisation des avis Google : ' . $e->getMessage());
        }

        return $this->redirectToRoute('app_home');
    }
}
