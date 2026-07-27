<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260727071856 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE entreprise (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(255) NOT NULL, nif VARCHAR(255) DEFAULT NULL, logo VARCHAR(255) DEFAULT NULL, adresse LONGTEXT DEFAULT NULL, tel VARCHAR(255) DEFAULT NULL, email VARCHAR(255) NOT NULL, status VARCHAR(20) NOT NULL, plan VARCHAR(20) NOT NULL, date_fin_abonnement DATETIME DEFAULT NULL, stripe_customer_id VARCHAR(255) DEFAULT NULL, stripe_subscription_id VARCHAR(255) DEFAULT NULL, slug VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, prix_mois NUMERIC(10, 2) DEFAULT NULL, module_paie TINYINT DEFAULT 0 NOT NULL, module_pointage TINYINT DEFAULT 0 NOT NULL, module_rh TINYINT DEFAULT 0 NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE planning (id INT AUTO_INCREMENT NOT NULL, day_of_week VARCHAR(20) NOT NULL, week_start DATE NOT NULL, heure_debut TIME NOT NULL, heure_fin TIME NOT NULL, pause TIME DEFAULT NULL, pausette TIME DEFAULT NULL, is_day_off TINYINT NOT NULL, comment LONGTEXT DEFAULT NULL, user_id INT NOT NULL, INDEX IDX_D499BFF6A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE system_log (id INT AUTO_INCREMENT NOT NULL, logged_at DATETIME DEFAULT NULL, level VARCHAR(50) DEFAULT NULL, message LONGTEXT DEFAULT NULL, source_file VARCHAR(255) DEFAULT NULL, ip_address VARCHAR(255) DEFAULT NULL, user_email VARCHAR(255) DEFAULT NULL, company_name VARCHAR(255) DEFAULT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE user (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) DEFAULT NULL, nom VARCHAR(255) NOT NULL, prenom VARCHAR(255) NOT NULL, adresse VARCHAR(255) DEFAULT NULL, telephone VARCHAR(20) DEFAULT NULL, photo VARCHAR(255) DEFAULT NULL, cv VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, is_active TINYINT NOT NULL, email_verified_at DATETIME DEFAULT NULL, last_login_at DATETIME DEFAULT NULL, is_verified TINYINT NOT NULL, invitation_token VARCHAR(255) DEFAULT NULL, invitation_expires_at DATETIME DEFAULT NULL, first_login TINYINT NOT NULL, banque_nom VARCHAR(255) DEFAULT NULL, banque_iban VARCHAR(50) DEFAULT NULL, banque_rib VARCHAR(50) DEFAULT NULL, date_embauche DATETIME DEFAULT NULL, solde_conge DOUBLE PRECISION DEFAULT NULL, entreprise_id INT NOT NULL, INDEX IDX_8D93D649A4AEAFEA (entreprise_id), UNIQUE INDEX UNIQ_IDENTIFIER_EMAIL (email), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL, INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 (queue_name, available_at, delivered_at, id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE planning ADD CONSTRAINT FK_D499BFF6A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE user ADD CONSTRAINT FK_8D93D649A4AEAFEA FOREIGN KEY (entreprise_id) REFERENCES entreprise (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE planning DROP FOREIGN KEY FK_D499BFF6A76ED395');
        $this->addSql('ALTER TABLE user DROP FOREIGN KEY FK_8D93D649A4AEAFEA');
        $this->addSql('DROP TABLE entreprise');
        $this->addSql('DROP TABLE planning');
        $this->addSql('DROP TABLE system_log');
        $this->addSql('DROP TABLE user');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
