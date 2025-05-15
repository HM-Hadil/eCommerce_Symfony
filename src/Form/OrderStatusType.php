<?php

namespace App\Form;

use App\Entity\Order;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class OrderStatusType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $order = $options['data'];
        $currentStatus = $order->getStatus();
        
        // Define allowed next statuses based on current status
        $allowedStatusTransitions = [
            Order::STATUS_PENDING => [
                Order::STATUS_PROCESSING => 'En traitement',
                Order::STATUS_CANCELLED => 'Annulée'
            ],
            Order::STATUS_PROCESSING => [
                Order::STATUS_SHIPPED => 'Expédiée',
                Order::STATUS_CANCELLED => 'Annulée'
            ],
            Order::STATUS_SHIPPED => [
                Order::STATUS_DELIVERED => 'Livrée',
                Order::STATUS_CANCELLED => 'Annulée'
            ],
            Order::STATUS_DELIVERED => [],
            Order::STATUS_CANCELLED => []
        ];
        
        $statusChoices = $allowedStatusTransitions[$currentStatus] ?? [];
        
        // Always include current status in choices
        $statusChoices[$order->getStatusLabel()] = $currentStatus;
        
        $builder
            ->add('status', ChoiceType::class, [
                'choices' => array_flip($statusChoices),
                'label' => 'Statut de la commande',
                'expanded' => true,
                'required' => true
            ])
            ->add('notes', TextareaType::class, [
                'label' => 'Notes',
                'required' => false
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Order::class,
        ]);
    }
}