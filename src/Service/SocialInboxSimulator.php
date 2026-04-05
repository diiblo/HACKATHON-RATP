<?php

namespace App\Service;

class SocialInboxSimulator
{
    public function getFeed(): array
    {
        return [
            [
                'id' => 'x-001',
                'platform' => 'X',
                'author' => '@voyageur_72',
                'language' => 'fr',
                'line' => '72',
                'vehicle' => '4589',
                'stop' => 'Nation',
                'publishedAt' => '2026-04-03T08:35:00+02:00',
                'title' => 'Signalement social ligne 72',
                'content' => 'Le conducteur du bus 72 a fermé les portes alors que plusieurs personnes descendaient encore.',
                'severity' => 'grave',
            ],
            [
                'id' => 'ig-002',
                'platform' => 'Instagram',
                'author' => '@buswatch_idf',
                'language' => 'en',
                'line' => '91',
                'vehicle' => '9134',
                'stop' => 'Montparnasse',
                'publishedAt' => '2026-04-03T09:10:00+02:00',
                'title' => 'Crowded bus complaint',
                'content' => 'Driver ignored accessibility request on line 91 and left the stop too fast.',
                'severity' => 'moyen',
            ],
            [
                'id' => 'fb-003',
                'platform' => 'Facebook',
                'author' => 'Client Mystère IDF',
                'language' => 'es',
                'line' => '183',
                'vehicle' => '7712',
                'stop' => 'Choisy',
                'publishedAt' => '2026-04-03T11:05:00+02:00',
                'title' => 'Cliente molesta en la linea 183',
                'content' => 'El conductor fue brusco con los pasajeros y no respondio a una pregunta simple.',
                'severity' => 'moyen',
            ],
        ];
    }

    public function find(string $id): ?array
    {
        foreach ($this->getFeed() as $item) {
            if ($item['id'] === $id) {
                return $item;
            }
        }

        return null;
    }
}
