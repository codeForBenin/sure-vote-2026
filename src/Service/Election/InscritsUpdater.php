<?php

namespace App\Service\Election;

use App\Entity\BureauDeVote;
use App\Entity\SignalementInscrits;
use Doctrine\ORM\EntityManagerInterface;

class InscritsUpdater
{
    public function __construct(
        private EntityManagerInterface $em
    ) {
    }

    public function applyToEntities(SignalementInscrits $signalement): void
    {
        // 1. Récupérer le centre
        $centre = $signalement->getCentreDeVote();

        // 2. Traiter la répartition par bureau
        $repartition = $signalement->getRepartitionBureaux();
        if (is_array($repartition)) {
            $bureauRepo = $this->em->getRepository(BureauDeVote::class);

            foreach ($repartition as $item) {
                // Format attendu: ['bureau_id' => 123, 'inscrits' => 450]
                if (!isset($item['bureau_id']) || !isset($item['inscrits'])) {
                    continue;
                }

                $bureauId = $item['bureau_id'];
                $nbInscrits = (int) $item['inscrits'];

                $bureau = $bureauRepo->find($bureauId);
                if ($bureau) {
                    // Vérification de sécurité : le bureau doit appartenir au même centre
                    // Note: getCentre() renvoie le centre
                    if ($bureau->getCentre()->getId() === $centre->getId()) {
                        $bureau->setNombreInscrits($nbInscrits);
                        $this->em->persist($bureau);
                    }
                }
            }
        }

        // Pas besoin de flush ici, car EasyAdmin le fera à la fin de la transaction du Controller
        // Mais par sécurité si utilisé ailleurs, on pourrait, mais laissons l'appelant gérer la transaction globale.
    }
    public function resetEntities(SignalementInscrits $signalement): void
    {
        // 1. Récupérer le centre
        $centre = $signalement->getCentreDeVote();

        // 2. Traiter la répartition par bureau pour remettre à 0
        $repartition = $signalement->getRepartitionBureaux();
        if (is_array($repartition)) {
            $bureauRepo = $this->em->getRepository(BureauDeVote::class);

            foreach ($repartition as $item) {
                if (!isset($item['bureau_id'])) {
                    continue;
                }

                $bureauId = $item['bureau_id'];
                $bureau = $bureauRepo->find($bureauId);

                if ($bureau && $bureau->getCentre()->getId() === $centre->getId()) {
                    // Reset à 0 ou null pour forcer la resaisie/blocage
                    $bureau->setNombreInscrits(0);
                    $this->em->persist($bureau);
                }
            }
        }
    }
}
