<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260728092353 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE planning ADD status VARCHAR(20) NOT NULL, ADD validated_at DATETIME DEFAULT NULL, ADD validated_by_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE planning ADD CONSTRAINT FK_D499BFF6C69DE5E5 FOREIGN KEY (validated_by_id) REFERENCES user (id)');
        $this->addSql('CREATE INDEX IDX_D499BFF6C69DE5E5 ON planning (validated_by_id)');
        $this->addSql('CREATE UNIQUE INDEX unique_planning_per_day ON planning (user_id, week_start, day_of_week)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE planning DROP FOREIGN KEY FK_D499BFF6C69DE5E5');
        $this->addSql('DROP INDEX IDX_D499BFF6C69DE5E5 ON planning');
        $this->addSql('DROP INDEX unique_planning_per_day ON planning');
        $this->addSql('ALTER TABLE planning DROP status, DROP validated_at, DROP validated_by_id');
    }
}
