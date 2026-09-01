<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ajoute le montant des heures supplémentaires sur la fiche de paie, dans la même
 * logique que montant_avance_deduite : les heures sup étaient calculées et affichées
 * dans le reporting RH (findTopOvertimeByEntreprise) mais jamais réellement injectées
 * ni tracées dans le calcul de paie (PaieCalculatorService ignorait hs_30/hs_50).
 */
final class Version20260901120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute paie.montant_heures_supplementaires (heures sup désormais réellement payées et tracées sur la fiche de paie)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE paie ADD montant_heures_supplementaires NUMERIC(10, 2) DEFAULT \'0.00\' NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE paie DROP montant_heures_supplementaires');
    }
}
