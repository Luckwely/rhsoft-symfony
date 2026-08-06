<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260806184552 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE demission (id INT AUTO_INCREMENT NOT NULL, date_depart DATE NOT NULL, date_demande DATE NOT NULL, motif LONGTEXT NOT NULL, statut VARCHAR(50) NOT NULL, employee_id INT NOT NULL, entreprise_id INT NOT NULL, INDEX IDX_7286479B8C03F15C (employee_id), INDEX IDX_7286479BA4AEAFEA (entreprise_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE demission ADD CONSTRAINT FK_7286479B8C03F15C FOREIGN KEY (employee_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE demission ADD CONSTRAINT FK_7286479BA4AEAFEA FOREIGN KEY (entreprise_id) REFERENCES entreprise (id)');
        $this->addSql('ALTER TABLE user CHANGE heures_contractuelles heures_contractuelles NUMERIC(4, 2) DEFAULT 8 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE demission DROP FOREIGN KEY FK_7286479B8C03F15C');
        $this->addSql('ALTER TABLE demission DROP FOREIGN KEY FK_7286479BA4AEAFEA');
        $this->addSql('DROP TABLE demission');
        $this->addSql('ALTER TABLE user CHANGE heures_contractuelles heures_contractuelles NUMERIC(4, 2) DEFAULT \'8.00\' NOT NULL');
    }
}
