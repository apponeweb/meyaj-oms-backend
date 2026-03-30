<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260330231000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add acronym column to product catalog tables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE category ADD acronym VARCHAR(10) DEFAULT NULL');
        $this->addSql('ALTER TABLE fabric_type ADD acronym VARCHAR(10) DEFAULT NULL');
        $this->addSql('ALTER TABLE garment_type ADD acronym VARCHAR(10) DEFAULT NULL');
        $this->addSql('ALTER TABLE gender_catalog ADD acronym VARCHAR(10) DEFAULT NULL');
        $this->addSql('ALTER TABLE label_catalog ADD acronym VARCHAR(10) DEFAULT NULL');
        $this->addSql('ALTER TABLE quality_grade ADD acronym VARCHAR(10) DEFAULT NULL');
        $this->addSql('ALTER TABLE season_catalog ADD acronym VARCHAR(10) DEFAULT NULL');
        $this->addSql('ALTER TABLE size_profile ADD acronym VARCHAR(10) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE category DROP acronym');
        $this->addSql('ALTER TABLE fabric_type DROP acronym');
        $this->addSql('ALTER TABLE garment_type DROP acronym');
        $this->addSql('ALTER TABLE gender_catalog DROP acronym');
        $this->addSql('ALTER TABLE label_catalog DROP acronym');
        $this->addSql('ALTER TABLE quality_grade DROP acronym');
        $this->addSql('ALTER TABLE season_catalog DROP acronym');
        $this->addSql('ALTER TABLE size_profile DROP acronym');
    }
}
