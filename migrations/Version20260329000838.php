<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260329000838 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rename security module tables to use app_ prefix and rename app_function to app_functionality';
    }

    public function up(Schema $schema): void
    {
        // Drop foreign keys on tables that will be renamed
        $this->addSql('ALTER TABLE role_action_permission DROP FOREIGN KEY `FK_C3023F9FD60322AC`');
        $this->addSql('ALTER TABLE role_action_permission DROP FOREIGN KEY `FK_C3023F9F3EE1AEBD`');
        $this->addSql('ALTER TABLE role_action_permission DROP FOREIGN KEY `FK_C3023F9F9D32F035`');
        $this->addSql('ALTER TABLE role_module_permission DROP FOREIGN KEY `FK_35457EC8D60322AC`');
        $this->addSql('ALTER TABLE role_module_permission DROP FOREIGN KEY `FK_35457EC87ADEAA4`');
        $this->addSql('ALTER TABLE app_function DROP FOREIGN KEY `FK_C22E76907ADEAA4`');
        $this->addSql('ALTER TABLE `user` DROP FOREIGN KEY `FK_8D93D649D60322AC`');
        $this->addSql('ALTER TABLE user_session DROP FOREIGN KEY `FK_8849CBDEA76ED395`');

        // Rename all tables
        $this->addSql('RENAME TABLE `user` TO app_user');
        $this->addSql('RENAME TABLE user_session TO app_user_session');
        $this->addSql('RENAME TABLE role TO app_role');
        $this->addSql('RENAME TABLE role_module_permission TO app_role_module_permission');
        $this->addSql('RENAME TABLE role_action_permission TO app_role_action_permission');
        $this->addSql('RENAME TABLE app_function TO app_functionality');
        $this->addSql('RENAME TABLE action_catalog TO app_action_catalog');

        // Re-create foreign keys with new table references
        $this->addSql('ALTER TABLE app_user ADD CONSTRAINT FK_88BDF3E9D60322AC FOREIGN KEY (role_id) REFERENCES app_role (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE app_user_session ADD CONSTRAINT FK_4AC7D15FA76ED395 FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE app_functionality ADD CONSTRAINT FK_624162E87ADEAA4 FOREIGN KEY (app_module_id) REFERENCES app_module (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE app_role_module_permission ADD CONSTRAINT FK_185FCD52D60322AC FOREIGN KEY (role_id) REFERENCES app_role (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE app_role_module_permission ADD CONSTRAINT FK_185FCD527ADEAA4 FOREIGN KEY (app_module_id) REFERENCES app_module (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE app_role_action_permission ADD CONSTRAINT FK_EE188C05D60322AC FOREIGN KEY (role_id) REFERENCES app_role (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE app_role_action_permission ADD CONSTRAINT FK_EE188C053EE1AEBD FOREIGN KEY (app_function_id) REFERENCES app_functionality (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE app_role_action_permission ADD CONSTRAINT FK_EE188C059D32F035 FOREIGN KEY (action_id) REFERENCES app_action_catalog (id) ON DELETE CASCADE');

        // Rename auto-generated indexes to match new table hashes
        $this->addSql('ALTER TABLE app_action_catalog RENAME INDEX uniq_c18ebaa677153098 TO UNIQ_1E74285877153098');
        $this->addSql('ALTER TABLE app_functionality RENAME INDEX uniq_c22e769077153098 TO UNIQ_624162E877153098');
        $this->addSql('ALTER TABLE app_functionality RENAME INDEX idx_c22e76907adeaa4 TO IDX_624162E87ADEAA4');
        $this->addSql('ALTER TABLE app_role RENAME INDEX uniq_57698a6a5e237e06 TO UNIQ_5247AFCA5E237E06');
        $this->addSql('ALTER TABLE app_role_action_permission RENAME INDEX idx_c3023f9fd60322ac TO IDX_EE188C05D60322AC');
        $this->addSql('ALTER TABLE app_role_action_permission RENAME INDEX idx_c3023f9f3ee1aebd TO IDX_EE188C053EE1AEBD');
        $this->addSql('ALTER TABLE app_role_action_permission RENAME INDEX idx_c3023f9f9d32f035 TO IDX_EE188C059D32F035');
        $this->addSql('ALTER TABLE app_role_module_permission RENAME INDEX idx_35457ec8d60322ac TO IDX_185FCD52D60322AC');
        $this->addSql('ALTER TABLE app_role_module_permission RENAME INDEX idx_35457ec87adeaa4 TO IDX_185FCD527ADEAA4');
        $this->addSql('ALTER TABLE app_user RENAME INDEX uniq_8d93d649e7927c74 TO UNIQ_88BDF3E9E7927C74');
        $this->addSql('ALTER TABLE app_user RENAME INDEX idx_8d93d649d60322ac TO IDX_88BDF3E9D60322AC');
        $this->addSql('ALTER TABLE app_user_session RENAME INDEX idx_8849cbdea76ed395 TO IDX_4AC7D15FA76ED395');
    }

    public function down(Schema $schema): void
    {
        // Drop foreign keys
        $this->addSql('ALTER TABLE app_role_action_permission DROP FOREIGN KEY FK_EE188C05D60322AC');
        $this->addSql('ALTER TABLE app_role_action_permission DROP FOREIGN KEY FK_EE188C053EE1AEBD');
        $this->addSql('ALTER TABLE app_role_action_permission DROP FOREIGN KEY FK_EE188C059D32F035');
        $this->addSql('ALTER TABLE app_role_module_permission DROP FOREIGN KEY FK_185FCD52D60322AC');
        $this->addSql('ALTER TABLE app_role_module_permission DROP FOREIGN KEY FK_185FCD527ADEAA4');
        $this->addSql('ALTER TABLE app_functionality DROP FOREIGN KEY FK_624162E87ADEAA4');
        $this->addSql('ALTER TABLE app_user DROP FOREIGN KEY FK_88BDF3E9D60322AC');
        $this->addSql('ALTER TABLE app_user_session DROP FOREIGN KEY FK_4AC7D15FA76ED395');

        // Rename tables back
        $this->addSql('RENAME TABLE app_user TO `user`');
        $this->addSql('RENAME TABLE app_user_session TO user_session');
        $this->addSql('RENAME TABLE app_role TO role');
        $this->addSql('RENAME TABLE app_role_module_permission TO role_module_permission');
        $this->addSql('RENAME TABLE app_role_action_permission TO role_action_permission');
        $this->addSql('RENAME TABLE app_functionality TO app_function');
        $this->addSql('RENAME TABLE app_action_catalog TO action_catalog');

        // Re-create original foreign keys
        $this->addSql('ALTER TABLE `user` ADD CONSTRAINT `FK_8D93D649D60322AC` FOREIGN KEY (role_id) REFERENCES role (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE user_session ADD CONSTRAINT `FK_8849CBDEA76ED395` FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE app_function ADD CONSTRAINT `FK_C22E76907ADEAA4` FOREIGN KEY (app_module_id) REFERENCES app_module (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE role_module_permission ADD CONSTRAINT `FK_35457EC8D60322AC` FOREIGN KEY (role_id) REFERENCES role (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE role_module_permission ADD CONSTRAINT `FK_35457EC87ADEAA4` FOREIGN KEY (app_module_id) REFERENCES app_module (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE role_action_permission ADD CONSTRAINT `FK_C3023F9FD60322AC` FOREIGN KEY (role_id) REFERENCES role (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE role_action_permission ADD CONSTRAINT `FK_C3023F9F3EE1AEBD` FOREIGN KEY (app_function_id) REFERENCES app_function (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE role_action_permission ADD CONSTRAINT `FK_C3023F9F9D32F035` FOREIGN KEY (action_id) REFERENCES action_catalog (id) ON DELETE CASCADE');
    }
}
