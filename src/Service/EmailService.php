<?php

namespace App\Service;

use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;
use App\Entity\Order;

class EmailService
{
    private $mailer;
    private $senderEmail;
    
    public function __construct(MailerInterface $mailer, string $senderEmail = 'noreply@votre-site.com')
    {
        $this->mailer = $mailer;
        $this->senderEmail = $senderEmail;
    }
    
    /**
     * Envoie un email de confirmation de commande avec la facture en PDF
     */
    public function sendOrderConfirmationWithInvoice(string $recipientEmail, Order $order, string $pdfContent): void
    {
        $email = (new Email())
            ->from($this->senderEmail)
            ->to($recipientEmail)
            ->subject('Confirmation de votre commande #' . $order->getReference())
            ->html($this->generateOrderConfirmationHtml($order))
            ->addPart(new DataPart(
                $pdfContent,
                'facture-' . $order->getReference() . '.pdf',
                'application/pdf'
            ));
            
        $this->mailer->send($email);
    }
    
    /**
     * Génère le contenu HTML du mail de confirmation de commande
     */
    private function generateOrderConfirmationHtml(Order $order): string
    {
        $html = '
        <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
            <h2 style="color: #333;">Confirmation de commande</h2>
            <p>Bonjour ' . $order->getUser()->getFullName() . ',</p>
            <p>Nous vous remercions pour votre commande. Votre paiement a été traité avec succès.</p>
            
            <div style="background-color: #f9f9f9; padding: 15px; border-radius: 5px; margin: 20px 0;">
                <h3 style="margin-top: 0;">Détails de la commande #' . $order->getReference() . '</h3>
                <p><strong>Date:</strong> ' . $order->getPaymentDate()->format('d/m/Y') . '</p>
                <p><strong>Montant total:</strong> ' . number_format($order->getTotalAmount(), 2, ',', ' ') . ' €</p>
            </div>
            
            <table style="width: 100%; border-collapse: collapse; margin-bottom: 20px;">
                <thead>
                    <tr style="background-color: #f2f2f2;">
                        <th style="padding: 10px; text-align: left; border: 1px solid #ddd;">Produit</th>
                        <th style="padding: 10px; text-align: right; border: 1px solid #ddd;">Quantité</th>
                        <th style="padding: 10px; text-align: right; border: 1px solid #ddd;">Prix</th>
                        <th style="padding: 10px; text-align: right; border: 1px solid #ddd;">Sous-total</th>
                    </tr>
                </thead>
                <tbody>';
        
        foreach ($order->getOrderItems() as $item) {
            $html .= '
                <tr>
                    <td style="padding: 10px; border: 1px solid #ddd;">' . $item->getProduct()->getName() . '</td>
                    <td style="padding: 10px; text-align: right; border: 1px solid #ddd;">' . $item->getQuantity() . '</td>
                    <td style="padding: 10px; text-align: right; border: 1px solid #ddd;">' . number_format($item->getPrice(), 2, ',', ' ') . ' €</td>
                    <td style="padding: 10px; text-align: right; border: 1px solid #ddd;">' . number_format($item->getSubtotal(), 2, ',', ' ') . ' €</td>
                </tr>';
        }
                
        $html .= '
                </tbody>
                <tfoot>
                    <tr style="background-color: #f2f2f2;">
                        <td colspan="3" style="padding: 10px; text-align: right; border: 1px solid #ddd;"><strong>Total</strong></td>
                        <td style="padding: 10px; text-align: right; border: 1px solid #ddd;"><strong>' . number_format($order->getTotalAmount(), 2, ',', ' ') . ' €</strong></td>
                    </tr>
                </tfoot>
            </table>
            
            <p>Une facture est jointe à cet email au format PDF.</p>
            
            <p>Merci pour votre confiance!</p>
            <p>L\'équipe de votre boutique</p>
        </div>';
        
        return $html;
    }
}