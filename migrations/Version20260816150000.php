<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Adds the salary-related fields on User needed by the payroll (Paie) module.
 * These fields were referenced by PaieController/PaieCalculatorService but
 * never existed on the entity or in the database, so payroll calculation
 * was fatally broken.
 */
final class Version20260816150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add salaire_base, panier_repas, transport, anciennete, personnes_acharge to user table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user ADD salaire_base DOUBLE PRECISION DEFAULT NULL');
        $this->addSql('ALTER TABLE user ADD panier_repas DOUBLE PRECISION DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE user ADD transport DOUBLE PRECISION DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE user ADD anciennete DOUBLE PRECISION DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE user ADD personnes_acharge INT DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user DROP salaire_base');
        $this->addSql('ALTER TABLE user DROP panier_repas');
        $this->addSql('ALTER TABLE user DROP transport');
        $this->addSql('ALTER TABLE user DROP anciennete');
        $this->addSql('ALTER TABLE user DROP personnes_acharge');
    }
}
