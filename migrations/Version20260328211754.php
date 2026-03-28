<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260328211754 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE fabric_type (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(100) NOT NULL, description VARCHAR(255) DEFAULT NULL, active TINYINT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_F6B419FB5E237E06 (name), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE garment_type (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(100) NOT NULL, description VARCHAR(255) DEFAULT NULL, active TINYINT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_2D3D3FD95E237E06 (name), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE gender_catalog (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(100) NOT NULL, description VARCHAR(255) DEFAULT NULL, active TINYINT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_E9C447FB5E237E06 (name), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE label_catalog (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(100) NOT NULL, description VARCHAR(255) DEFAULT NULL, active TINYINT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_A7AD9B6E5E237E06 (name), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE paca (id INT AUTO_INCREMENT NOT NULL, code VARCHAR(50) NOT NULL, name VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, purchase_price NUMERIC(10, 2) NOT NULL, selling_price NUMERIC(10, 2) NOT NULL, stock INT NOT NULL, piece_count INT DEFAULT NULL, weight NUMERIC(8, 2) DEFAULT NULL, active TINYINT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, brand_id INT DEFAULT NULL, label_id INT DEFAULT NULL, quality_grade_id INT DEFAULT NULL, season_id INT DEFAULT NULL, gender_id INT DEFAULT NULL, garment_type_id INT DEFAULT NULL, fabric_type_id INT DEFAULT NULL, size_profile_id INT DEFAULT NULL, supplier_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_770BB73D77153098 (code), INDEX IDX_770BB73D44F5D008 (brand_id), INDEX IDX_770BB73D33B92F39 (label_id), INDEX IDX_770BB73DEA7929DC (quality_grade_id), INDEX IDX_770BB73D4EC001D1 (season_id), INDEX IDX_770BB73D708A0E0 (gender_id), INDEX IDX_770BB73DE4AFAD8F (garment_type_id), INDEX IDX_770BB73D88DC877A (fabric_type_id), INDEX IDX_770BB73D161591CD (size_profile_id), INDEX IDX_770BB73D2ADD6D8C (supplier_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE quality_grade (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(100) NOT NULL, description VARCHAR(255) DEFAULT NULL, active TINYINT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_4DF466075E237E06 (name), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE season_catalog (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(100) NOT NULL, description VARCHAR(255) DEFAULT NULL, active TINYINT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_3E559B195E237E06 (name), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE size_profile (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(100) NOT NULL, description VARCHAR(255) DEFAULT NULL, active TINYINT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_643A78775E237E06 (name), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE paca ADD CONSTRAINT FK_770BB73D44F5D008 FOREIGN KEY (brand_id) REFERENCES brand (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE paca ADD CONSTRAINT FK_770BB73D33B92F39 FOREIGN KEY (label_id) REFERENCES label_catalog (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE paca ADD CONSTRAINT FK_770BB73DEA7929DC FOREIGN KEY (quality_grade_id) REFERENCES quality_grade (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE paca ADD CONSTRAINT FK_770BB73D4EC001D1 FOREIGN KEY (season_id) REFERENCES season_catalog (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE paca ADD CONSTRAINT FK_770BB73D708A0E0 FOREIGN KEY (gender_id) REFERENCES gender_catalog (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE paca ADD CONSTRAINT FK_770BB73DE4AFAD8F FOREIGN KEY (garment_type_id) REFERENCES garment_type (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE paca ADD CONSTRAINT FK_770BB73D88DC877A FOREIGN KEY (fabric_type_id) REFERENCES fabric_type (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE paca ADD CONSTRAINT FK_770BB73D161591CD FOREIGN KEY (size_profile_id) REFERENCES size_profile (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE paca ADD CONSTRAINT FK_770BB73D2ADD6D8C FOREIGN KEY (supplier_id) REFERENCES supplier (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE paca DROP FOREIGN KEY FK_770BB73D44F5D008');
        $this->addSql('ALTER TABLE paca DROP FOREIGN KEY FK_770BB73D33B92F39');
        $this->addSql('ALTER TABLE paca DROP FOREIGN KEY FK_770BB73DEA7929DC');
        $this->addSql('ALTER TABLE paca DROP FOREIGN KEY FK_770BB73D4EC001D1');
        $this->addSql('ALTER TABLE paca DROP FOREIGN KEY FK_770BB73D708A0E0');
        $this->addSql('ALTER TABLE paca DROP FOREIGN KEY FK_770BB73DE4AFAD8F');
        $this->addSql('ALTER TABLE paca DROP FOREIGN KEY FK_770BB73D88DC877A');
        $this->addSql('ALTER TABLE paca DROP FOREIGN KEY FK_770BB73D161591CD');
        $this->addSql('ALTER TABLE paca DROP FOREIGN KEY FK_770BB73D2ADD6D8C');
        $this->addSql('DROP TABLE fabric_type');
        $this->addSql('DROP TABLE garment_type');
        $this->addSql('DROP TABLE gender_catalog');
        $this->addSql('DROP TABLE label_catalog');
        $this->addSql('DROP TABLE paca');
        $this->addSql('DROP TABLE quality_grade');
        $this->addSql('DROP TABLE season_catalog');
        $this->addSql('DROP TABLE size_profile');
    }
}
