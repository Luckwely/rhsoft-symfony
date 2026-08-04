<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260803134028 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE paie (id INT AUTO_INCREMENT NOT NULL, mois INT NOT NULL, annee INT NOT NULL, salaire_brut NUMERIC(10, 2) DEFAULT NULL, cotisations NUMERIC(10, 2) DEFAULT NULL, salaire_net NUMERIC(10, 2) DEFAULT NULL, status VARCHAR(255) NOT NULL, employee_id INT NOT NULL, INDEX IDX_8A899BAE8C03F15C (employee_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE paie ADD CONSTRAINT FK_8A899BAE8C03F15C FOREIGN KEY (employee_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE user CHANGE heures_contractuelles heures_contractuelles NUMERIC(4, 2) DEFAULT 8 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE paie DROP FOREIGN KEY FK_8A899BAE8C03F15C');
        $this->addSql('DROP TABLE paie');
        $this->addSql('ALTER TABLE user CHANGE heures_contractuelles heures_contractuelles NUMERIC(4, 2) DEFAULT \'8.00\' NOT NULL');
    }
}
