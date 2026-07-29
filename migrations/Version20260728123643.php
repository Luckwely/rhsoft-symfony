<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260728123643 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE pointage (id INT AUTO_INCREMENT NOT NULL, date DATE NOT NULL, heure_entree TIME NOT NULL, heure_sortie TIME NOT NULL, pause_minutes TIME NOT NULL, statut VARCHAR(255) NOT NULL, motif_correction VARCHAR(255) NOT NULL, employee_id INT DEFAULT NULL, INDEX IDX_7591B208C03F15C (employee_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE pointage_user (pointage_id INT NOT NULL, user_id INT NOT NULL, INDEX IDX_BA43DA30E58DA11D (pointage_id), INDEX IDX_BA43DA30A76ED395 (user_id), PRIMARY KEY (pointage_id, user_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE pointage ADD CONSTRAINT FK_7591B208C03F15C FOREIGN KEY (employee_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE pointage_user ADD CONSTRAINT FK_BA43DA30E58DA11D FOREIGN KEY (pointage_id) REFERENCES pointage (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE pointage_user ADD CONSTRAINT FK_BA43DA30A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE pointage DROP FOREIGN KEY FK_7591B208C03F15C');
        $this->addSql('ALTER TABLE pointage_user DROP FOREIGN KEY FK_BA43DA30E58DA11D');
        $this->addSql('ALTER TABLE pointage_user DROP FOREIGN KEY FK_BA43DA30A76ED395');
        $this->addSql('DROP TABLE pointage');
        $this->addSql('DROP TABLE pointage_user');
    }
}
