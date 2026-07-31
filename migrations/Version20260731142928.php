<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260731142928 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE candidature (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(255) NOT NULL, email VARCHAR(255) NOT NULL, cv VARCHAR(255) DEFAULT NULL, status VARCHAR(20) DEFAULT \'nouveau\' NOT NULL, source VARCHAR(20) DEFAULT \'site\' NOT NULL, created_at DATETIME NOT NULL, telephone VARCHAR(255) NOT NULL, lettre_motivation VARCHAR(255) NOT NULL, offre_id INT NOT NULL, INDEX IDX_E33BD3B84CC8505A (offre_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE offre (id INT AUTO_INCREMENT NOT NULL, titre VARCHAR(255) NOT NULL, description LONGTEXT NOT NULL, status VARCHAR(20) DEFAULT \'ouverte\' NOT NULL, date_expiration DATE DEFAULT NULL, created_at DATETIME NOT NULL, entreprise_id INT NOT NULL, INDEX IDX_AF86866FA4AEAFEA (entreprise_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE candidature ADD CONSTRAINT FK_E33BD3B84CC8505A FOREIGN KEY (offre_id) REFERENCES offre (id)');
        $this->addSql('ALTER TABLE offre ADD CONSTRAINT FK_AF86866FA4AEAFEA FOREIGN KEY (entreprise_id) REFERENCES entreprise (id)');
        $this->addSql('ALTER TABLE user CHANGE heures_contractuelles heures_contractuelles NUMERIC(4, 2) DEFAULT 8 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE candidature DROP FOREIGN KEY FK_E33BD3B84CC8505A');
        $this->addSql('ALTER TABLE offre DROP FOREIGN KEY FK_AF86866FA4AEAFEA');
        $this->addSql('DROP TABLE candidature');
        $this->addSql('DROP TABLE offre');
        $this->addSql('ALTER TABLE user CHANGE heures_contractuelles heures_contractuelles NUMERIC(4, 2) DEFAULT \'8.00\' NOT NULL');
    }
}
