<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260403194000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Supprime le default SQL sur le journal JSON des courriers';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE courrier_draft ALTER dispatch_journal DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE courrier_draft ALTER dispatch_journal SET DEFAULT '[]'");
    }
}
