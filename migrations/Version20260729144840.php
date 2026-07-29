<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260729144840 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE conge (id INT AUTO_INCREMENT NOT NULL, date_debut DATE NOT NULL, date_fin DATE NOT NULL, nb_jours DOUBLE PRECISION DEFAULT NULL, statut VARCHAR(20) NOT NULL, motif LONGTEXT DEFAULT NULL, valide_le DATE DEFAULT NULL, created_at DATETIME NOT NULL, employee_id INT NOT NULL, entreprise_id INT NOT NULL, type_conge_id INT NOT NULL, valide_par_id INT DEFAULT NULL, INDEX IDX_2ED893488C03F15C (employee_id), INDEX IDX_2ED89348A4AEAFEA (entreprise_id), INDEX IDX_2ED89348753BDA5 (type_conge_id), INDEX IDX_2ED893486AF12ED9 (valide_par_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE pointage (id INT AUTO_INCREMENT NOT NULL, date DATE NOT NULL, heure_entree TIME DEFAULT NULL, heure_sortie TIME DEFAULT NULL, pause_minutes INT DEFAULT 60 NOT NULL, statut VARCHAR(20) DEFAULT \'absent\' NOT NULL, valide TINYINT DEFAULT 0 NOT NULL, motif_correction LONGTEXT DEFAULT NULL, corrige_le DATETIME DEFAULT NULL, heure_prevue_debut TIME DEFAULT NULL, heure_prevue_fin TIME DEFAULT NULL, pause_prevue_minutes INT DEFAULT 60 NOT NULL, employee_id INT NOT NULL, entreprise_id INT NOT NULL, corrige_par_id INT DEFAULT NULL, INDEX IDX_7591B208C03F15C (employee_id), INDEX IDX_7591B20A4AEAFEA (entreprise_id), INDEX IDX_7591B20C99BFB58 (corrige_par_id), UNIQUE INDEX unique_pointage_user_date (employee_id, date), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE type_conge (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(255) NOT NULL, code VARCHAR(50) NOT NULL, jours_annuels INT DEFAULT NULL, paye TINYINT NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE conge ADD CONSTRAINT FK_2ED893488C03F15C FOREIGN KEY (employee_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE conge ADD CONSTRAINT FK_2ED89348A4AEAFEA FOREIGN KEY (entreprise_id) REFERENCES entreprise (id)');
        $this->addSql('ALTER TABLE conge ADD CONSTRAINT FK_2ED89348753BDA5 FOREIGN KEY (type_conge_id) REFERENCES type_conge (id)');
        $this->addSql('ALTER TABLE conge ADD CONSTRAINT FK_2ED893486AF12ED9 FOREIGN KEY (valide_par_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE pointage ADD CONSTRAINT FK_7591B208C03F15C FOREIGN KEY (employee_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE pointage ADD CONSTRAINT FK_7591B20A4AEAFEA FOREIGN KEY (entreprise_id) REFERENCES entreprise (id)');
        $this->addSql('ALTER TABLE pointage ADD CONSTRAINT FK_7591B20C99BFB58 FOREIGN KEY (corrige_par_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE planning ADD pause_minutes INT DEFAULT NULL, ADD status VARCHAR(20) NOT NULL, ADD created_at DATETIME NOT NULL, ADD validated_at DATETIME DEFAULT NULL, ADD entreprise_id INT NOT NULL, ADD validated_by_id INT DEFAULT NULL, DROP pause, DROP pausette, CHANGE heure_debut heure_debut TIME DEFAULT NULL, CHANGE heure_fin heure_fin TIME DEFAULT NULL');
        $this->addSql('ALTER TABLE planning ADD CONSTRAINT FK_D499BFF6A4AEAFEA FOREIGN KEY (entreprise_id) REFERENCES entreprise (id)');
        $this->addSql('ALTER TABLE planning ADD CONSTRAINT FK_D499BFF6C69DE5E5 FOREIGN KEY (validated_by_id) REFERENCES user (id)');
        $this->addSql('CREATE INDEX IDX_D499BFF6A4AEAFEA ON planning (entreprise_id)');
        $this->addSql('CREATE INDEX IDX_D499BFF6C69DE5E5 ON planning (validated_by_id)');
        $this->addSql('CREATE UNIQUE INDEX unique_planning_per_day ON planning (user_id, week_start, day_of_week)');
        $this->addSql('ALTER TABLE user ADD poste VARCHAR(100) DEFAULT NULL, ADD service VARCHAR(100) DEFAULT NULL, ADD heures_contractuelles NUMERIC(4, 2) DEFAULT 8 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE conge DROP FOREIGN KEY FK_2ED893488C03F15C');
        $this->addSql('ALTER TABLE conge DROP FOREIGN KEY FK_2ED89348A4AEAFEA');
        $this->addSql('ALTER TABLE conge DROP FOREIGN KEY FK_2ED89348753BDA5');
        $this->addSql('ALTER TABLE conge DROP FOREIGN KEY FK_2ED893486AF12ED9');
        $this->addSql('ALTER TABLE pointage DROP FOREIGN KEY FK_7591B208C03F15C');
        $this->addSql('ALTER TABLE pointage DROP FOREIGN KEY FK_7591B20A4AEAFEA');
        $this->addSql('ALTER TABLE pointage DROP FOREIGN KEY FK_7591B20C99BFB58');
        $this->addSql('DROP TABLE conge');
        $this->addSql('DROP TABLE pointage');
        $this->addSql('DROP TABLE type_conge');
        $this->addSql('ALTER TABLE planning DROP FOREIGN KEY FK_D499BFF6A4AEAFEA');
        $this->addSql('ALTER TABLE planning DROP FOREIGN KEY FK_D499BFF6C69DE5E5');
        $this->addSql('DROP INDEX IDX_D499BFF6A4AEAFEA ON planning');
        $this->addSql('DROP INDEX IDX_D499BFF6C69DE5E5 ON planning');
        $this->addSql('DROP INDEX unique_planning_per_day ON planning');
        $this->addSql('ALTER TABLE planning ADD pause TIME DEFAULT NULL, ADD pausette TIME DEFAULT NULL, DROP pause_minutes, DROP status, DROP created_at, DROP validated_at, DROP entreprise_id, DROP validated_by_id, CHANGE heure_debut heure_debut TIME NOT NULL, CHANGE heure_fin heure_fin TIME NOT NULL');
        $this->addSql('ALTER TABLE user DROP poste, DROP service, DROP heures_contractuelles');
    }
}
