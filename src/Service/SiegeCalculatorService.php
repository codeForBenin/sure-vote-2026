<?php

namespace App\Service;

class SiegeCalculatorService
{
    /**
     * Calcule la répartition des sièges pour une circonscription donnée
     * Basé sur le Code Électoral (Bénin)
     * 
     * @param array $resultats Tableau de forme ['parti' => Parti, 'voix' => int]
     * @param int $siegesTotalNombre Nombre total de sièges dans la circonscription
     * @return array Résultat enrichi avec le nombre de sièges ['femme' => int, 'ordinaire' => int, 'total' => int]
     */
    public function calculerSieges(array $resultats, int $siegesTotalNombre): array
    {
        // On prépare la structure de retour
        $projection = [];
        $totalVoixExprimees = 0;

        foreach ($resultats as $r) {
            $partiId = (string) $r['parti']->getId();
            $projection[$partiId] = [
                'parti' => $r['parti'],
                'voix' => $r['voix'],
                'sieges_femme' => 0,
                'sieges_ordinaire' => 0,
                'total_sieges' => 0,
            ];
            $totalVoixExprimees += $r['voix'];
        }

        if ($totalVoixExprimees === 0 || $siegesTotalNombre === 0) {
            return [
                'repartition' => $projection,
                'quotient_electoral' => 0
            ];
        }

        // ==========================================
        // ÉTAPE 1 : ATTRIBUTION DU SIÈGE FÉMININ
        // ==========================================
        // "Attribué à la liste ayant obtenu le plus grand nombre de suffrages"
        // On trie par voix décroissante
        uasort($projection, fn($a, $b) => $b['voix'] <=> $a['voix']);

        // Le premier prend le siège femme (si au moins 1 siège dispo au total)
        if ($siegesTotalNombre >= 1) {
            $firstKey = array_key_first($projection);
            $projection[$firstKey]['sieges_femme'] = 1;
            $projection[$firstKey]['total_sieges'] += 1;
        }

        // ==========================================
        // ÉTAPE 2 : RÉPARTITION DES SIÈGES ORDINAIRES
        // ==========================================
        $siegesOrdinairesAPourvoir = $siegesTotalNombre - 1;

        if ($siegesOrdinairesAPourvoir > 0) {
            // 2.1 Calcul du Quotient Electoral
            // "Nombre de suffrages valablement exprimés divisé par le nombre de sièges à pourvoir"
            $quotientElectoral = floor($totalVoixExprimees / $siegesOrdinairesAPourvoir);

            // 2.2 Première répartition au quotient
            $siegesAttribues = 0;

            foreach ($projection as $id => $data) {
                $nbSieges = floor($data['voix'] / $quotientElectoral);
                $projection[$id]['sieges_ordinaire'] += $nbSieges;
                $projection[$id]['total_sieges'] += $nbSieges;
                $siegesAttribues += $nbSieges;
            }

            // 2.3 Répartition des restes à la Plus Forte Moyenne
            $siegesRestants = $siegesOrdinairesAPourvoir - $siegesAttribues;

            while ($siegesRestants > 0) {
                $meilleureMoyenne = -1;
                $partiGagnantId = null;

                foreach ($projection as $id => $data) {
                    // Moyenne = Voix / (Sièges Déjà Obtenus + 1 [celui qu'on convoite])
                    $diviseur = $data['sieges_ordinaire'] + 1;
                    $moyenne = $data['voix'] / $diviseur;

                    if ($moyenne > $meilleureMoyenne) {
                        $meilleureMoyenne = $moyenne;
                        $partiGagnantId = $id;
                    }
                }

                if ($partiGagnantId) {
                    $projection[$partiGagnantId]['sieges_ordinaire']++;
                    $projection[$partiGagnantId]['total_sieges']++;
                    $siegesRestants--;
                } else {
                    break; // Sécurité
                }
            }
        }

        return [
            'repartition' => $projection,
            'quotient_electoral' => $quotientElectoral ?? 0
        ];
    }
}
