<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260403203000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute le contexte terrain, la simulation vocale et le dépôt de plainte sur les signalements';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE signalement ADD source_line VARCHAR(20) DEFAULT NULL');
        $this->addSql('ALTER TABLE signalement ADD source_vehicle VARCHAR(40) DEFAULT NULL');
        $this->addSql('ALTER TABLE signalement ADD source_stop VARCHAR(120) DEFAULT NULL');
        $this->addSql('ALTER TABLE signalement ADD source_entry_mode VARCHAR(30) DEFAULT NULL');
        $this->addSql('ALTER TABLE signalement ADD source_platform VARCHAR(30) DEFAULT NULL');
        $this->addSql('ALTER TABLE signalement ADD source_language VARCHAR(10) DEFAULT NULL');
        $this->addSql('ALTER TABLE signalement ADD voice_transcript TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE signalement ADD translated_description TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE signalement ADD plainte_deposee_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE signalement ADD plainte_commentaire TEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE signalement DROP source_line');
        $this->addSql('ALTER TABLE signalement DROP source_vehicle');
        $this->addSql('ALTER TABLE signalement DROP source_stop');
        $this->addSql('ALTER TABLE signalement DROP source_entry_mode');
        $this->addSql('ALTER TABLE signalement DROP source_platform');
        $this->addSql('ALTER TABLE signalement DROP source_language');
        $this->addSql('ALTER TABLE signalement DROP voice_transcript');
        $this->addSql('ALTER TABLE signalement DROP translated_description');
        $this->addSql('ALTER TABLE signalement DROP plainte_deposee_at');
        $this->addSql('ALTER TABLE signalement DROP plainte_commentaire');
    }
}
