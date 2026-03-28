<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260328203554 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE action_catalog (id INT AUTO_INCREMENT NOT NULL, code VARCHAR(50) NOT NULL, name VARCHAR(100) NOT NULL, UNIQUE INDEX UNIQ_C18EBAA677153098 (code), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE app_module (id INT AUTO_INCREMENT NOT NULL, code VARCHAR(50) NOT NULL, name VARCHAR(100) NOT NULL, icon VARCHAR(50) NOT NULL, display_order INT NOT NULL, active TINYINT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_E9274BA877153098 (code), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE role (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(100) NOT NULL, description VARCHAR(255) DEFAULT NULL, active TINYINT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_57698A6A5E237E06 (name), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE role_action_permission (id INT AUTO_INCREMENT NOT NULL, allowed TINYINT NOT NULL, role_id INT NOT NULL, app_module_id INT NOT NULL, action_id INT NOT NULL, INDEX IDX_C3023F9FD60322AC (role_id), INDEX IDX_C3023F9F7ADEAA4 (app_module_id), INDEX IDX_C3023F9F9D32F035 (action_id), UNIQUE INDEX unique_role_module_action (role_id, app_module_id, action_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE role_module_permission (id INT AUTO_INCREMENT NOT NULL, can_access TINYINT NOT NULL, role_id INT NOT NULL, app_module_id INT NOT NULL, INDEX IDX_35457EC8D60322AC (role_id), INDEX IDX_35457EC87ADEAA4 (app_module_id), UNIQUE INDEX unique_role_module (role_id, app_module_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE user_session (id INT AUTO_INCREMENT NOT NULL, token VARCHAR(500) NOT NULL, ip_address VARCHAR(45) DEFAULT NULL, user_agent VARCHAR(500) DEFAULT NULL, login_at DATETIME NOT NULL, logout_at DATETIME DEFAULT NULL, active TINYINT NOT NULL, user_id INT NOT NULL, INDEX IDX_8849CBDEA76ED395 (user_id), INDEX idx_session_active (active), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE role_action_permission ADD CONSTRAINT FK_C3023F9FD60322AC FOREIGN KEY (role_id) REFERENCES role (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE role_action_permission ADD CONSTRAINT FK_C3023F9F7ADEAA4 FOREIGN KEY (app_module_id) REFERENCES app_module (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE role_action_permission ADD CONSTRAINT FK_C3023F9F9D32F035 FOREIGN KEY (action_id) REFERENCES action_catalog (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE role_module_permission ADD CONSTRAINT FK_35457EC8D60322AC FOREIGN KEY (role_id) REFERENCES role (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE role_module_permission ADD CONSTRAINT FK_35457EC87ADEAA4 FOREIGN KEY (app_module_id) REFERENCES app_module (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_session ADD CONSTRAINT FK_8849CBDEA76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user ADD active TINYINT NOT NULL DEFAULT 1, ADD role_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE user ADD CONSTRAINT FK_8D93D649D60322AC FOREIGN KEY (role_id) REFERENCES role (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_8D93D649D60322AC ON user (role_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE role_action_permission DROP FOREIGN KEY FK_C3023F9FD60322AC');
        $this->addSql('ALTER TABLE role_action_permission DROP FOREIGN KEY FK_C3023F9F7ADEAA4');
        $this->addSql('ALTER TABLE role_action_permission DROP FOREIGN KEY FK_C3023F9F9D32F035');
        $this->addSql('ALTER TABLE role_module_permission DROP FOREIGN KEY FK_35457EC8D60322AC');
        $this->addSql('ALTER TABLE role_module_permission DROP FOREIGN KEY FK_35457EC87ADEAA4');
        $this->addSql('ALTER TABLE user_session DROP FOREIGN KEY FK_8849CBDEA76ED395');
        $this->addSql('DROP TABLE action_catalog');
        $this->addSql('DROP TABLE app_module');
        $this->addSql('DROP TABLE role');
        $this->addSql('DROP TABLE role_action_permission');
        $this->addSql('DROP TABLE role_module_permission');
        $this->addSql('DROP TABLE user_session');
        $this->addSql('ALTER TABLE `user` DROP FOREIGN KEY FK_8D93D649D60322AC');
        $this->addSql('DROP INDEX IDX_8D93D649D60322AC ON `user`');
        $this->addSql('ALTER TABLE `user` DROP active, DROP role_id');
    }
}
