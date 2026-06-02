<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class GoogleReviewsService
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $googlePlacesApiKey,
        private string $googlePlaceId,
    ) {}

    public function getReviews(): array
    {
        $url = sprintf(
            'https://places.googleapis.com/v1/places/%s',
            $this->googlePlaceId
        );

        $response = $this->httpClient->request(
            'GET',
            $url,
            [
                'query' => [
                    'languageCode' => 'fr',
                ],
                'headers' => [
                    'X-Goog-Api-Key' => $this->googlePlacesApiKey,
                    'X-Goog-FieldMask' => 'reviews,rating,displayName',
                    'Accept-Language' => 'fr',
                ],
            ]
        );

        $data = $response->toArray();

        return $data['reviews'] ?? [];
    }
}
