<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260403214000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Supprime les defaults SQL des paramètres avancés IA';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ai_provider_config ALTER temperature DROP DEFAULT');
        $this->addSql('ALTER TABLE ai_provider_config ALTER timeout_seconds DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE ai_provider_config ALTER temperature SET DEFAULT '0.2'");
        $this->addSql("ALTER TABLE ai_provider_config ALTER timeout_seconds SET DEFAULT 20");
    }
}
