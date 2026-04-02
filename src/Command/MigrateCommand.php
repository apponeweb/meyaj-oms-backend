<?php

declare(strict_types=1);

namespace App\Command;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:migrate',
    description: 'Run safe migration with data migration, schema update, seed modules, and admin permissions',
)]
class MigrateCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $conn = $this->em->getConnection();

        // ── Step 1: Data migrations (before schema update) ──
        $io->section('1/4 - Running data migrations...');

        $this->migrateWarehouseType($conn, $io);
        $this->migratePacaLocations($conn, $io);

        // ── Step 2: Schema update (safe now that data is migrated) ──
        $io->section('2/4 - Updating database schema...');
        $schemaCmd = $this->getApplication()->find('doctrine:schema:update');
        $schemaCmd->run(new ArrayInput(['--force' => true]), $output);

        // ── Step 3: Seed modules ──
        $io->section('3/4 - Seeding modules, functions, and actions...');
        $seedCmd = $this->getApplication()->find('app:seed-modules');
        $seedCmd->run(new ArrayInput([]), $output);

        // ── Step 4: Admin permissions ──
        $io->section('4/4 - Assigning admin permissions...');
        $permCmd = $this->getApplication()->find('app:admin-permissions');
        $permCmd->run(new ArrayInput([]), $output);

        $io->success('Migration complete.');

        return Command::SUCCESS;
    }

    private function migrateWarehouseType(Connection $conn, SymfonyStyle $io): void
    {
        // Check if warehouse_type table already exists
        $tables = $conn->createSchemaManager()->listTableNames();

        if (!in_array('warehouse_type', $tables, true)) {
            $io->info('Creating warehouse_type table...');
            $conn->executeStatement('
                CREATE TABLE warehouse_type (
                    id INT AUTO_INCREMENT NOT NULL,
                    name VARCHAR(100) NOT NULL,
                    description VARCHAR(255) DEFAULT NULL,
                    is_active TINYINT(1) NOT NULL DEFAULT 1,
                    created_at DATETIME NOT NULL,
                    updated_at DATETIME NOT NULL,
                    UNIQUE INDEX UNIQ_warehouse_type_name (name),
                    PRIMARY KEY(id)
                ) DEFAULT CHARACTER SET utf8mb4
            ');
        }

        // Seed default types
        $count = (int) $conn->fetchOne('SELECT COUNT(*) FROM warehouse_type');
        if ($count === 0) {
            $io->info('Seeding warehouse types...');
            $conn->executeStatement("
                INSERT INTO warehouse_type (name, description, is_active, created_at, updated_at) VALUES
                ('Propio',   'Bodega propia de la empresa',              1, NOW(), NOW()),
                ('Externo',  'Bodega externa o de terceros',              1, NOW(), NOW()),
                ('Temporal', 'Bodega temporal para almacenamiento corto', 1, NOW(), NOW())
            ");
        }

        // Check if warehouse still has old string column
        $columns = array_map(
            fn($col) => $col->getName(),
            $conn->createSchemaManager()->listTableColumns('warehouse')
        );

        if (in_array('warehouse_type', $columns, true)) {
            $io->info('Migrating warehouse.warehouse_type string → FK...');

            // Add new column if not exists
            if (!in_array('warehouse_type_id', $columns, true)) {
                $conn->executeStatement('ALTER TABLE warehouse ADD warehouse_type_id INT DEFAULT NULL');
            }

            // Map old values
            $defaultId = (int) $conn->fetchOne("SELECT id FROM warehouse_type WHERE name = 'Propio' LIMIT 1");
            $conn->executeStatement("
                UPDATE warehouse w SET w.warehouse_type_id = COALESCE(
                    (SELECT wt.id FROM warehouse_type wt WHERE wt.name = CASE w.warehouse_type
                        WHEN 'PROPIO' THEN 'Propio' WHEN 'EXTERNO' THEN 'Externo' WHEN 'TEMPORAL' THEN 'Temporal'
                        ELSE w.warehouse_type END LIMIT 1),
                    {$defaultId}
                ) WHERE w.warehouse_type_id IS NULL OR w.warehouse_type_id = 0
            ");

            // Make NOT NULL
            $conn->executeStatement('ALTER TABLE warehouse MODIFY warehouse_type_id INT NOT NULL');

            // Drop old column
            $conn->executeStatement('ALTER TABLE warehouse DROP COLUMN warehouse_type');
            $io->info('Done. Old warehouse_type column removed.');
        } elseif (in_array('warehouse_type_id', $columns, true)) {
            // Column exists but may have 0 values (broken state)
            $zeros = (int) $conn->fetchOne('SELECT COUNT(*) FROM warehouse WHERE warehouse_type_id = 0');
            if ($zeros > 0) {
                $io->info("Fixing {$zeros} warehouses with warehouse_type_id=0...");
                $defaultId = (int) $conn->fetchOne("SELECT id FROM warehouse_type WHERE name = 'Propio' LIMIT 1");
                $conn->executeStatement("UPDATE warehouse SET warehouse_type_id = {$defaultId} WHERE warehouse_type_id = 0");
            }
        } else {
            $io->note('warehouse_type already migrated, skipping.');
        }
    }

    private function migratePacaLocations(Connection $conn, SymfonyStyle $io): void
    {
        $tables = $conn->createSchemaManager()->listTableNames();

        if (!in_array('paca_location', $tables, true)) {
            $io->info('Creating paca_location table...');
            $conn->executeStatement('
                CREATE TABLE paca_location (
                    id INT AUTO_INCREMENT NOT NULL,
                    paca_id INT NOT NULL,
                    warehouse_id INT NOT NULL,
                    warehouse_bin_id INT DEFAULT NULL,
                    created_at DATETIME NOT NULL,
                    INDEX IDX_paca_location_paca (paca_id),
                    INDEX IDX_paca_location_warehouse (warehouse_id),
                    INDEX IDX_paca_location_bin (warehouse_bin_id),
                    PRIMARY KEY(id)
                ) DEFAULT CHARACTER SET utf8mb4
            ');
            $conn->executeStatement('ALTER TABLE paca_location ADD CONSTRAINT FK_pl_paca FOREIGN KEY (paca_id) REFERENCES paca (id) ON DELETE CASCADE');
            $conn->executeStatement('ALTER TABLE paca_location ADD CONSTRAINT FK_pl_warehouse FOREIGN KEY (warehouse_id) REFERENCES warehouse (id)');
            $conn->executeStatement('ALTER TABLE paca_location ADD CONSTRAINT FK_pl_bin FOREIGN KEY (warehouse_bin_id) REFERENCES warehouse_bin (id) ON DELETE SET NULL');
        }

        // Check if paca still has old columns
        $columns = array_map(
            fn($col) => $col->getName(),
            $conn->createSchemaManager()->listTableColumns('paca')
        );

        if (in_array('warehouse_id', $columns, true)) {
            $io->info('Migrating paca warehouse/bin → paca_location...');

            // Migrate existing data
            $migrated = $conn->executeStatement('
                INSERT INTO paca_location (paca_id, warehouse_id, warehouse_bin_id, created_at)
                SELECT p.id, p.warehouse_id, p.warehouse_bin_id, NOW()
                FROM paca p WHERE p.warehouse_id IS NOT NULL
                  AND NOT EXISTS (SELECT 1 FROM paca_location pl WHERE pl.paca_id = p.id AND pl.warehouse_id = p.warehouse_id)
            ');
            $io->info("Migrated {$migrated} paca locations.");

            // Drop old FKs and columns
            $this->safeDropForeignKey($conn, 'paca', 'FK_770BB73D5080ECDE');
            $this->safeDropForeignKey($conn, 'paca', 'FK_770BB73DC77159AD');
            $this->safeDropIndex($conn, 'paca', 'IDX_770BB73D5080ECDE');
            $this->safeDropIndex($conn, 'paca', 'IDX_770BB73DC77159AD');
            $conn->executeStatement('ALTER TABLE paca DROP COLUMN warehouse_id');
            if (in_array('warehouse_bin_id', $columns, true)) {
                $conn->executeStatement('ALTER TABLE paca DROP COLUMN warehouse_bin_id');
            }
            $io->info('Done. Old paca warehouse columns removed.');
        } else {
            $io->note('paca_location already migrated, skipping.');
        }
    }

    private function safeDropForeignKey(Connection $conn, string $table, string $fk): void
    {
        try {
            $conn->executeStatement("ALTER TABLE {$table} DROP FOREIGN KEY {$fk}");
        } catch (\Exception) {
            // FK doesn't exist, ignore
        }
    }

    private function safeDropIndex(Connection $conn, string $table, string $index): void
    {
        try {
            $conn->executeStatement("DROP INDEX {$index} ON {$table}");
        } catch (\Exception) {
            // Index doesn't exist, ignore
        }
    }
}
