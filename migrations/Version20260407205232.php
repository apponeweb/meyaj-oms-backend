<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260407205232 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE paca_unit (id INT AUTO_INCREMENT NOT NULL, serial VARCHAR(30) NOT NULL, status VARCHAR(20) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, paca_id INT NOT NULL, warehouse_id INT NOT NULL, warehouse_bin_id INT DEFAULT NULL, sales_order_id INT DEFAULT NULL, sales_order_item_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_34A1768CD374C9DC (serial), INDEX IDX_34A1768C76A45273 (paca_id), INDEX IDX_34A1768CC77159AD (warehouse_bin_id), INDEX IDX_34A1768C21338DA6 (sales_order_item_id), INDEX idx_paca_unit_paca_status (paca_id, status), INDEX idx_paca_unit_warehouse (warehouse_id), INDEX idx_paca_unit_status (status), INDEX idx_paca_unit_so (sales_order_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE paca_unit ADD CONSTRAINT FK_34A1768C76A45273 FOREIGN KEY (paca_id) REFERENCES paca (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE paca_unit ADD CONSTRAINT FK_34A1768C5080ECDE FOREIGN KEY (warehouse_id) REFERENCES warehouse (id)');
        $this->addSql('ALTER TABLE paca_unit ADD CONSTRAINT FK_34A1768CC77159AD FOREIGN KEY (warehouse_bin_id) REFERENCES warehouse_bin (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE paca_unit ADD CONSTRAINT FK_34A1768CC023F51C FOREIGN KEY (sales_order_id) REFERENCES sales_order (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE paca_unit ADD CONSTRAINT FK_34A1768C21338DA6 FOREIGN KEY (sales_order_item_id) REFERENCES sales_order_item (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE inventory_reservation DROP FOREIGN KEY `FK_363A759A76A45273`');
        $this->addSql('ALTER TABLE inventory_reservation DROP FOREIGN KEY `FK_363A759AA76ED395`');
        $this->addSql('ALTER TABLE paca_location DROP FOREIGN KEY `FK_67335D135080ECDE`');
        $this->addSql('ALTER TABLE paca_location DROP FOREIGN KEY `FK_67335D1376A45273`');
        $this->addSql('ALTER TABLE paca_location DROP FOREIGN KEY `FK_67335D13C77159AD`');
        $this->addSql('DROP TABLE inventory_reservation');
        $this->addSql('DROP TABLE paca_location');
        $this->addSql('ALTER TABLE inventory_movement ADD paca_unit_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE inventory_movement ADD CONSTRAINT FK_40972F6656DA6AEE FOREIGN KEY (paca_unit_id) REFERENCES paca_unit (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX idx_movement_paca_unit ON inventory_movement (paca_unit_id)');
        $this->addSql('ALTER TABLE paca CHANGE stock cached_stock INT NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE inventory_reservation (id INT AUTO_INCREMENT NOT NULL, sales_order_id INT DEFAULT NULL, sales_order_item_id INT DEFAULT NULL, quantity INT NOT NULL, status VARCHAR(20) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_0900_ai_ci`, expires_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, paca_id INT NOT NULL, user_id INT NOT NULL, INDEX IDX_363A759AA76ED395 (user_id), INDEX idx_reservation_paca (paca_id), INDEX idx_reservation_status (status), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_0900_ai_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE paca_location (id INT AUTO_INCREMENT NOT NULL, created_at DATETIME NOT NULL, paca_id INT NOT NULL, warehouse_id INT NOT NULL, warehouse_bin_id INT DEFAULT NULL, INDEX IDX_67335D1376A45273 (paca_id), INDEX IDX_67335D135080ECDE (warehouse_id), INDEX IDX_67335D13C77159AD (warehouse_bin_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_0900_ai_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE inventory_reservation ADD CONSTRAINT `FK_363A759A76A45273` FOREIGN KEY (paca_id) REFERENCES paca (id) ON UPDATE NO ACTION ON DELETE CASCADE');
        $this->addSql('ALTER TABLE inventory_reservation ADD CONSTRAINT `FK_363A759AA76ED395` FOREIGN KEY (user_id) REFERENCES app_user (id) ON UPDATE NO ACTION');
        $this->addSql('ALTER TABLE paca_location ADD CONSTRAINT `FK_67335D135080ECDE` FOREIGN KEY (warehouse_id) REFERENCES warehouse (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE paca_location ADD CONSTRAINT `FK_67335D1376A45273` FOREIGN KEY (paca_id) REFERENCES paca (id) ON UPDATE NO ACTION ON DELETE CASCADE');
        $this->addSql('ALTER TABLE paca_location ADD CONSTRAINT `FK_67335D13C77159AD` FOREIGN KEY (warehouse_bin_id) REFERENCES warehouse_bin (id) ON UPDATE NO ACTION ON DELETE SET NULL');
        $this->addSql('ALTER TABLE paca_unit DROP FOREIGN KEY FK_34A1768C76A45273');
        $this->addSql('ALTER TABLE paca_unit DROP FOREIGN KEY FK_34A1768C5080ECDE');
        $this->addSql('ALTER TABLE paca_unit DROP FOREIGN KEY FK_34A1768CC77159AD');
        $this->addSql('ALTER TABLE paca_unit DROP FOREIGN KEY FK_34A1768CC023F51C');
        $this->addSql('ALTER TABLE paca_unit DROP FOREIGN KEY FK_34A1768C21338DA6');
        $this->addSql('DROP TABLE paca_unit');
        $this->addSql('ALTER TABLE inventory_movement DROP FOREIGN KEY FK_40972F6656DA6AEE');
        $this->addSql('DROP INDEX idx_movement_paca_unit ON inventory_movement');
        $this->addSql('ALTER TABLE inventory_movement DROP paca_unit_id');
        $this->addSql('ALTER TABLE paca CHANGE cached_stock stock INT NOT NULL');
    }
}
