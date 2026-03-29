<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260329033008 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Change company address column from TEXT to JSON type';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE company MODIFY address JSON');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE company MODIFY address TEXT');
    }
}
