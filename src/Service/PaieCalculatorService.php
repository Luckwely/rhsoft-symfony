<?php

namespace App\Service;

class PaieCalculatorService
{
    private const CNAPS_TAUX = 0.01; // 1% employee share
    private const OSTIE_TAUX = 0.01; // 1% employee share
    private const PLAFOND_SOCIAL = 2101440; // Example ceiling (8x SME)
    private const MIN_IRSA = 3000; // Minimum tax perception

    // Heures supplémentaires : équivalent mensuel de la règle légale des 8 premières
    // heures sup/semaine majorées à 30%, le reste à 50% (21.66 = 5j/semaine x 4.33
    // semaines/mois, la même conversion jour->mois déjà utilisée pour le taux horaire
    // estimé côté reporting RH, afin que les deux écrans restent cohérents entre eux).
    public const JOURS_OUVRES_PAR_MOIS = 21.66;
    public const SEUIL_HS_30_PAR_MOIS = 35.0;
    public const TAUX_MAJORATION_HS_30 = 1.30;
    public const TAUX_MAJORATION_HS_50 = 1.50;

    // Primes calculées sur la présence réelle / l'ancienneté réelle (et non plus des
    // montants fixes saisis manuellement) : un employé absent ou présent moins de 5
    // jours dans la semaine touche donc mécaniquement moins de panier repas / transport.
    private const PANIER_REPAS_PAR_JOUR = 5000; // Ar par jour réellement présent
    private const TRANSPORT_PAR_JOUR = 5000; // Ar par jour réellement présent
    private const PRIME_ANCIENNETE_PAR_AN = 20000; // Ar par année d'ancienneté

    public function calculerPaie(array $data): array
    {
        $salaireBase = $data['salaire_base'];
        $joursAbsents = $data['jours_absents'] ?? 0;
        $joursMois = $data['jours_mois'] ?? 30;

        // 1. ABSENCES
        $retenueAbsence = ($salaireBase / $joursMois) * $joursAbsents;
        $salaireDeBaseEffectif = max(0, $salaireBase - $retenueAbsence);

        // 2. HEURES SUPPLÉMENTAIRES (HS)
        // Converties en montant à partir du taux horaire de l'employé (même formule que
        // l'estimation affichée dans le reporting RH), et non plus un montant à saisir
        // manuellement : jusqu'ici hs_30/hs_50 étaient reçues mais jamais utilisées, donc
        // aucune heure supplémentaire n'était réellement payée.
        $hs30 = $data['hs_30'] ?? 0; // Heures majorées à 30%
        $hs50 = $data['hs_50'] ?? 0; // Heures majorées à 50%
        $heuresContractJour = $data['heures_contractuelles'] ?? 8.0;
        $heuresContractMois = $heuresContractJour * self::JOURS_OUVRES_PAR_MOIS;
        $tauxHoraire = ($salaireBase > 0 && $heuresContractMois > 0) ? $salaireBase / $heuresContractMois : 0.0;
        $montantHS = ($hs30 * $tauxHoraire * self::TAUX_MAJORATION_HS_30)
            + ($hs50 * $tauxHoraire * self::TAUX_MAJORATION_HS_50);

        // 3. GAINS / ELEMENTS VARIABLES
        // Panier repas et transport suivent la présence réelle du mois (5000 Ar/jour chacun,
        // indépendamment l'un de l'autre) plutôt qu'un montant fixe : un employé absent ou
        // présent moins de 5 jours dans la semaine touche donc mécaniquement moins que
        // quelqu'un présent tous les jours.
        $joursPresents = $data['jours_presents'] ?? 0;
        $panierRepas = $joursPresents * self::PANIER_REPAS_PAR_JOUR;
        $transport = $joursPresents * self::TRANSPORT_PAR_JOUR;

        // Prime d'ancienneté : calculée à partir du même nombre d'années d'ancienneté que
        // celui affiché dans le rapport RH "Bilan Social" (basé sur la date d'embauche),
        // pour que les deux chiffres soient toujours cohérents entre eux.
        $ancienneteAnnees = $data['anciennete_annees'] ?? 0;
        $anciennete = $ancienneteAnnees * self::PRIME_ANCIENNETE_PAR_AN;

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

        // 7. AVANCES SUR SALAIRE
        // Retenue des avances déjà versées à l'employé et pas encore remboursées, déduite
        // après impôt (une avance n'est pas une charge fiscale, c'est un remboursement pur).
        // Plafonnée pour ne jamais rendre le salaire net négatif.
        $avanceADeduire = min($data['avance_a_deduire'] ?? 0, max(0, $salaireBrut - $totalCotisations - $irsaNet));

        // 8. SALAIRE NET
        $salaireNet = $salaireBrut - $totalCotisations - $irsaNet - $avanceADeduire;

        return [
            'salaire_brut' => $salaireBrut,
            'cnaps' => $cnapsSal,
            'ostie' => $ostieSal,
            'base_imposable' => $baseImposable,
            'irsa' => $irsaNet,
            'montant_hs' => $montantHS,
            'avance_deduite' => $avanceADeduire,
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
            $tranche4 = min($base, 600000) - 500000;
            $impot += $tranche4 * 0.15;
        }
        if ($base > 600000) {
            // Tranche 5: 600 001 à 4 000 000 Ar (20%)
            $tranche5 = min($base, 4000000) - 600000;
            $impot += $tranche5 * 0.20;
        }
        if ($base > 4000000) {
            // Tranche 6: Au-delà de 4 000 000 Ar (25%)
            $tranche6 = $base - 4000000;
            $impot += $tranche6 * 0.25;
        }

        return $impot;
    }
}
