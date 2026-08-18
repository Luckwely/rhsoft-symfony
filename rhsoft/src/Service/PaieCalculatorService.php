<?php

namespace App\Service;

class PaieCalculatorService
{
    private const CNAPS_TAUX = 0.01; // 1% employee share
    private const OSTIE_TAUX = 0.01; // 1% employee share
    private const PLAFOND_SOCIAL = 2101440; // Example ceiling (8x SME)
    private const MIN_IRSA = 3000; // Minimum tax perception

    public function calculerPaie(array $data): array
    {
        $salaireBase = $data['salaire_base'];
        $joursAbsents = $data['jours_absents'] ?? 0;
        $joursMois = $data['jours_mois'] ?? 30;

        // 1. ABSENCES
        $retenueAbsence = ($salaireBase / $joursMois) * $joursAbsents;
        $salaireDeBaseEffectif = max(0, $salaireBase - $retenueAbsence);

        // 2. HEURES SUPPLÉMENTAIRES (HS)
        $hs30 = $data['hs_30'] ?? 0; // Majorées à 30%
        $hs50 = $data['hs_50'] ?? 0; // Majorées à 50%
        $montantHS = $data['montant_hs'] ?? 0;

        // 3. GAINS / ELEMENTS VARIABLES
        $panierRepas = $data['panier_repas'] ?? 0;
        $transport = $data['transport'] ?? 0;
        $anciennete = $data['anciennete'] ?? 0;

        // Salaire Brut Total
        $salaireBrut = $salaireDeBaseEffectif + $anciennete + $montantHS + $panierRepas + $transport;

        // 4. COTISATIONS SOCIALES (CNaPS & OSTIE)
        // Correction: Définition de $baseSociale ici
        $baseSociale = min($salaireBrut, self::PLAFOND_SOCIAL);
        $cnapsSal = $baseSociale * self::CNAPS_TAUX;
        $ostieSal = $baseSociale * self::OSTIE_TAUX;
        $totalCotisations = $cnapsSal + $ostieSal;

        // 5. BASE IMPOSABLE IRSA
        $baseImposable = $salaireBrut - $totalCotisations;
        // Arrondi à la centaine d'ariary inférieure
        $baseImposable = floor($baseImposable / 100) * 100;

        // 6. CALCUL IRSA (Barème progressif Madagascar)
        $irsaBrut = $this->calculerBaremeIrsa($baseImposable);

        // Déduction pour charges de famille (ex: 2000 Ar par personne à charge)
        $personnesACharge = $data['personnes_a_charge'] ?? 0;
        $reductionFamille = $personnesACharge * 2000;

        $irsaNet = $irsaBrut - $reductionFamille;

        // Minimum de perception IRSA (3000 Ar si imposable > seuil d'exoneration)
        if ($baseImposable > 350000 && $irsaNet < self::MIN_IRSA) {
            $irsaNet = self::MIN_IRSA;
        }
        $irsaNet = max(0, $irsaNet);

        // 7. SALAIRE NET
        $salaireNet = $salaireBrut - $totalCotisations - $irsaNet;

        return [
            'salaire_brut' => $salaireBrut,
            'cnaps' => $cnapsSal,
            'ostie' => $ostieSal,
            'base_imposable' => $baseImposable,
            'irsa' => $irsaNet,
            'salaire_net' => $salaireNet,
        ];
    }

    private function calculerBaremeIrsa(float $base): float
    {
        $impot = 0;

        // Tranche 1: 0 à 350 000 Ar (0%)
        if ($base > 350000) {
            // Tranche 2: 350 001 à 400 000 Ar (5%)
            $tranche2 = min($base, 400000) - 350000;
            $impot += $tranche2 * 0.05;
        }
        if ($base > 400000) {
            // Tranche 3: 400 001 à 500 000 Ar (10%)
            $tranche3 = min($base, 500000) - 400000;
            $impot += $tranche3 * 0.10;
        }
        if ($base > 500000) {
            // Tranche 4: 500 001 à 600 000 Ar (15%)
            $tranche5 = min($base, 600000) - 500000;
            $impot += $tranche5 * 0.15;
        }
        if ($base > 600000) {
            // Tranche 5: 600 001 à 4 000 000 Ar (20%)
            $tranche6 = min($base, 4000000) - 600000;
            $impot += $tranche6 * 0.20;
        }
        if ($base > 4000000) {
            // Tranche 6: Au-delà de 4 000 000 Ar (25%)
            $tranche7 = $base - 4000000;
            $impot += $tranche7 * 0.25;
        }

        return $impot;
    }
}
