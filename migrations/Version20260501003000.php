<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260501003000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Agregar scanned_serials al detalle de conteo para soportar conteo por escaneo unitario';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE inventory_count_detail ADD scanned_serials JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE inventory_count_detail DROP scanned_serials');
    }
}
