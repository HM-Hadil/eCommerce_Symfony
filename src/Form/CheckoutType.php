<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

class CheckoutType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('shippingAddress', TextareaType::class, [
                'label' => 'Adresse de livraison',
                'required' => true,
                'constraints' => [
                    new NotBlank([
                        'message' => 'Veuillez entrer une adresse de livraison'
                    ])
                ],
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 3
                ]
            ])
            ->add('billingAddress', TextareaType::class, [
                'label' => 'Adresse de facturation',
                'required' => true,
                'constraints' => [
                    new NotBlank([
                        'message' => 'Veuillez entrer une adresse de facturation'
                    ])
                ],
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 3
                ]
            ])
            ->add('paymentMethod', ChoiceType::class, [
                'label' => 'Méthode de paiement',
                'choices' => [
                    'Carte de crédit' => 'credit_card',
                    'PayPal' => 'paypal',
                    'Virement bancaire' => 'bank_transfer'
                ],
                'expanded' => true,
                'multiple' => false,
                'required' => true,
                'constraints' => [
                    new NotBlank([
                        'message' => 'Veuillez choisir une méthode de paiement'
                    ])
                ],
                'attr' => [
                    'class' => 'payment-method-selection'
                ]
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_protection' => true,
            'csrf_field_name' => '_token',
            'csrf_token_id' => 'checkout_form',
        ]);
    }
}