<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260405161845 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE signalement ADD plainant_nom VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE signalement ADD plainant_email VARCHAR(150) DEFAULT NULL');
        $this->addSql('ALTER TABLE signalement ADD plainant_telephone VARCHAR(20) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE signalement DROP plainant_nom');
        $this->addSql('ALTER TABLE signalement DROP plainant_email');
        $this->addSql('ALTER TABLE signalement DROP plainant_telephone');
    }
}
