<?php
namespace App\Command;

use App\Entity\Role;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class AddRolesCommand extends Command
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        parent::__construct();
        $this->entityManager = $entityManager;
    }

    protected static $defaultName = 'app:add-roles';

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $roleAdmin = new Role();
        $roleAdmin->setName('ROLE_ADMIN');

        $roleUser = new Role();
        $roleUser->setName('ROLE_USER');

        // Persister les rôles
        $this->entityManager->persist($roleAdmin);
        $this->entityManager->persist($roleUser);
        $this->entityManager->flush();

        $output->writeln('Roles added successfully!');
        
        return Command::SUCCESS;
    }
}
