<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260328233034 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // Clear existing action permissions (they referenced app_module, no longer valid)
        $this->addSql('DELETE FROM role_action_permission');

        $this->addSql('CREATE TABLE app_function (id INT AUTO_INCREMENT NOT NULL, code VARCHAR(50) NOT NULL, name VARCHAR(100) NOT NULL, display_order INT NOT NULL, active TINYINT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, app_module_id INT NOT NULL, UNIQUE INDEX UNIQ_C22E769077153098 (code), INDEX IDX_C22E76907ADEAA4 (app_module_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE app_function ADD CONSTRAINT FK_C22E76907ADEAA4 FOREIGN KEY (app_module_id) REFERENCES app_module (id) ON DELETE CASCADE');

        $this->addSql('ALTER TABLE role_action_permission DROP FOREIGN KEY `FK_C3023F9F7ADEAA4`');
        $this->addSql('DROP INDEX IDX_C3023F9F7ADEAA4 ON role_action_permission');
        $this->addSql('DROP INDEX unique_role_module_action ON role_action_permission');
        $this->addSql('ALTER TABLE role_action_permission CHANGE app_module_id app_function_id INT NOT NULL');
        $this->addSql('ALTER TABLE role_action_permission ADD CONSTRAINT FK_C3023F9F3EE1AEBD FOREIGN KEY (app_function_id) REFERENCES app_function (id) ON DELETE CASCADE');
        $this->addSql('CREATE INDEX IDX_C3023F9F3EE1AEBD ON role_action_permission (app_function_id)');
        $this->addSql('CREATE UNIQUE INDEX unique_role_function_action ON role_action_permission (role_id, app_function_id, action_id)');

        // Seed functions
        $now = date('Y-m-d H:i:s');
        $fns = [
            ['catalogos','companies','Empresas',1], ['catalogos','branches','Sucursales',2], ['catalogos','departments','Departamentos',3],
            ['seguridad','roles','Roles',1], ['seguridad','permissions','Permisos',2], ['seguridad','users','Usuarios',3], ['seguridad','sessions','Sesiones',4],
            ['proveedor','suppliers','Proveedores',1], ['proveedor','brands','Marcas',2],
            ['productos','pacas','Pacas',1], ['productos','product_catalogs','Catálogos de producto',2],
            ['ventas','pos','Punto de venta',1], ['ventas','sales','Historial de ventas',2], ['ventas','customers','Clientes',3],
            ['reportes','dashboard','Dashboard',1], ['reportes','daily_reports','Reportes diarios',2], ['reportes','monthly_reports','Reportes mensuales',3],
        ];
        foreach ($fns as [$mc, $code, $name, $ord]) {
            $this->addSql("INSERT INTO app_function (app_module_id, code, name, display_order, active, created_at, updated_at) SELECT id, '{$code}', '{$name}', {$ord}, 1, '{$now}', '{$now}' FROM app_module WHERE code = '{$mc}'");
        }
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE app_function DROP FOREIGN KEY FK_C22E76907ADEAA4');
        $this->addSql('DROP TABLE app_function');
        $this->addSql('ALTER TABLE role_action_permission DROP FOREIGN KEY FK_C3023F9F3EE1AEBD');
        $this->addSql('DROP INDEX IDX_C3023F9F3EE1AEBD ON role_action_permission');
        $this->addSql('DROP INDEX unique_role_function_action ON role_action_permission');
        $this->addSql('ALTER TABLE role_action_permission CHANGE app_function_id app_module_id INT NOT NULL');
        $this->addSql('ALTER TABLE role_action_permission ADD CONSTRAINT `FK_C3023F9F7ADEAA4` FOREIGN KEY (app_module_id) REFERENCES app_module (id) ON UPDATE NO ACTION ON DELETE CASCADE');
        $this->addSql('CREATE INDEX IDX_C3023F9F7ADEAA4 ON role_action_permission (app_module_id)');
        $this->addSql('CREATE UNIQUE INDEX unique_role_module_action ON role_action_permission (role_id, app_module_id, action_id)');
    }
}
