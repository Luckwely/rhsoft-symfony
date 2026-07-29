<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260728124637 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE pointage_user DROP FOREIGN KEY `FK_BA43DA30A76ED395`');
        $this->addSql('ALTER TABLE pointage_user DROP FOREIGN KEY `FK_BA43DA30E58DA11D`');
        $this->addSql('DROP TABLE pointage_user');
        $this->addSql('ALTER TABLE pointage ADD valide TINYINT DEFAULT 0 NOT NULL, ADD corrige_le DATETIME DEFAULT NULL, ADD entreprise_id INT NOT NULL, ADD corrige_par_id INT DEFAULT NULL, CHANGE heure_entree heure_entree TIME DEFAULT NULL, CHANGE heure_sortie heure_sortie TIME DEFAULT NULL, CHANGE pause_minutes pause_minutes INT DEFAULT 60 NOT NULL, CHANGE statut statut VARCHAR(20) DEFAULT \'absent\' NOT NULL, CHANGE motif_correction motif_correction LONGTEXT DEFAULT NULL, CHANGE employee_id employee_id INT NOT NULL');
        $this->addSql('ALTER TABLE pointage ADD CONSTRAINT FK_7591B20A4AEAFEA FOREIGN KEY (entreprise_id) REFERENCES entreprise (id)');
        $this->addSql('ALTER TABLE pointage ADD CONSTRAINT FK_7591B20C99BFB58 FOREIGN KEY (corrige_par_id) REFERENCES user (id)');
        $this->addSql('CREATE INDEX IDX_7591B20A4AEAFEA ON pointage (entreprise_id)');
        $this->addSql('CREATE INDEX IDX_7591B20C99BFB58 ON pointage (corrige_par_id)');
        $this->addSql('CREATE UNIQUE INDEX unique_pointage_user_date ON pointage (employee_id, date)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE pointage_user (pointage_id INT NOT NULL, user_id INT NOT NULL, INDEX IDX_BA43DA30E58DA11D (pointage_id), INDEX IDX_BA43DA30A76ED395 (user_id), PRIMARY KEY (pointage_id, user_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_uca1400_ai_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE pointage_user ADD CONSTRAINT `FK_BA43DA30A76ED395` FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE pointage_user ADD CONSTRAINT `FK_BA43DA30E58DA11D` FOREIGN KEY (pointage_id) REFERENCES pointage (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE pointage DROP FOREIGN KEY FK_7591B20A4AEAFEA');
        $this->addSql('ALTER TABLE pointage DROP FOREIGN KEY FK_7591B20C99BFB58');
        $this->addSql('DROP INDEX IDX_7591B20A4AEAFEA ON pointage');
        $this->addSql('DROP INDEX IDX_7591B20C99BFB58 ON pointage');
        $this->addSql('DROP INDEX unique_pointage_user_date ON pointage');
        $this->addSql('ALTER TABLE pointage DROP valide, DROP corrige_le, DROP entreprise_id, DROP corrige_par_id, CHANGE heure_entree heure_entree TIME NOT NULL, CHANGE heure_sortie heure_sortie TIME NOT NULL, CHANGE pause_minutes pause_minutes TIME NOT NULL, CHANGE statut statut VARCHAR(255) NOT NULL, CHANGE motif_correction motif_correction VARCHAR(255) NOT NULL, CHANGE employee_id employee_id INT DEFAULT NULL');
    }
}
