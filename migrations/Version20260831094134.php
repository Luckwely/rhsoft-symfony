<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260831094134 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE page_view (id INT AUTO_INCREMENT NOT NULL, viewed_at DATETIME NOT NULL, INDEX idx_page_view_viewed_at (viewed_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('DROP TABLE site_visit');
        $this->addSql('ALTER TABLE entreprise CHANGE salaire_base_admin salaire_base_admin NUMERIC(12, 2) DEFAULT 1000000 NOT NULL, CHANGE salaire_base_rh salaire_base_rh NUMERIC(12, 2) DEFAULT 800000 NOT NULL, CHANGE salaire_base_manager salaire_base_manager NUMERIC(12, 2) DEFAULT 600000 NOT NULL, CHANGE salaire_base_employe salaire_base_employe NUMERIC(12, 2) DEFAULT 400000 NOT NULL');
        $this->addSql('ALTER TABLE user CHANGE heures_contractuelles heures_contractuelles NUMERIC(4, 2) DEFAULT 8 NOT NULL, CHANGE panier_repas panier_repas DOUBLE PRECISION DEFAULT 0 NOT NULL, CHANGE transport transport DOUBLE PRECISION DEFAULT 0 NOT NULL, CHANGE anciennete anciennete DOUBLE PRECISION DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE site_visit (id INT AUTO_INCREMENT NOT NULL, visited_at DATETIME NOT NULL, path VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_uca1400_ai_ci`, visitor_hash VARCHAR(64) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_uca1400_ai_ci`, INDEX idx_site_visit_visited_at (visited_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_uca1400_ai_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('DROP TABLE page_view');
        $this->addSql('ALTER TABLE entreprise CHANGE salaire_base_admin salaire_base_admin NUMERIC(12, 2) DEFAULT \'1000000.00\' NOT NULL, CHANGE salaire_base_rh salaire_base_rh NUMERIC(12, 2) DEFAULT \'800000.00\' NOT NULL, CHANGE salaire_base_manager salaire_base_manager NUMERIC(12, 2) DEFAULT \'600000.00\' NOT NULL, CHANGE salaire_base_employe salaire_base_employe NUMERIC(12, 2) DEFAULT \'400000.00\' NOT NULL');
        $this->addSql('ALTER TABLE user CHANGE heures_contractuelles heures_contractuelles NUMERIC(4, 2) DEFAULT \'8.00\' NOT NULL, CHANGE panier_repas panier_repas DOUBLE PRECISION DEFAULT \'0\' NOT NULL, CHANGE transport transport DOUBLE PRECISION DEFAULT \'0\' NOT NULL, CHANGE anciennete anciennete DOUBLE PRECISION DEFAULT \'0\' NOT NULL');
    }
}
