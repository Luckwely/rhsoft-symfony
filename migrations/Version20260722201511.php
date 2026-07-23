<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260722201511 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE entreprise ADD module_paie TINYINT NOT NULL, ADD module_pointage TINYINT NOT NULL, ADD module_rh TINYINT NOT NULL');
        $this->addSql('ALTER TABLE user CHANGE entreprise_id entreprise_id INT NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE entreprise DROP module_paie, DROP module_pointage, DROP module_rh');
        $this->addSql('ALTER TABLE user CHANGE entreprise_id entreprise_id INT DEFAULT NULL');
    }
}
