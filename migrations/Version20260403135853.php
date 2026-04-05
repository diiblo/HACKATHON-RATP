<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260403135853 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute visibilité et catégorie aux pièces jointes';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE piece_jointe ADD visibility VARCHAR(20) DEFAULT 'internal' NOT NULL");
        $this->addSql("ALTER TABLE piece_jointe ADD category VARCHAR(30) DEFAULT 'document' NOT NULL");
        $this->addSql("UPDATE piece_jointe SET visibility = 'public', category = 'public_submission' WHERE uploaded_by_id IS NULL");
        $this->addSql('ALTER TABLE piece_jointe ALTER visibility DROP DEFAULT');
        $this->addSql('ALTER TABLE piece_jointe ALTER category DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE piece_jointe DROP visibility');
        $this->addSql('ALTER TABLE piece_jointe DROP category');
    }
}
