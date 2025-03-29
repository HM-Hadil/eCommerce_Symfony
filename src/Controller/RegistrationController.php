<?php
namespace App\Controller;

use App\Entity\User;
use App\Entity\Role;
use App\Form\RegistrationType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Psr\Log\LoggerInterface;

class RegistrationController extends AbstractController
{
    private $logger;
    
    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    #[Route('/register', name: 'app_register')]
    public function register(
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher
    ): Response {
        $user = new User();
        $form = $this->createForm(RegistrationType::class, $user);
        
        $this->logger->info('Form submission method: ' . $request->getMethod());
        
        $form->handleRequest($request);
        
        if ($form->isSubmitted() && $form->isValid()) {
            $this->logger->info('Form is submitted and valid');
            
            try {
                // Hash the password
                $hashedPassword = $passwordHasher->hashPassword($user, $user->getPassword());
                $user->setPassword($hashedPassword);
                $user->setIsVerified(false);
                
                // Get the selected role from the form (note the field name change)
                $roleName = $form->get('roleChoice')->getData();
                
                // Find or create the role
                $role = $entityManager->getRepository(Role::class)->findOneBy(['name' => $roleName]);
                
                if (!$role) {
                    $role = new Role();
                    $role->setName($roleName);
                    $entityManager->persist($role);
                }
                
                // Add the role to the user
                $user->addRole($role);
                
                $entityManager->persist($user);
                $entityManager->flush();
                
                $this->addFlash('success', 'Votre compte a été créé avec succès');
                return $this->redirectToRoute('app_home');
                
            } catch (\Exception $e) {
                $this->logger->error('Error during registration: ' . $e->getMessage());
                $this->addFlash('error', 'Une erreur est survenue lors de l\'inscription');
            }
        } elseif ($form->isSubmitted()) {
            $errors = $form->getErrors(true);
            foreach ($errors as $error) {
                $this->addFlash('error', $error->getMessage());
            }
        }
        
        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form->createView(),
        ]);
    }
}