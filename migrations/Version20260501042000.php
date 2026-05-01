<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260501042000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Crear tabla customer_import_log para soportar importación de clientes';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE customer_import_log (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, filename VARCHAR(255) NOT NULL, original_filename VARCHAR(255) NOT NULL, total_rows INT NOT NULL, processed_rows INT NOT NULL, created_count INT NOT NULL, updated_count INT NOT NULL, error_count INT NOT NULL, errors JSON DEFAULT NULL, status VARCHAR(20) NOT NULL, created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', completed_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', INDEX IDX_82D8A3CCA76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        $this->addSql('ALTER TABLE customer_import_log ADD CONSTRAINT FK_82D8A3CCA76ED395 FOREIGN KEY (user_id) REFERENCES app_user (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE customer_import_log DROP FOREIGN KEY FK_82D8A3CCA76ED395');
        $this->addSql('DROP TABLE customer_import_log');
    }
}
