<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260801084545 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE avance_salaire (id INT AUTO_INCREMENT NOT NULL, montant DOUBLE PRECISION NOT NULL, motif LONGTEXT NOT NULL, statut VARCHAR(20) NOT NULL, date_demande DATE NOT NULL, date_remboursement DATE DEFAULT NULL, created_at DATETIME NOT NULL, employee_id INT NOT NULL, entreprise_id INT NOT NULL, valide_par_id INT DEFAULT NULL, INDEX IDX_6DA8D1058C03F15C (employee_id), INDEX IDX_6DA8D105A4AEAFEA (entreprise_id), INDEX IDX_6DA8D1056AF12ED9 (valide_par_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE avance_salaire ADD CONSTRAINT FK_6DA8D1058C03F15C FOREIGN KEY (employee_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE avance_salaire ADD CONSTRAINT FK_6DA8D105A4AEAFEA FOREIGN KEY (entreprise_id) REFERENCES entreprise (id)');
        $this->addSql('ALTER TABLE avance_salaire ADD CONSTRAINT FK_6DA8D1056AF12ED9 FOREIGN KEY (valide_par_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE user CHANGE heures_contractuelles heures_contractuelles NUMERIC(4, 2) DEFAULT 8 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE avance_salaire DROP FOREIGN KEY FK_6DA8D1058C03F15C');
        $this->addSql('ALTER TABLE avance_salaire DROP FOREIGN KEY FK_6DA8D105A4AEAFEA');
        $this->addSql('ALTER TABLE avance_salaire DROP FOREIGN KEY FK_6DA8D1056AF12ED9');
        $this->addSql('DROP TABLE avance_salaire');
        $this->addSql('ALTER TABLE user CHANGE heures_contractuelles heures_contractuelles NUMERIC(4, 2) DEFAULT \'8.00\' NOT NULL');
    }
}
