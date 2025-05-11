<?php

// src/Controller/VerificationController.php
namespace App\Controller;

use App\Entity\User;
use App\Form\OtpVerificationType;
use App\Service\OtpService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Security\Http\SecurityRequestAttributes;
use Psr\Log\LoggerInterface;

class VerificationController extends AbstractController
{
    private $logger;
    
    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }
    
    #[Route('/verify', name: 'app_verify_otp')]
    public function verify(
        Request $request,
        EntityManagerInterface $entityManager,
        OtpService $otpService,
        AuthenticationUtils $authenticationUtils
    ): Response {
        // Get user from session
        $userId = $request->getSession()->get('otp_user_id');
        if (!$userId) {
            $this->logger->warning('OTP verification attempted without user ID in session');
            $this->addFlash('error', 'Session expirée. Veuillez vous inscrire à nouveau.');
            return $this->redirectToRoute('app_register');
        }

        $user = $entityManager->getRepository(User::class)->find($userId);
        if (!$user) {
            $this->logger->error('User not found for OTP verification', ['user_id' => $userId]);
            $this->addFlash('error', 'Utilisateur introuvable. Veuillez vous inscrire à nouveau.');
            return $this->redirectToRoute('app_register');
        }
        
        $form = $this->createForm(OtpVerificationType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $submittedCode = $form->get('code')->getData();
            $this->logger->info('OTP verification attempt', [
                'user_id' => $user->getId(),
                'email' => $user->getEmail()
            ]);
            
            if ($user->isOtpValid() && 
                $otpService->verifyOtp($user, $submittedCode)) {
                
                $user->setIsVerified(true)
                     ->setOtpCode(null)
                     ->setOtpExpiresAt(null);
                
                $entityManager->flush();
                
                $this->logger->info('User verified successfully', [
                    'user_id' => $user->getId(),
                    'email' => $user->getEmail()
                ]);

                // Clear session OTP data
                $request->getSession()->remove('otp_user_id');
                
                $this->addFlash('success', 'Votre compte a été vérifié avec succès. Vous pouvez maintenant vous connecter.');

                // Toujours rediriger vers la page de connexion après une vérification réussie
                $session = $request->getSession();
                $session->set(SecurityRequestAttributes::LAST_USERNAME, $user->getEmail());
                $session->set('verified', 1);
                return $this->redirectToRoute('app_login');
            }

            $this->logger->warning('Invalid OTP code entered', [
                'user_id' => $user->getId(),
                'is_otp_valid' => $user->isOtpValid()
            ]);
            $this->addFlash('error', 'Code de vérification invalide ou expiré');
        }

        // Handle case when OTP is expired
        if ($user && !$user->isOtpValid()) {
            $this->logger->info('OTP expired, generating new code', [
                'user_id' => $user->getId()
            ]);
            $otpService->generateOtp($user);
            $entityManager->flush();
            $this->addFlash('info', 'Un nouveau code de vérification a été envoyé');
        }

        return $this->render('verification/verify.html.twig', [
            'form' => $form->createView(),
            'phone' => $this->maskPhone($user->getPhoneNumber()),
            'last_username' => $authenticationUtils->getLastUsername(),
            'error' => $authenticationUtils->getLastAuthenticationError()
        ]);
    }

    #[Route('/resend-otp', name: 'app_resend_otp')]
    public function resendOtp(
        Request $request,
        EntityManagerInterface $entityManager,
        OtpService $otpService
    ): Response {
        $userId = $request->getSession()->get('otp_user_id');
        if (!$userId) {
            $this->logger->warning('OTP resend attempted without user ID in session');
            $this->addFlash('error', 'Session expirée. Veuillez vous inscrire à nouveau.');
            return $this->redirectToRoute('app_register');
        }

        $user = $entityManager->getRepository(User::class)->find($userId);
        if (!$user) {
            $this->logger->error('User not found for OTP resend', ['user_id' => $userId]);
            $this->addFlash('error', 'Utilisateur introuvable. Veuillez vous inscrire à nouveau.');
            return $this->redirectToRoute('app_register');
        }

        $this->logger->info('Resending OTP', [
            'user_id' => $user->getId(),
            'phone' => $this->maskPhone($user->getPhoneNumber())
        ]);
        
        $otpService->generateOtp($user);
        $entityManager->flush();

        $this->addFlash('success', 'Nouveau code de vérification envoyé !');
        return $this->redirectToRoute('app_verify_otp');
    }

    private function maskPhone(string $phone): string
    {
        // Ensure we have enough characters to mask
        if (strlen($phone) < 6) {
            return $phone;
        }
        
        // Keep first 3 and last 3 digits visible, mask the rest
        $visiblePart1 = substr($phone, 0, 3);
        $visiblePart2 = substr($phone, -3);
        $maskedLength = strlen($phone) - 6;
        $maskedPart = str_repeat('*', $maskedLength);
        
        return $visiblePart1 . $maskedPart . $visiblePart2;
    }
}