<?php
// src/Service/OtpService.php
namespace App\Service;

use App\Entity\User;
use Twilio\Rest\Client;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Psr\Log\LoggerInterface;

class OtpService
{
    private $twilioSid;
    private $twilioAuthToken;
    private $twilioFromNumber;
    private $logger;

    public function __construct(
        string $twilioSid,
        string $twilioAuthToken,
        string $twilioFromNumber,
        LoggerInterface $logger
    ) {
        $this->twilioSid = $twilioSid;
        $this->twilioAuthToken = $twilioAuthToken;
        $this->twilioFromNumber = $twilioFromNumber;
        $this->logger = $logger;
    }

    public function generateOtp(User $user): void
    {
        // Génération d'un code OTP à 6 chiffres
        $otpCode = sprintf('%06d', mt_rand(1, 999999));
        
        // Définir l'expiration à 10 minutes
        $expiresAt = new \DateTime('+10 minutes');
        
        // Mettre à jour l'utilisateur avec le nouveau code OTP
        $user->setOtpCode($otpCode)
             ->setOtpExpiresAt($expiresAt);
        
        // Envoyer l'OTP par SMS
        $this->sendSms($user->getPhoneNumber(), "Votre code de vérification est : $otpCode. Il expire dans 10 minutes.");
    }

    public function verifyOtp(User $user, string $code): bool
    {
        // Vérifier si le code OTP est valide et non expiré
        if ($user->isOtpValid() && $user->getOtpCode() === $code) {
            return true;
        }
        
        return false;
    }

    private function sendSms(string $to, string $message): bool
    {
        try {
            $client = new Client($this->twilioSid, $this->twilioAuthToken);
            
            $client->messages->create(
                $to,
                [
                    'from' => $this->twilioFromNumber,
                    'body' => $message,
                ]
            );
            
            $this->logger->info('SMS envoyé avec succès', [
                'to' => $to,
                'from' => $this->twilioFromNumber
            ]);
            
            return true;
        } catch (\Exception $e) {
            $this->logger->error('Erreur lors de l\'envoi du SMS', [
                'error' => $e->getMessage(),
                'to' => $to
            ]);
            
            return false;
        }
    }
}