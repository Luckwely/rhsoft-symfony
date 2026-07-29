<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260729100814 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE conge DROP FOREIGN KEY `FK_2ED893481B65292`');
        $this->addSql('DROP INDEX IDX_2ED893481B65292 ON conge');
        $this->addSql('ALTER TABLE conge ADD created_at DATETIME NOT NULL, ADD employee_id INT NOT NULL, DROP employe_id, CHANGE nb_jours nb_jours DOUBLE PRECISION DEFAULT NULL, CHANGE motif motif LONGTEXT DEFAULT NULL, CHANGE valide_le valide_le DATE DEFAULT NULL, CHANGE entreprise_id entreprise_id INT NOT NULL, CHANGE type_conge_id type_conge_id INT NOT NULL, CHANGE status statut VARCHAR(20) NOT NULL');
        $this->addSql('ALTER TABLE conge ADD CONSTRAINT FK_2ED893488C03F15C FOREIGN KEY (employee_id) REFERENCES user (id)');
        $this->addSql('CREATE INDEX IDX_2ED893488C03F15C ON conge (employee_id)');
        $this->addSql('ALTER TABLE user CHANGE heures_contractuelles heures_contractuelles NUMERIC(4, 2) DEFAULT 8 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE conge DROP FOREIGN KEY FK_2ED893488C03F15C');
        $this->addSql('DROP INDEX IDX_2ED893488C03F15C ON conge');
        $this->addSql('ALTER TABLE conge ADD employe_id INT DEFAULT NULL, DROP created_at, DROP employee_id, CHANGE nb_jours nb_jours DOUBLE PRECISION NOT NULL, CHANGE motif motif LONGTEXT NOT NULL, CHANGE valide_le valide_le DATE NOT NULL, CHANGE entreprise_id entreprise_id INT DEFAULT NULL, CHANGE type_conge_id type_conge_id INT DEFAULT NULL, CHANGE statut status VARCHAR(20) NOT NULL');
        $this->addSql('ALTER TABLE conge ADD CONSTRAINT `FK_2ED893481B65292` FOREIGN KEY (employe_id) REFERENCES user (id)');
        $this->addSql('CREATE INDEX IDX_2ED893481B65292 ON conge (employe_id)');
        $this->addSql('ALTER TABLE user CHANGE heures_contractuelles heures_contractuelles NUMERIC(4, 2) DEFAULT \'8.00\' NOT NULL');
    }
}
