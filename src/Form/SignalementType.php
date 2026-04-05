<?php

namespace App\Form;

use App\Entity\Agent;
use App\Entity\Signalement;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

class SignalementType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('agent', EntityType::class, [
                'class' => Agent::class,
                'choice_label' => fn(Agent $a) => $a->getFullName() . ' (' . $a->getMatricule() . ')',
                'label' => 'Agent concerné',
                'attr' => ['class' => 'form-select'],
                'query_builder' => fn($repo) => $repo->createQueryBuilder('a')
                    ->where('a.actif = true')
                    ->orderBy('a.nom', 'ASC'),
            ])
            ->add('type', ChoiceType::class, [
                'label' => 'Type de signalement',
                'choices' => [
                    'Incident' => 'incident',
                    'Avis positif' => 'positif',
                ],
                'attr' => ['class' => 'form-select', 'id' => 'signalement_type'],
                'constraints' => [new NotBlank()],
            ])
            ->add('canal', ChoiceType::class, [
                'label' => 'Canal de réception',
                'choices' => [
                    'Formulaire' => 'formulaire',
                    'E-mail'     => 'email',
                    'Terrain'    => 'terrain',
                    'Réseaux sociaux' => 'social',
                    'Autre'      => 'autre',
                ],
                'attr' => ['class' => 'form-select'],
            ])
            ->add('titre', TextType::class, [
                'label' => 'Titre',
                'constraints' => [new NotBlank()],
                'attr' => ['class' => 'form-control', 'placeholder' => 'Résumé court du signalement'],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description des faits',
                'constraints' => [new NotBlank()],
                'attr' => ['class' => 'form-control', 'rows' => 6],
            ])
            ->add('dateFait', DateType::class, [
                'label' => 'Date des faits',
                'widget' => 'single_text',
                'constraints' => [new NotBlank()],
                'attr' => ['class' => 'form-control'],
            ])
            ->add('gravite', ChoiceType::class, [
                'label' => 'Gravité',
                'required' => false,
                'placeholder' => '— Non applicable (avis positif) —',
                'choices' => [
                    'Faible' => 'faible',
                    'Moyen'  => 'moyen',
                    'Grave'  => 'grave',
                ],
                'attr' => ['class' => 'form-select', 'id' => 'signalement_gravite'],
            ])
            ->add('agentDescription', TextareaType::class, [
                'label' => 'Description de l\'agent (si non identifié)',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 2,
                    'placeholder' => 'Ex : conducteur ligne 13, grand, environ 40 ans…',
                ],
                'help' => 'À renseigner si l\'agent n\'est pas encore identifié dans le référentiel.',
            ])
            ->add('pieceJointe', FileType::class, [
                'label' => 'Ajouter une pièce jointe',
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
                'help' => 'Simulation SharePoint — .docx, .pdf, .jpg, .png — max 10 Mo',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Signalement::class,
        ]);
    }
}
