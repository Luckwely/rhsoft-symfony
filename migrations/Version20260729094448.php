<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260729094448 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE conge (id INT AUTO_INCREMENT NOT NULL, date_debut DATE NOT NULL, date_fin DATE NOT NULL, nb_jours DOUBLE PRECISION NOT NULL, status VARCHAR(20) NOT NULL, motif LONGTEXT NOT NULL, valide_le DATE NOT NULL, employe_id INT DEFAULT NULL, entreprise_id INT DEFAULT NULL, type_conge_id INT DEFAULT NULL, valide_par_id INT DEFAULT NULL, INDEX IDX_2ED893481B65292 (employe_id), INDEX IDX_2ED89348A4AEAFEA (entreprise_id), INDEX IDX_2ED89348753BDA5 (type_conge_id), INDEX IDX_2ED893486AF12ED9 (valide_par_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE conge ADD CONSTRAINT FK_2ED893481B65292 FOREIGN KEY (employe_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE conge ADD CONSTRAINT FK_2ED89348A4AEAFEA FOREIGN KEY (entreprise_id) REFERENCES entreprise (id)');
        $this->addSql('ALTER TABLE conge ADD CONSTRAINT FK_2ED89348753BDA5 FOREIGN KEY (type_conge_id) REFERENCES type_conge (id)');
        $this->addSql('ALTER TABLE conge ADD CONSTRAINT FK_2ED893486AF12ED9 FOREIGN KEY (valide_par_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE user CHANGE heures_contractuelles heures_contractuelles NUMERIC(4, 2) DEFAULT 8 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE conge DROP FOREIGN KEY FK_2ED893481B65292');
        $this->addSql('ALTER TABLE conge DROP FOREIGN KEY FK_2ED89348A4AEAFEA');
        $this->addSql('ALTER TABLE conge DROP FOREIGN KEY FK_2ED89348753BDA5');
        $this->addSql('ALTER TABLE conge DROP FOREIGN KEY FK_2ED893486AF12ED9');
        $this->addSql('DROP TABLE conge');
        $this->addSql('ALTER TABLE user CHANGE heures_contractuelles heures_contractuelles NUMERIC(4, 2) DEFAULT \'8.00\' NOT NULL');
    }
}
