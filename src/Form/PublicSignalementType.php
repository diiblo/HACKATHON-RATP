<?php

namespace App\Form;

use App\Entity\Signalement;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\NotBlank;

class PublicSignalementType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('type', HiddenType::class, [
                'constraints' => [
                    new NotBlank(message: 'Veuillez choisir un type de signalement.'),
                    new Choice(choices: ['incident', 'positif']),
                ],
            ])
            ->add('plainantNom', TextType::class, [
                'required' => false,
                'label' => false,
                'attr' => ['placeholder' => 'Votre nom (optionnel)', 'class' => 'form-control'],
            ])
            ->add('plainantEmail', EmailType::class, [
                'required' => false,
                'label' => false,
                'attr' => ['placeholder' => 'Votre e-mail (optionnel)', 'class' => 'form-control'],
                'constraints' => [new Email(message: 'Adresse e-mail invalide.')],
            ])
            ->add('plainantTelephone', TelType::class, [
                'required' => false,
                'label' => false,
                'attr' => ['placeholder' => 'Votre téléphone (optionnel)', 'class' => 'form-control'],
            ])
            ->add('agentDescription', TextType::class, [
                'label' => false,
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ex : conducteur ligne 13, agent au guichet, contrôleur...',
                ],
                'help' => 'Si vous pouvez la décrire, cela aidera nos équipes à identifier la situation plus vite.',
            ])
            ->add('description', TextareaType::class, [
                'label' => false,
                'constraints' => [new NotBlank(message: 'Merci de décrire les faits.')],
                'attr' => [
                    'class' => 'form-control',
                    'rows'  => 6,
                    'placeholder' => 'Décrivez librement les faits, le contexte, le lieu, l\'heure approximative et tout élément utile.',
                ],
            ])
            ->add('dateFait', DateType::class, [
                'label' => false,
                'widget' => 'single_text',
                'required' => false,
                'attr' => ['class' => 'form-control'],
                'help' => 'Laissez vide si vous ne vous souvenez pas de la date exacte.',
                'data' => new \DateTimeImmutable(),
            ])
            ->add('pieceJointe', FileType::class, [
                'label' => false,
                'required' => false,
                'mapped' => false,
                'constraints' => [
                    new File(
                        maxSize: '10M',
                        mimeTypes: [
                            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                            'application/msword',
                            'application/pdf',
                            'image/jpeg',
                            'image/png',
                        ],
                        mimeTypesMessage: 'Formats acceptés : .docx, .doc, .pdf, .jpg, .png (max 10 Mo)',
                    ),
                ],
                'attr' => ['class' => 'form-control', 'accept' => '.docx,.doc,.pdf,.jpg,.jpeg,.png'],
                'help' => 'Vous pouvez joindre un document, un PDF ou une photo.',
            ])
            ->add('noteVocale', FileType::class, [
                'label' => false,
                'required' => false,
                'mapped' => false,
                'constraints' => [
                    new File(
                        maxSize: '10M',
                        mimeTypes: [
                            'audio/mpeg',
                            'audio/wav',
                            'audio/x-wav',
                            'audio/mp4',
                            'audio/x-m4a',
                        ],
                        mimeTypesMessage: 'Formats audio acceptés : .mp3, .wav, .m4a (max 10 Mo)',
                    ),
                ],
                'attr' => ['class' => 'form-control d-none', 'accept' => '.mp3,.wav,.m4a,audio/*'],
                'help' => 'Enregistrez directement votre message vocal depuis le navigateur.',
            ])
            ->add('voiceLanguageHint', ChoiceType::class, [
                'label' => false,
                'required' => false,
                'mapped' => false,
                'placeholder' => 'Détection automatique',
                'choices' => [
                    'Français' => 'fr',
                    'Anglais' => 'en',
                    'Espagnol' => 'es',
                    'Arabe' => 'ar',
                ],
                'attr' => ['class' => 'form-select'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Signalement::class,
        ]);
    }
}
