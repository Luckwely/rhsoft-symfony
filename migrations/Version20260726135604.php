<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260726135604 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE system_log (id INT AUTO_INCREMENT NOT NULL, logged_at DATETIME DEFAULT NULL, level VARCHAR(50) DEFAULT NULL, message LONGTEXT DEFAULT NULL, source_file VARCHAR(255) DEFAULT NULL, ip_address VARCHAR(255) DEFAULT NULL, user_email VARCHAR(255) DEFAULT NULL, company_name VARCHAR(255) DEFAULT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE user CHANGE roles roles JSON NOT NULL, CHANGE entreprise_id entreprise_id INT NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE system_log');
        $this->addSql('ALTER TABLE user CHANGE roles roles LONGTEXT NOT NULL COLLATE `utf8mb4_bin`, CHANGE entreprise_id entreprise_id INT DEFAULT NULL');
    }
}
