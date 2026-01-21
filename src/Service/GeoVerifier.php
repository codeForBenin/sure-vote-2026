<?php

namespace App\Service;

class GeoVerifier
{
    /**
     * Calcule la distance en mètres entre deux points (formule de Haversine)
     */
    public function calculateDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371000; // mètres

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    /**
     * Vérifie si un point est dans une distance maximale par rapport à un autre point en mètres --> 1000 mètres = 1 km
     */
    public function isWithinRange(float $userLat, float $userLon, float $targetLat, float $targetLon, float $maxDistance = 1000): bool
    {
        return $this->calculateDistance($userLat, $userLon, $targetLat, $targetLon) <= $maxDistance;
    }
}
