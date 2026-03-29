<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260328224803 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE customer (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, email VARCHAR(255) DEFAULT NULL, phone VARCHAR(20) DEFAULT NULL, address LONGTEXT DEFAULT NULL, tax_id VARCHAR(50) DEFAULT NULL, active TINYINT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX idx_customer_name (name), INDEX idx_customer_email (email), INDEX idx_customer_phone (phone), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE payment (id INT AUTO_INCREMENT NOT NULL, amount NUMERIC(10, 2) NOT NULL, method VARCHAR(20) NOT NULL, status VARCHAR(20) NOT NULL, transaction_id VARCHAR(100) DEFAULT NULL, notes LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, sale_id INT NOT NULL, INDEX IDX_6D28840D4A7E4868 (sale_id), INDEX idx_payment_status (status), INDEX idx_payment_method (method), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE sale (id INT AUTO_INCREMENT NOT NULL, subtotal NUMERIC(10, 2) NOT NULL, tax NUMERIC(10, 2) NOT NULL, discount NUMERIC(10, 2) NOT NULL, total NUMERIC(10, 2) NOT NULL, status VARCHAR(20) NOT NULL, payment_method VARCHAR(20) NOT NULL, notes LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, customer_id INT DEFAULT NULL, INDEX IDX_E54BC0059395C3F3 (customer_id), INDEX idx_sale_status (status), INDEX idx_sale_payment_method (payment_method), INDEX idx_sale_created_at (created_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE sale_item (id INT AUTO_INCREMENT NOT NULL, quantity INT NOT NULL, unit_price NUMERIC(10, 2) NOT NULL, total_price NUMERIC(10, 2) NOT NULL, tax_amount NUMERIC(10, 2) NOT NULL, discount_amount NUMERIC(10, 2) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, sale_id INT NOT NULL, product_id INT NOT NULL, INDEX IDX_A35551FB4A7E4868 (sale_id), INDEX IDX_A35551FB4584665A (product_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE payment ADD CONSTRAINT FK_6D28840D4A7E4868 FOREIGN KEY (sale_id) REFERENCES sale (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE sale ADD CONSTRAINT FK_E54BC0059395C3F3 FOREIGN KEY (customer_id) REFERENCES customer (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE sale_item ADD CONSTRAINT FK_A35551FB4A7E4868 FOREIGN KEY (sale_id) REFERENCES sale (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE sale_item ADD CONSTRAINT FK_A35551FB4584665A FOREIGN KEY (product_id) REFERENCES product (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE product ADD barcode VARCHAR(50) DEFAULT NULL');
        $this->addSql('ALTER TABLE user_session ADD last_activity_at DATETIME NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE payment DROP FOREIGN KEY FK_6D28840D4A7E4868');
        $this->addSql('ALTER TABLE sale DROP FOREIGN KEY FK_E54BC0059395C3F3');
        $this->addSql('ALTER TABLE sale_item DROP FOREIGN KEY FK_A35551FB4A7E4868');
        $this->addSql('ALTER TABLE sale_item DROP FOREIGN KEY FK_A35551FB4584665A');
        $this->addSql('DROP TABLE customer');
        $this->addSql('DROP TABLE payment');
        $this->addSql('DROP TABLE sale');
        $this->addSql('DROP TABLE sale_item');
        $this->addSql('ALTER TABLE product DROP barcode');
        $this->addSql('ALTER TABLE user_session DROP last_activity_at');
    }
}
