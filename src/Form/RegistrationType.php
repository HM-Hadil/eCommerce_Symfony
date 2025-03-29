<?php
namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class RegistrationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\Email(),
                ]
            ])
            ->add('password', PasswordType::class, [
                'label' => 'Mot de passe',
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\Length(['min' => 6]),
                ]
            ])
            ->add('phoneNumber', TelType::class, [
                'label' => 'Numéro de téléphone',
                'constraints' => [
                    new Assert\NotBlank(),
                ]
            ])
            ->add('roleChoice', ChoiceType::class, [  // Changed from 'role' to 'roleChoice'
                'mapped' => false,  // This field is not mapped to the User entity
                'choices' => [
                    'Acheteur' => 'ROLE_USER',
                    'Vendeur' => 'ROLE_ADMIN',
                ],
                'expanded' => true,
                'multiple' => false,
                'label' => 'Choisissez votre rôle'
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'S\'inscrire',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}