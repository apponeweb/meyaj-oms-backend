<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260330220406 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE supplier ADD contacts JSON DEFAULT NULL, DROP contact_name, DROP email, DROP phone');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE supplier ADD contact_name VARCHAR(150) DEFAULT NULL, ADD email VARCHAR(255) DEFAULT NULL, ADD phone VARCHAR(20) DEFAULT NULL, DROP contacts');
    }
}
