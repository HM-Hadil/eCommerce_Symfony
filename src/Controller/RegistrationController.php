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
    private LoggerInterface $logger;

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
        // Redirection si l'utilisateur est déjà connecté
        if ($this->getUser()) {
            return $this->redirectToRoute('app_home');
        }

        $user = new User();
        $form = $this->createForm(RegistrationType::class, $user);
        
        $this->logger->info('Tentative d\'inscription', [
            'ip' => $request->getClientIp(),
            'method' => $request->getMethod(),
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $this->logger->info('Formulaire soumis', [
                'valid' => $form->isValid(),
                'email' => $user->getEmail(),
            ]);

            if ($form->isValid()) {
                try {
                    // 🔹 1. Hachage du mot de passe
                    $hashedPassword = $passwordHasher->hashPassword($user, $user->getPassword());
                    $user->setPassword($hashedPassword);
                    $user->setIsVerified(false);

                    // 🔹 2. Attribution du rôle
                    $roleChoice = $form->has('roleChoice') ? $form->get('roleChoice')->getData() : null;

                    if ($roleChoice) {
                        $role = $entityManager->getRepository(Role::class)->findOneBy(['name' => $roleChoice]);

                        if (!$role) {
                            $role = new Role();
                            $role->setName($roleChoice);
                            $entityManager->persist($role);
                        }
                        $user->addUserRole($role);
                    }

                    // 🔹 3. Formatage du numéro de téléphone
                    $phoneNumber = trim($user->getPhoneNumber());

                    if (!str_starts_with($phoneNumber, '+')) {
                        if (str_starts_with($phoneNumber, '0')) {
                            $phoneNumber = '+216' . substr($phoneNumber, 1);
                        } else {
                            $phoneNumber = '+216' . $phoneNumber;
                        }
                    }
                    $user->setPhoneNumber($phoneNumber);

                    // 🔹 4. Génération et envoi de l'OTP
                    $otpService->generateOtp($user);

                    // 🔹 5. Persistance en base de données
                    $entityManager->persist($user);
                    $entityManager->flush();

                    // 🔹 6. Stockage de l'ID utilisateur pour vérification OTP
                    $session = $request->getSession();
                    $session->set('otp_user_id', $user->getId());
                    // Modification pour rediriger vers la page de vérification
                    $session->set('otp_verified_redirect', 'verify');

                    // 🔹 7. Journalisation et redirection
                    $this->logger->info('Utilisateur inscrit avec succès', [
                        'user_id' => $user->getId(),
                        'email' => $user->getEmail(),
                        'phone' => $user->getPhoneNumber(),
                    ]);

                    $this->addFlash('success', 'Un code de vérification a été envoyé à votre numéro de téléphone.');
                    // Redirection vers la page de vérification au lieu de la page de connexion
                    return $this->redirectToRoute('app_verify_otp');

                } catch (\Throwable $e) {
                    // Gestion des erreurs avec journalisation détaillée
                    $this->logger->error('Erreur lors de l\'inscription', [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);

                    $this->addFlash('error', 'Inscription échouée. Veuillez réessayer.');
                }
            } else {
                // 🔹 Gestion des erreurs du formulaire
                $errors = [];
                foreach ($form->getErrors(true) as $error) {
                    $errors[] = $error->getMessage();
                    $this->addFlash('error', $error->getMessage());
                }
                $this->logger->warning('Échec de la validation du formulaire', ['errors' => $errors]);
            }
        }

        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form->createView(),
        ]);
    }
}