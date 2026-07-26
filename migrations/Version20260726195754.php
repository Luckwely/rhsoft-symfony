<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260726195754 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE user DROP statut, DROP statut_travail, DROP debut_shift_at, DROP fin_shift_at, DROP heure_debut, DROP heure_fin, DROP pause, CHANGE roles roles JSON NOT NULL, CHANGE entreprise_id entreprise_id INT NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE user ADD statut VARCHAR(50) NOT NULL, ADD statut_travail VARCHAR(50) NOT NULL, ADD debut_shift_at DATETIME DEFAULT NULL, ADD fin_shift_at DATETIME DEFAULT NULL, ADD heure_debut TIME DEFAULT NULL, ADD heure_fin TIME DEFAULT NULL, ADD pause VARCHAR(10) DEFAULT NULL, CHANGE roles roles LONGTEXT NOT NULL COLLATE `utf8mb4_bin`, CHANGE entreprise_id entreprise_id INT DEFAULT NULL');
    }
}
