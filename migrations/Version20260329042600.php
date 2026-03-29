<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Change branch.address column from TEXT to JSON to store structured address data.
 */
final class Version20260329042600 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Change branch.address column from TEXT to JSON';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE branch CHANGE address address JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE branch CHANGE address address LONGTEXT DEFAULT NULL');
    }
}
