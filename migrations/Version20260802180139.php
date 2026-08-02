<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260802180139 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE paie ADD cotisations NUMERIC(10, 2) DEFAULT NULL, ADD salaire_net NUMERIC(10, 2) DEFAULT NULL, ADD employee_id INT NOT NULL, CHANGE masse_salariale salaire_brut NUMERIC(10, 2) DEFAULT NULL');
        $this->addSql('ALTER TABLE paie ADD CONSTRAINT FK_8A899BAE8C03F15C FOREIGN KEY (employee_id) REFERENCES user (id)');
        $this->addSql('CREATE INDEX IDX_8A899BAE8C03F15C ON paie (employee_id)');
        $this->addSql('ALTER TABLE user CHANGE heures_contractuelles heures_contractuelles NUMERIC(4, 2) DEFAULT 8 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE paie DROP FOREIGN KEY FK_8A899BAE8C03F15C');
        $this->addSql('DROP INDEX IDX_8A899BAE8C03F15C ON paie');
        $this->addSql('ALTER TABLE paie ADD masse_salariale NUMERIC(10, 2) DEFAULT NULL, DROP salaire_brut, DROP cotisations, DROP salaire_net, DROP employee_id');
        $this->addSql('ALTER TABLE user CHANGE heures_contractuelles heures_contractuelles NUMERIC(4, 2) DEFAULT \'8.00\' NOT NULL');
    }
}
