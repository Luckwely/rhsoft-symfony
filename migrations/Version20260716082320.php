<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260716082320 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE user ADD adresse VARCHAR(255) DEFAULT NULL, ADD telephone VARCHAR(20) DEFAULT NULL, ADD cv VARCHAR(255) DEFAULT NULL, ADD statut VARCHAR(50) NOT NULL, ADD statut_travail VARCHAR(50) NOT NULL, ADD debut_shift_at DATETIME DEFAULT NULL, ADD fin_shift_at DATETIME DEFAULT NULL, ADD created_at DATETIME NOT NULL, ADD banque_nom VARCHAR(255) DEFAULT NULL, ADD banque_iban VARCHAR(50) DEFAULT NULL, ADD banque_rib VARCHAR(50) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE user DROP adresse, DROP telephone, DROP cv, DROP statut, DROP statut_travail, DROP debut_shift_at, DROP fin_shift_at, DROP created_at, DROP banque_nom, DROP banque_iban, DROP banque_rib');
    }
}
