<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260403193000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute le suivi Maileva simulé sur les courriers';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE courrier_draft ADD dispatch_status VARCHAR(20) DEFAULT NULL");
        $this->addSql("ALTER TABLE courrier_draft ADD dispatch_reference VARCHAR(80) DEFAULT NULL");
        $this->addSql("ALTER TABLE courrier_draft ADD dispatched_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL");
        $this->addSql("ALTER TABLE courrier_draft ADD last_dispatch_update_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL");
        $this->addSql("ALTER TABLE courrier_draft ADD dispatch_journal JSON NOT NULL DEFAULT '[]'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE courrier_draft DROP dispatch_status');
        $this->addSql('ALTER TABLE courrier_draft DROP dispatch_reference');
        $this->addSql('ALTER TABLE courrier_draft DROP dispatched_at');
        $this->addSql('ALTER TABLE courrier_draft DROP last_dispatch_update_at');
        $this->addSql('ALTER TABLE courrier_draft DROP dispatch_journal');
    }
}
