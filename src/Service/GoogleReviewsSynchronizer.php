<?php

namespace App\Service;

use App\Entity\GoogleReview;
use App\Repository\GoogleReviewRepository;
use Doctrine\ORM\EntityManagerInterface;

class GoogleReviewsSynchronizer
{
    public function __construct(
        private GoogleReviewsService $googleReviewsService,
        private GoogleReviewRepository $googleReviewRepository,
        private EntityManagerInterface $entityManager,
    ) {}

    public function syncReviews(): void
    {
        $reviews = $this->googleReviewsService->getReviews();

        $googleReviewIds = [];

        foreach ($reviews as $reviewData) {

            $googleReviewId = $reviewData['name'] ?? null;

            if (!$googleReviewId) {
                continue;
            }

            $googleReviewIds[] = $googleReviewId;

            $review = $this
                ->googleReviewRepository
                ->findOneBy([
                    'googleReviewId' => $googleReviewId,
                ]);

            if (!$review) {
                $review = new GoogleReview();

                $review->setGoogleReviewId($googleReviewId);

                $this->entityManager->persist($review);
            }

            $review->setAuthorName(
                $reviewData['authorAttribution']['displayName'] ?? 'Anonyme'
            );

            $review->setRating(
                $reviewData['rating'] ?? 0
            );

            $review->setComment(
                $reviewData['text']['text'] ?? null
            );

            $review->setRelativeTimeDescription(
                $reviewData['relativePublishTimeDescription'] ?? null
            );

            $review->setProfilePhotoUrl(
                $reviewData['authorAttribution']['photoUri'] ?? null
            );

            if (!empty($reviewData['publishTime'])) {
                $review->setPublishTime(
                    new \DateTimeImmutable($reviewData['publishTime'])
                );
            }
        }

        $existingReviews = $this
            ->googleReviewRepository
            ->findAll();

        foreach ($existingReviews as $existingReview) {

            if (!in_array(
                $existingReview->getGoogleReviewId(),
                $googleReviewIds
            )) {
                $this->entityManager->remove($existingReview);
            }
        }

        $this->entityManager->flush();
    }
}
