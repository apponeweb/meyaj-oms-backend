<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\User;
use App\Repository\RoleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:seed-users',
    description: 'Genera 1000 usuarios de prueba para el sistema',
)]
class SeedUsersCommand extends Command
{
    private const NAMES = ['Juan', 'Maria', 'Pedro', 'Lucia', 'Carlos', 'Ana', 'Luis', 'Sofia', 'Jorge', 'Elena', 'Diego', 'Paula', 'Andres', 'Laura', 'Roberto', 'Marta', 'Fernando', 'Carmen', 'Ricardo', 'Sara'];
    private const LASTNAMES = ['Garcia', 'Rodriguez', 'Gonzalez', 'Fernandez', 'Lopez', 'Martinez', 'Sanchez', 'Perez', 'Gomez', 'Martin', 'Jimenez', 'Ruiz', 'Hernandez', 'Diaz', 'Moreno', 'Muñoz', 'Alvarez', 'Romero', 'Alonso', 'Gutierrez'];

    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
        private RoleRepository $roleRepository
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Generando 1000 Usuarios de Prueba');

        // Buscar el rol de Administrador o el primero disponible
        $role = $this->roleRepository->findOneBy(['name' => 'Administrador']) ?? $this->roleRepository->findOneBy([]);

        if (!$role) {
            $io->error('No se encontró ningún Rol en la base de datos. Por favor, crea al menos un rol antes de ejecutar este comando.');
            return Command::FAILURE;
        }

        $io->note(sprintf('Utilizando el rol: %s', $role->getName()));

        $batchSize = 50;
        $password = 'password123';

        for ($i = 1; $i <= 1000; $i++) {
            $name = self::NAMES[array_rand(self::NAMES)];
            $lastName = self::LASTNAMES[array_rand(self::LASTNAMES)];
            
            $user = new User();
            $user->setName($name);
            $user->setLastName($lastName . ' ' . $i);
            $user->setEmail(strtolower(sprintf('user%d@example.com', $i)));
            $user->setPhone(sprintf('+521%07d', $i));
            $user->setRole($role);
            $user->setActive((bool)rand(0, 1)); // Aleatoriamente activo o inactivo
            
            // Hash password
            $hashedPassword = $this->passwordHasher->hashPassword($user, $password);
            $user->setPassword($hashedPassword);

            $this->entityManager->persist($user);

            if (($i % $batchSize) === 0) {
                $this->entityManager->flush();
                $this->entityManager->clear();
                // Necesitamos refrescar el rol después del clear para seguir usándolo
                $role = $this->roleRepository->find($role->getId());
                $io->text(sprintf('Batch %d/%d completado...', $i / $batchSize, 1000 / $batchSize));
            }
        }

        $this->entityManager->flush();
        $io->success('Se han generado 1000 usuarios exitosamente.');

        return Command::SUCCESS;
    }
}
