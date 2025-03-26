<?php
namespace App\Controller;

use App\Entity\User;
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
        $form->handleRequest($request);
    
        // First check if the form is submitted
        if ($form->isSubmitted()) {
            // Then check if the form is valid
            if ($form->isValid()) {
                try {
                    // Hash the password
                    $hashedPassword = $passwordHasher->hashPassword($user, $user->getPassword());
                    $user->setPassword($hashedPassword);
                    $user->setIsVerified(false);
    
                    // Persist the user
                    $entityManager->persist($user);
                    $entityManager->flush();
    
                    $this->addFlash('success', 'Votre compte a été créé avec succès');
                    return $this->redirectToRoute('app_home');
                } catch (\Exception $e) {
                    // Log any exception during user creation
                    $this->addFlash('error', 'Erreur lors de la création du compte: ' . $e->getMessage());
                }
            } else {
                // If form is not valid, collect and display errors
                $errors = $form->getErrors(true);
                foreach ($errors as $error) {
                    $this->addFlash('error', $error->getMessage());
                }
            }
        }
    
        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form->createView(),
        ]);
    }
}