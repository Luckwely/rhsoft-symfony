<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260728082710 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE planning ADD pause_minutes INT DEFAULT NULL, ADD created_at DATETIME NOT NULL, ADD entreprise_id INT NOT NULL, DROP pause, DROP pausette, CHANGE heure_debut heure_debut TIME DEFAULT NULL, CHANGE heure_fin heure_fin TIME DEFAULT NULL');
        $this->addSql('ALTER TABLE planning ADD CONSTRAINT FK_D499BFF6A4AEAFEA FOREIGN KEY (entreprise_id) REFERENCES entreprise (id)');
        $this->addSql('CREATE INDEX IDX_D499BFF6A4AEAFEA ON planning (entreprise_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE planning DROP FOREIGN KEY FK_D499BFF6A4AEAFEA');
        $this->addSql('DROP INDEX IDX_D499BFF6A4AEAFEA ON planning');
        $this->addSql('ALTER TABLE planning ADD pause TIME DEFAULT NULL, ADD pausette TIME DEFAULT NULL, DROP pause_minutes, DROP created_at, DROP entreprise_id, CHANGE heure_debut heure_debut TIME NOT NULL, CHANGE heure_fin heure_fin TIME NOT NULL');
    }
}
