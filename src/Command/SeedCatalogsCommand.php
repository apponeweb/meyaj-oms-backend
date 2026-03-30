<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Branch;
use App\Entity\Brand;
use App\Entity\Category;
use App\Entity\Company;
use App\Entity\Department;
use App\Entity\FabricType;
use App\Entity\GarmentType;
use App\Entity\GenderCatalog;
use App\Entity\LabelCatalog;
use App\Entity\QualityGrade;
use App\Entity\SeasonCatalog;
use App\Entity\SizeProfile;
use App\Entity\Supplier;
use App\Entity\Product;
use App\Entity\Paca;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:seed-catalogs',
    description: 'Limpia y resetea las tablas de catálogos y productos con 5 registros temáticos',
)]
class SeedCatalogsCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Limpiando y Sembrando Datos Temáticos (5 registros por tabla)');

        try {
            // TRUNCATE de tablas dependientes para evitar errores de FK
            $io->note('Limpiando base de datos...');
            $connection = $this->entityManager->getConnection();
            $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 0');
            
            $tables = [
                'paca', 'product', 'sale_item', 'sale', 'payment',
                'department', 'branch', 'company', 'supplier', 'brand', 'category',
                'fabric_type', 'garment_type', 'gender_catalog', 'label_catalog', 'quality_grade', 'season_catalog', 'size_profile'
            ];

            foreach ($tables as $table) {
                $connection->executeStatement("TRUNCATE TABLE $table");
            }
            $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 1');
            $io->success('Tablas truncadas exitosamente.');

            // 1. Companies
            $io->section('Empresas');
            $companiesData = ['Distribuidora Textil', 'Modas del Norte', 'Logística Pacas MX', 'Importaciones Premium', 'Almacenes El Éxito'];
            $companies = [];
            foreach ($companiesData as $idx => $name) {
                $company = new Company();
                $company->setName($name);
                $company->setTaxId("TX-$idx-" . rand(100, 999));
                $company->setActive(true);
                $this->entityManager->persist($company);
                $companies[] = $company;
            }
            $this->entityManager->flush();

            // 2. Branches
            $io->section('Sucursales');
            $branchesData = ['Matriz Central', 'Sucursal Poniente', 'Punto de Venta Norte', 'Bodega Logística', 'Showroom Principal'];
            $branches = [];
            foreach ($branchesData as $idx => $name) {
                $branch = new Branch();
                $branch->setName($name);
                $branch->setCompany($companies[array_rand($companies)]);
                $branch->setActive(true);
                $this->entityManager->persist($branch);
                $branches[] = $branch;
            }
            $this->entityManager->flush();

            // 3. Departments
            $io->section('Departamentos');
            $deptsData = ['Ventas', 'Almacén', 'Atención al Cliente', 'Administración', 'Logística'];
            foreach ($deptsData as $name) {
                $dept = new Department();
                $dept->setName($name);
                $dept->setBranch($branches[array_rand($branches)]);
                $dept->setActive(true);
                $this->entityManager->persist($dept);
            }

            // 4. Categories & Brands
            $io->section('Categorías y Marcas');
            $catsData = ['Ropa de Invierno', 'Ropa Deportiva', 'Accesorios', 'Calzado', 'Ropa de Verano'];
            $brandsData = ['Nike', 'Adidas', 'Levis', 'Zara', 'Gucci'];
            $categoriesList = [];
            $brandsList = [];
            for ($i = 0; $i < 5; $i++) {
                $cat = new Category();
                $cat->setName($catsData[$i]);
                $this->entityManager->persist($cat);
                $categoriesList[] = $cat;

                $brand = new Brand();
                $brand->setName($brandsData[$i]);
                $brand->setActive(true);
                $this->entityManager->persist($brand);
                $brandsList[] = $brand;
            }
            $this->entityManager->flush();

            // 5. Product Catalogs
            $io->section('Catálogos de Producto');
            $catalogs = [
                FabricType::class => ['Algodón', 'Poliéster', 'Mezclilla', 'Lana', 'Seda'],
                GarmentType::class => ['Camiseta', 'Pantalón', 'Chaqueta', 'Vestido', 'Sudadera'],
                GenderCatalog::class => ['Caballero', 'Dama', 'Niño', 'Niña', 'Unisex'],
                LabelCatalog::class => ['Sin Etiqueta', 'Etiqueta Original', 'Etiqueta Dañada', 'Re-etiquetado', 'Premium'],
                QualityGrade::class => ['Nueva', 'Grade A', 'Grade B', 'Liquidación', 'Vintage'],
                SeasonCatalog::class => ['Primavera', 'Verano', 'Otoño', 'Invierno', 'Toda Estación'],
                SizeProfile::class => ['XS - S', 'M - L', 'XL - XXL', 'Talla Única', 'Juvenil'],
            ];

            $catalogInstances = [];
            foreach ($catalogs as $className => $values) {
                $catalogInstances[$className] = [];
                foreach ($values as $val) {
                    $entity = new $className();
                    $entity->setName($val);
                    if (method_exists($entity, 'setActive')) $entity->setActive(true);
                    $this->entityManager->persist($entity);
                    $catalogInstances[$className][] = $entity;
                }
            }
            $this->entityManager->flush();

            // 6. Suppliers
            $io->section('Proveedores');
            $suppliersData = ['Textiles de América', 'Import Paca Global', 'Suministros Moda SL', 'Global Wear Co.', 'EuroTrade Fashion'];
            $suppliersList = [];
            foreach ($suppliersData as $name) {
                $supplier = new Supplier();
                $supplier->setName($name);
                $supplier->setActive(true);
                $this->entityManager->persist($supplier);
                $suppliersList[] = $supplier;
            }
            $this->entityManager->flush();

            // 7. Products (Basic)
            $io->section('Productos');
            $productsData = ['Camisa Formal Premium', 'Jeans Ajustado', 'Chaqueta Impermeable', 'Tenis Deportivos', 'Sudadera con Gorro'];
            foreach ($productsData as $idx => $name) {
                $product = new Product();
                $product->setName($name);
                $product->setPrice((string)(250 + ($idx * 150)));
                $product->setStock(20);
                $product->setCategory($categoriesList[$idx]);
                $product->setActive(true);
                $this->entityManager->persist($product);
            }

            // 8. Pacas
            $io->section('Pacas');
            $pacasData = ['Pacas de Ropa Mixta', 'Pacas de Abrigos Premium', 'Pacas de Camisetas Algodón', 'Pacas de Jean de Marca', 'Pacas de Calzado Mixto'];
            foreach ($pacasData as $idx => $name) {
                $paca = new Paca();
                $paca->setCode("PACA-00" . ($idx + 1));
                $paca->setName($name);
                $paca->setBrand($brandsList[$idx]);
                $paca->setLabel($catalogInstances[LabelCatalog::class][$idx]);
                $paca->setQualityGrade($catalogInstances[QualityGrade::class][$idx]);
                $paca->setSeason($catalogInstances[SeasonCatalog::class][$idx]);
                $paca->setGender($catalogInstances[GenderCatalog::class][$idx]);
                $paca->setGarmentType($catalogInstances[GarmentType::class][$idx]);
                $paca->setFabricType($catalogInstances[FabricType::class][$idx]);
                $paca->setSizeProfile($catalogInstances[SizeProfile::class][$idx]);
                $paca->setSupplier($suppliersList[$idx]);
                $paca->setPurchasePrice((string)(1500 + ($idx * 100)));
                $paca->setSellingPrice((string)(3000 + ($idx * 200)));
                $paca->setStock(5);
                $paca->setActive(true);
                $this->entityManager->persist($paca);
            }

            $this->entityManager->flush();
            $io->success('Base de datos reseteada con los 5 registros temáticos por tabla.');
            
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Error: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
