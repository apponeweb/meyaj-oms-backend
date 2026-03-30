<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260330050000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Change department relation from branch to company, add acronym field';
    }

    public function up(Schema $schema): void
    {
        // Drop old foreign key and unique constraint
        $this->addSql('ALTER TABLE department DROP FOREIGN KEY FK_CD1DE18ADCD6CC49');
        $this->addSql('DROP INDEX uniq_department_branch_name ON department');

        // Add company_id column as nullable first
        $this->addSql('ALTER TABLE department ADD company_id INT DEFAULT NULL');

        // Populate company_id from the branch's company
        $this->addSql('UPDATE department d INNER JOIN branch b ON d.branch_id = b.id SET d.company_id = b.company_id');

        // Now make it NOT NULL and drop the old branch_id column
        $this->addSql('ALTER TABLE department MODIFY company_id INT NOT NULL');
        $this->addSql('ALTER TABLE department DROP COLUMN branch_id');

        // Add acronym field
        $this->addSql('ALTER TABLE department ADD acronym VARCHAR(20) DEFAULT NULL AFTER name');

        // Add new foreign key and unique constraint
        $this->addSql('ALTER TABLE department ADD CONSTRAINT FK_CD1DE18A979B1AD6 FOREIGN KEY (company_id) REFERENCES company (id) ON DELETE CASCADE');
        $this->addSql('CREATE UNIQUE INDEX uniq_department_company_name ON department (company_id, name)');
        $this->addSql('CREATE INDEX IDX_CD1DE18A979B1AD6 ON department (company_id)');
    }

    public function down(Schema $schema): void
    {
        // Drop new foreign key and unique constraint
        $this->addSql('ALTER TABLE department DROP FOREIGN KEY FK_CD1DE18A979B1AD6');
        $this->addSql('DROP INDEX uniq_department_company_name ON department');
        $this->addSql('DROP INDEX IDX_CD1DE18A979B1AD6 ON department');

        // Remove acronym field
        $this->addSql('ALTER TABLE department DROP COLUMN acronym');

        // Add back branch_id column
        $this->addSql('ALTER TABLE department ADD branch_id INT NOT NULL');

        // Restore foreign key and unique constraint
        $this->addSql('ALTER TABLE department DROP COLUMN company_id');
        $this->addSql('ALTER TABLE department ADD CONSTRAINT FK_CD1DE18ADBE6A7B6 FOREIGN KEY (branch_id) REFERENCES branch (id) ON DELETE CASCADE');
        $this->addSql('CREATE UNIQUE INDEX uniq_department_branch_name ON department (branch_id, name)');
    }
}
