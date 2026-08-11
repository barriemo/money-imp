<?php

namespace App\Domains\Infrastructure\Actions;

use App\Models\InfrastructureRelationship;
use App\Models\SupplierAsset;
use InvalidArgumentException;

class LinkInfrastructureAssets
{
    public function execute(
        SupplierAsset $from,
        SupplierAsset $to,
        string $relationship,
        int $confidence = 100,
        string $source = 'manual',
        bool $verified = false,
        array $metadata = []
    ): InfrastructureRelationship {
        if ($from->is($to)) {
            throw new InvalidArgumentException(
                'An infrastructure asset cannot link to itself.'
            );
        }

        $relationship = strtoupper(
            trim($relationship)
        );

        $allowed = [
            'HOSTS',
            'USES',
            'POINTS_TO',
            'PROVIDES',
            'BACKS_UP',
            'SECURES',
            'RUNS',
            'DEPENDS_ON',
        ];

        if (
            ! in_array(
                $relationship,
                $allowed,
                true
            )
        ) {
            throw new InvalidArgumentException(
                'Unsupported infrastructure relationship.'
            );
        }

        return InfrastructureRelationship::updateOrCreate(
            [
                'from_asset_id' => $from->id,

                'to_asset_id' => $to->id,

                'relationship' => $relationship,
            ],
            [
                'confidence' => max(
                    0,
                    min(100, $confidence)
                ),

                'source' => $source,

                'verified' => $verified,

                'metadata' => $metadata,
            ]
        );
    }
}
