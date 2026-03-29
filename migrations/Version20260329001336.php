<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260329001336 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add table comments describing the purpose of each security module table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE app_user COMMENT = 'Usuarios del sistema con credenciales de acceso, rol asignado y estado activo/inactivo'");
        $this->addSql("ALTER TABLE app_user_session COMMENT = 'Registro de sesiones de usuario, rastreando login, logout, actividad y expiración por inactividad'");
        $this->addSql("ALTER TABLE app_role COMMENT = 'Roles del sistema que agrupan permisos de acceso a módulos y acciones sobre funcionalidades'");
        $this->addSql("ALTER TABLE app_role_module_permission COMMENT = 'Permisos de acceso de un rol a un módulo del sistema'");
        $this->addSql("ALTER TABLE app_role_action_permission COMMENT = 'Permisos granulares que definen qué acciones (crear, leer, actualizar, eliminar, exportar) puede realizar un rol sobre cada funcionalidad'");
        $this->addSql("ALTER TABLE app_module COMMENT = 'Módulos principales del sistema (Catálogos, Seguridad, Ventas, etc.) que agrupan funcionalidades'");
        $this->addSql("ALTER TABLE app_functionality COMMENT = 'Funcionalidades específicas dentro de un módulo sobre las cuales se asignan permisos de acción por rol'");
        $this->addSql("ALTER TABLE app_action_catalog COMMENT = 'Catálogo de acciones disponibles en el sistema (crear, leer, actualizar, eliminar, exportar)'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE app_user COMMENT = ''");
        $this->addSql("ALTER TABLE app_user_session COMMENT = ''");
        $this->addSql("ALTER TABLE app_role COMMENT = ''");
        $this->addSql("ALTER TABLE app_role_module_permission COMMENT = ''");
        $this->addSql("ALTER TABLE app_role_action_permission COMMENT = ''");
        $this->addSql("ALTER TABLE app_module COMMENT = ''");
        $this->addSql("ALTER TABLE app_functionality COMMENT = ''");
        $this->addSql("ALTER TABLE app_action_catalog COMMENT = ''");
    }
}
