<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
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
                        'message' => 'Veuillez saisir une adresse de livraison',
                    ]),
                ],
                'attr' => [
                    'rows' => 3,
                    'placeholder' => 'Rue, code postal, ville, pays',
                ],
            ])
            ->add('billingAddress', TextareaType::class, [
                'label' => 'Adresse de facturation',
                'required' => true,
                'constraints' => [
                    new NotBlank([
                        'message' => 'Veuillez saisir une adresse de facturation',
                    ]),
                ],
                'attr' => [
                    'rows' => 3,
                    'placeholder' => 'Rue, code postal, ville, pays',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
        ]);
    }
}