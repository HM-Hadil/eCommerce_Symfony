<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\Role;
use App\Form\RegistrationType;
use App\Service\OtpService;
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
        UserPasswordHasherInterface $passwordHasher,
        OtpService $otpService
    ): Response {
        // Redirect already authenticated users
        if ($this->getUser()) {
            return $this->redirectToRoute('app_home');
        }

        $user = new User();
        $form = $this->createForm(RegistrationType::class, $user);
        
        $this->logger->info('Registration attempt', [
            'ip' => $request->getClientIp(),
            'method' => $request->getMethod()
        ]);
        
        $form->handleRequest($request);
        
        if ($form->isSubmitted()) {
            $this->logger->info('Form submitted', [
                'valid' => $form->isValid(),
                'email' => $user->getEmail()
            ]);
            
            if ($form->isValid()) {
                try {
                    // 1. Hash password
                    $hashedPassword = $passwordHasher->hashPassword(
                        $user, 
                        $user->getPassword()
                    );
                    $user->setPassword($hashedPassword)
                         ->setIsVerified(false);

                    // 2. Handle role assignment
                    $roleName = $form->get('roleChoice')->getData();
                    $role = $entityManager->getRepository(Role::class)
                        ->findOneBy(['name' => $roleName]);
                    
                    if (!$role) {
                        $role = new Role();
                        $role->setName($roleName);
                        $entityManager->persist($role);
                    }
                    $user->addUserRole($role);

                    // 3. Format phone number (remove spaces and ensure international format)
                    $phoneNumber = $user->getPhoneNumber();
                    // Ensure phone number starts with '+' if it doesn't
                    if (!str_starts_with($phoneNumber, '+')) {
                        // If it starts with 0, replace with Tunisia country code
                        if (str_starts_with($phoneNumber, '0')) {
                            $phoneNumber = '+216' . substr($phoneNumber, 1);
                        } else {
                            $phoneNumber = '+216' . $phoneNumber;
                        }
                    }
                    $user->setPhoneNumber($phoneNumber);

                    // 4. Generate and send OTP
                    $otpService->generateOtp($user);

                    // 5. Persist user and flush
                    $entityManager->persist($user);
                    $entityManager->flush();

                    // 6. Store user ID in session for verification
                    $request->getSession()->set('otp_user_id', $user->getId());
                    $request->getSession()->set('otp_verified_redirect', 'app_home');

                    // 7. Log and redirect to verification
                    $this->logger->info('User registered successfully', [
                        'user_id' => $user->getId(),
                        'email' => $user->getEmail(),
                        'phone' => $user->getPhoneNumber() // Log the formatted phone number
                    ]);

                    $this->addFlash('success', 'Un code de vérification a été envoyé à votre numéro de téléphone.');
                    return $this->redirectToRoute('app_verify_otp');

                } catch (\Exception $e) {
                    $this->logger->error('Registration error', [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                    $this->addFlash('error', 'Inscription échouée. Veuillez réessayer.');
                }
            } else {
                // Log form errors
                $errors = [];
                foreach ($form->getErrors(true) as $error) {
                    $errors[] = $error->getMessage();
                    $this->addFlash('error', $error->getMessage());
                }
                $this->logger->warning('Form validation failed', ['errors' => $errors]);
            }
        }
        
        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form->createView(),
        ]);
    }
}