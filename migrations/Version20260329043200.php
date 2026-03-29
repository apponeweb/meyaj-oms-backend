<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add image column to branch table
 */
final class Version20260329043200 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add image column to branch table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE branch ADD image LONGTEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE branch DROP image');
    }
}
