<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260407212413 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE shipment_order (id INT AUTO_INCREMENT NOT NULL, folio VARCHAR(30) NOT NULL, tracking_number VARCHAR(100) DEFAULT NULL, status VARCHAR(20) NOT NULL, carrier VARCHAR(100) DEFAULT NULL, notes LONGTEXT DEFAULT NULL, shipped_at DATETIME DEFAULT NULL, delivered_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, sales_order_id INT NOT NULL, warehouse_id INT NOT NULL, created_by_id INT NOT NULL, UNIQUE INDEX UNIQ_79E4313D9BEA0CC6 (folio), INDEX IDX_79E4313DB03A8386 (created_by_id), INDEX idx_sho_sales_order (sales_order_id), INDEX idx_sho_status (status), INDEX idx_sho_folio (folio), INDEX idx_sho_warehouse (warehouse_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE shipment_order_item (id INT AUTO_INCREMENT NOT NULL, scanned_at DATETIME NOT NULL, shipment_order_id INT NOT NULL, paca_unit_id INT NOT NULL, scanned_by_id INT NOT NULL, INDEX IDX_30E0C35EBBC642F (scanned_by_id), INDEX idx_shoi_shipment_order (shipment_order_id), INDEX idx_shoi_paca_unit (paca_unit_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE shipment_order ADD CONSTRAINT FK_79E4313DC023F51C FOREIGN KEY (sales_order_id) REFERENCES sales_order (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE shipment_order ADD CONSTRAINT FK_79E4313D5080ECDE FOREIGN KEY (warehouse_id) REFERENCES warehouse (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE shipment_order ADD CONSTRAINT FK_79E4313DB03A8386 FOREIGN KEY (created_by_id) REFERENCES app_user (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE shipment_order_item ADD CONSTRAINT FK_30E0C352BC89259 FOREIGN KEY (shipment_order_id) REFERENCES shipment_order (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE shipment_order_item ADD CONSTRAINT FK_30E0C3556DA6AEE FOREIGN KEY (paca_unit_id) REFERENCES paca_unit (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE shipment_order_item ADD CONSTRAINT FK_30E0C35EBBC642F FOREIGN KEY (scanned_by_id) REFERENCES app_user (id) ON DELETE RESTRICT');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE shipment_order DROP FOREIGN KEY FK_79E4313DC023F51C');
        $this->addSql('ALTER TABLE shipment_order DROP FOREIGN KEY FK_79E4313D5080ECDE');
        $this->addSql('ALTER TABLE shipment_order DROP FOREIGN KEY FK_79E4313DB03A8386');
        $this->addSql('ALTER TABLE shipment_order_item DROP FOREIGN KEY FK_30E0C352BC89259');
        $this->addSql('ALTER TABLE shipment_order_item DROP FOREIGN KEY FK_30E0C3556DA6AEE');
        $this->addSql('ALTER TABLE shipment_order_item DROP FOREIGN KEY FK_30E0C35EBBC642F');
        $this->addSql('DROP TABLE shipment_order');
        $this->addSql('DROP TABLE shipment_order_item');
    }
}
