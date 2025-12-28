<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251228020922 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE observation (id UUID NOT NULL, contenu TEXT NOT NULL, niveau VARCHAR(20) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, assesseur_id UUID NOT NULL, bureau_de_vote_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_C576DBE06A0AF12C ON observation (assesseur_id)');
        $this->addSql('CREATE INDEX IDX_C576DBE044298AD6 ON observation (bureau_de_vote_id)');
        $this->addSql('ALTER TABLE observation ADD CONSTRAINT FK_C576DBE06A0AF12C FOREIGN KEY (assesseur_id) REFERENCES "user" (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE observation ADD CONSTRAINT FK_C576DBE044298AD6 FOREIGN KEY (bureau_de_vote_id) REFERENCES bureau_de_vote (id) NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE observation DROP CONSTRAINT FK_C576DBE06A0AF12C');
        $this->addSql('ALTER TABLE observation DROP CONSTRAINT FK_C576DBE044298AD6');
        $this->addSql('DROP TABLE observation');
    }
}
