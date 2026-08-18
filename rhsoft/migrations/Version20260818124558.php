<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260818124558 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE avance_salaire (id INT AUTO_INCREMENT NOT NULL, montant DOUBLE PRECISION NOT NULL, motif LONGTEXT NOT NULL, statut VARCHAR(20) NOT NULL, date_demande DATE NOT NULL, date_remboursement DATE DEFAULT NULL, created_at DATETIME NOT NULL, commentaire LONGTEXT DEFAULT NULL, employee_id INT NOT NULL, entreprise_id INT NOT NULL, valide_par_id INT DEFAULT NULL, INDEX IDX_6DA8D1058C03F15C (employee_id), INDEX IDX_6DA8D105A4AEAFEA (entreprise_id), INDEX IDX_6DA8D1056AF12ED9 (valide_par_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE candidature (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(255) NOT NULL, email VARCHAR(255) NOT NULL, cv VARCHAR(255) DEFAULT NULL, source VARCHAR(20) DEFAULT \'site\' NOT NULL, created_at DATETIME NOT NULL, telephone VARCHAR(255) NOT NULL, lettre_motivation LONGTEXT NOT NULL, statut VARCHAR(20) DEFAULT \'en_attente\' NOT NULL, offre_id INT NOT NULL, INDEX IDX_E33BD3B84CC8505A (offre_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE conge (id INT AUTO_INCREMENT NOT NULL, date_debut DATE NOT NULL, date_fin DATE NOT NULL, nb_jours DOUBLE PRECISION DEFAULT NULL, statut VARCHAR(20) NOT NULL, motif LONGTEXT DEFAULT NULL, valide_le DATE DEFAULT NULL, created_at DATETIME NOT NULL, employee_id INT NOT NULL, entreprise_id INT NOT NULL, type_conge_id INT NOT NULL, valide_par_id INT DEFAULT NULL, INDEX IDX_2ED893488C03F15C (employee_id), INDEX IDX_2ED89348A4AEAFEA (entreprise_id), INDEX IDX_2ED89348753BDA5 (type_conge_id), INDEX IDX_2ED893486AF12ED9 (valide_par_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE demission (id INT AUTO_INCREMENT NOT NULL, date_depart DATE NOT NULL, date_demande DATE NOT NULL, motif LONGTEXT NOT NULL, statut VARCHAR(50) NOT NULL, employee_id INT NOT NULL, entreprise_id INT NOT NULL, INDEX IDX_7286479B8C03F15C (employee_id), INDEX IDX_7286479BA4AEAFEA (entreprise_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE entreprise (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(255) NOT NULL, nif VARCHAR(255) DEFAULT NULL, logo VARCHAR(255) DEFAULT NULL, adresse LONGTEXT DEFAULT NULL, tel VARCHAR(255) DEFAULT NULL, email VARCHAR(255) NOT NULL, status VARCHAR(20) NOT NULL, plan VARCHAR(20) NOT NULL, date_fin_abonnement DATETIME DEFAULT NULL, stripe_customer_id VARCHAR(255) DEFAULT NULL, stripe_subscription_id VARCHAR(255) DEFAULT NULL, slug VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, prix_mois NUMERIC(10, 2) DEFAULT NULL, module_paie TINYINT DEFAULT 0 NOT NULL, module_pointage TINYINT DEFAULT 0 NOT NULL, module_rh TINYINT DEFAULT 0 NOT NULL, tolerance_retard INT DEFAULT 15 NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE offre (id INT AUTO_INCREMENT NOT NULL, titre VARCHAR(255) NOT NULL, description LONGTEXT NOT NULL, status VARCHAR(20) DEFAULT \'ouverte\' NOT NULL, date_expiration DATE DEFAULT NULL, created_at DATETIME NOT NULL, entreprise_id INT NOT NULL, INDEX IDX_AF86866FA4AEAFEA (entreprise_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE paie (id INT AUTO_INCREMENT NOT NULL, mois INT NOT NULL, annee INT NOT NULL, salaire_brut NUMERIC(10, 2) DEFAULT NULL, cotisations NUMERIC(10, 2) DEFAULT NULL, salaire_net NUMERIC(10, 2) DEFAULT NULL, status VARCHAR(255) NOT NULL, masse_salariale DOUBLE PRECISION DEFAULT NULL, employee_id INT NOT NULL, INDEX IDX_8A899BAE8C03F15C (employee_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE planning (id INT AUTO_INCREMENT NOT NULL, day_of_week VARCHAR(20) NOT NULL, week_start DATE NOT NULL, heure_debut TIME DEFAULT NULL, heure_fin TIME DEFAULT NULL, pause_minutes INT DEFAULT NULL, type_jour VARCHAR(20) NOT NULL, comment LONGTEXT DEFAULT NULL, status VARCHAR(20) NOT NULL, created_at DATETIME NOT NULL, validated_at DATETIME DEFAULT NULL, user_id INT NOT NULL, entreprise_id INT NOT NULL, validated_by_id INT DEFAULT NULL, INDEX IDX_D499BFF6A76ED395 (user_id), INDEX IDX_D499BFF6A4AEAFEA (entreprise_id), INDEX IDX_D499BFF6C69DE5E5 (validated_by_id), UNIQUE INDEX unique_planning_per_day (user_id, week_start, day_of_week), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE pointage (id INT AUTO_INCREMENT NOT NULL, date DATE NOT NULL, heure_entree TIME DEFAULT NULL, heure_sortie TIME DEFAULT NULL, pause_minutes INT DEFAULT 60 NOT NULL, statut VARCHAR(20) DEFAULT \'absent\' NOT NULL, valide TINYINT DEFAULT 0 NOT NULL, motif_correction LONGTEXT DEFAULT NULL, corrige_le DATETIME DEFAULT NULL, heure_prevue_debut TIME DEFAULT NULL, heure_prevue_fin TIME DEFAULT NULL, pause_prevue_minutes INT DEFAULT 60 NOT NULL, pause_duration_minutes INT DEFAULT NULL, heure_debut_pause TIME DEFAULT NULL, heure_fin_pause TIME DEFAULT NULL, employee_id INT NOT NULL, entreprise_id INT NOT NULL, corrige_par_id INT DEFAULT NULL, INDEX IDX_7591B208C03F15C (employee_id), INDEX IDX_7591B20A4AEAFEA (entreprise_id), INDEX IDX_7591B20C99BFB58 (corrige_par_id), UNIQUE INDEX unique_pointage_user_date (employee_id, date), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE system_log (id INT AUTO_INCREMENT NOT NULL, logged_at DATETIME DEFAULT NULL, level VARCHAR(50) DEFAULT NULL, message LONGTEXT DEFAULT NULL, source_file VARCHAR(255) DEFAULT NULL, ip_address VARCHAR(255) DEFAULT NULL, user_email VARCHAR(255) DEFAULT NULL, company_name VARCHAR(255) DEFAULT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE type_conge (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(255) NOT NULL, code VARCHAR(50) NOT NULL, jours_annuels INT DEFAULT NULL, paye TINYINT NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE user (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) DEFAULT NULL, nom VARCHAR(255) NOT NULL, prenom VARCHAR(255) NOT NULL, adresse VARCHAR(255) DEFAULT NULL, telephone VARCHAR(20) DEFAULT NULL, photo VARCHAR(255) DEFAULT NULL, cv VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, is_active TINYINT NOT NULL, email_verified_at DATETIME DEFAULT NULL, last_login_at DATETIME DEFAULT NULL, is_verified TINYINT NOT NULL, invitation_token VARCHAR(255) DEFAULT NULL, invitation_expires_at DATETIME DEFAULT NULL, first_login TINYINT NOT NULL, banque_nom VARCHAR(255) DEFAULT NULL, banque_iban VARCHAR(50) DEFAULT NULL, banque_rib VARCHAR(50) DEFAULT NULL, date_embauche DATETIME DEFAULT NULL, solde_conge DOUBLE PRECISION DEFAULT NULL, poste VARCHAR(100) DEFAULT NULL, service VARCHAR(100) DEFAULT NULL, heures_contractuelles NUMERIC(4, 2) DEFAULT 8 NOT NULL, date_sortie DATETIME DEFAULT NULL, motif_sortie VARCHAR(100) DEFAULT NULL, salaire_base DOUBLE PRECISION DEFAULT NULL, panier_repas DOUBLE PRECISION DEFAULT 0 NOT NULL, transport DOUBLE PRECISION DEFAULT 0 NOT NULL, anciennete DOUBLE PRECISION DEFAULT 0 NOT NULL, personnes_acharge INT DEFAULT 0 NOT NULL, entreprise_id INT NOT NULL, INDEX IDX_8D93D649A4AEAFEA (entreprise_id), UNIQUE INDEX UNIQ_IDENTIFIER_EMAIL (email), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL, INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 (queue_name, available_at, delivered_at, id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE avance_salaire ADD CONSTRAINT FK_6DA8D1058C03F15C FOREIGN KEY (employee_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE avance_salaire ADD CONSTRAINT FK_6DA8D105A4AEAFEA FOREIGN KEY (entreprise_id) REFERENCES entreprise (id)');
        $this->addSql('ALTER TABLE avance_salaire ADD CONSTRAINT FK_6DA8D1056AF12ED9 FOREIGN KEY (valide_par_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE candidature ADD CONSTRAINT FK_E33BD3B84CC8505A FOREIGN KEY (offre_id) REFERENCES offre (id)');
        $this->addSql('ALTER TABLE conge ADD CONSTRAINT FK_2ED893488C03F15C FOREIGN KEY (employee_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE conge ADD CONSTRAINT FK_2ED89348A4AEAFEA FOREIGN KEY (entreprise_id) REFERENCES entreprise (id)');
        $this->addSql('ALTER TABLE conge ADD CONSTRAINT FK_2ED89348753BDA5 FOREIGN KEY (type_conge_id) REFERENCES type_conge (id)');
        $this->addSql('ALTER TABLE conge ADD CONSTRAINT FK_2ED893486AF12ED9 FOREIGN KEY (valide_par_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE demission ADD CONSTRAINT FK_7286479B8C03F15C FOREIGN KEY (employee_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE demission ADD CONSTRAINT FK_7286479BA4AEAFEA FOREIGN KEY (entreprise_id) REFERENCES entreprise (id)');
        $this->addSql('ALTER TABLE offre ADD CONSTRAINT FK_AF86866FA4AEAFEA FOREIGN KEY (entreprise_id) REFERENCES entreprise (id)');
        $this->addSql('ALTER TABLE paie ADD CONSTRAINT FK_8A899BAE8C03F15C FOREIGN KEY (employee_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE planning ADD CONSTRAINT FK_D499BFF6A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE planning ADD CONSTRAINT FK_D499BFF6A4AEAFEA FOREIGN KEY (entreprise_id) REFERENCES entreprise (id)');
        $this->addSql('ALTER TABLE planning ADD CONSTRAINT FK_D499BFF6C69DE5E5 FOREIGN KEY (validated_by_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE pointage ADD CONSTRAINT FK_7591B208C03F15C FOREIGN KEY (employee_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE pointage ADD CONSTRAINT FK_7591B20A4AEAFEA FOREIGN KEY (entreprise_id) REFERENCES entreprise (id)');
        $this->addSql('ALTER TABLE pointage ADD CONSTRAINT FK_7591B20C99BFB58 FOREIGN KEY (corrige_par_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE user ADD CONSTRAINT FK_8D93D649A4AEAFEA FOREIGN KEY (entreprise_id) REFERENCES entreprise (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE avance_salaire DROP FOREIGN KEY FK_6DA8D1058C03F15C');
        $this->addSql('ALTER TABLE avance_salaire DROP FOREIGN KEY FK_6DA8D105A4AEAFEA');
        $this->addSql('ALTER TABLE avance_salaire DROP FOREIGN KEY FK_6DA8D1056AF12ED9');
        $this->addSql('ALTER TABLE candidature DROP FOREIGN KEY FK_E33BD3B84CC8505A');
        $this->addSql('ALTER TABLE conge DROP FOREIGN KEY FK_2ED893488C03F15C');
        $this->addSql('ALTER TABLE conge DROP FOREIGN KEY FK_2ED89348A4AEAFEA');
        $this->addSql('ALTER TABLE conge DROP FOREIGN KEY FK_2ED89348753BDA5');
        $this->addSql('ALTER TABLE conge DROP FOREIGN KEY FK_2ED893486AF12ED9');
        $this->addSql('ALTER TABLE demission DROP FOREIGN KEY FK_7286479B8C03F15C');
        $this->addSql('ALTER TABLE demission DROP FOREIGN KEY FK_7286479BA4AEAFEA');
        $this->addSql('ALTER TABLE offre DROP FOREIGN KEY FK_AF86866FA4AEAFEA');
        $this->addSql('ALTER TABLE paie DROP FOREIGN KEY FK_8A899BAE8C03F15C');
        $this->addSql('ALTER TABLE planning DROP FOREIGN KEY FK_D499BFF6A76ED395');
        $this->addSql('ALTER TABLE planning DROP FOREIGN KEY FK_D499BFF6A4AEAFEA');
        $this->addSql('ALTER TABLE planning DROP FOREIGN KEY FK_D499BFF6C69DE5E5');
        $this->addSql('ALTER TABLE pointage DROP FOREIGN KEY FK_7591B208C03F15C');
        $this->addSql('ALTER TABLE pointage DROP FOREIGN KEY FK_7591B20A4AEAFEA');
        $this->addSql('ALTER TABLE pointage DROP FOREIGN KEY FK_7591B20C99BFB58');
        $this->addSql('ALTER TABLE user DROP FOREIGN KEY FK_8D93D649A4AEAFEA');
        $this->addSql('DROP TABLE avance_salaire');
        $this->addSql('DROP TABLE candidature');
        $this->addSql('DROP TABLE conge');
        $this->addSql('DROP TABLE demission');
        $this->addSql('DROP TABLE entreprise');
        $this->addSql('DROP TABLE offre');
        $this->addSql('DROP TABLE paie');
        $this->addSql('DROP TABLE planning');
        $this->addSql('DROP TABLE pointage');
        $this->addSql('DROP TABLE system_log');
        $this->addSql('DROP TABLE type_conge');
        $this->addSql('DROP TABLE user');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
