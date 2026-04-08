<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260408025510 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE paca_unit ADD purchase_order_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE paca_unit ADD CONSTRAINT FK_34A1768CA45D7E6A FOREIGN KEY (purchase_order_id) REFERENCES purchase_order (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_34A1768CA45D7E6A ON paca_unit (purchase_order_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE paca_unit DROP FOREIGN KEY FK_34A1768CA45D7E6A');
        $this->addSql('DROP INDEX IDX_34A1768CA45D7E6A ON paca_unit');
        $this->addSql('ALTER TABLE paca_unit DROP purchase_order_id');
    }
}
