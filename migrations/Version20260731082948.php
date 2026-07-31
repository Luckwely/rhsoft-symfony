<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260731082948 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE offre ADD created_at DATETIME NOT NULL, CHANGE description description LONGTEXT NOT NULL, CHANGE status status VARCHAR(20) DEFAULT \'ouverte\' NOT NULL, CHANGE date_expiration date_expiration DATE DEFAULT NULL, CHANGE entreprise_id entreprise_id INT NOT NULL');
        $this->addSql('ALTER TABLE user CHANGE heures_contractuelles heures_contractuelles NUMERIC(4, 2) DEFAULT 8 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE offre DROP created_at, CHANGE description description VARCHAR(255) NOT NULL, CHANGE status status VARCHAR(255) NOT NULL, CHANGE date_expiration date_expiration DATE NOT NULL, CHANGE entreprise_id entreprise_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE user CHANGE heures_contractuelles heures_contractuelles NUMERIC(4, 2) DEFAULT \'8.00\' NOT NULL');
    }
}
