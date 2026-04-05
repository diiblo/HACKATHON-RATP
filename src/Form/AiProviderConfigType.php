<?php

namespace App\Form;

use App\Entity\AiProviderConfig;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;

class AiProviderConfigType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom interne',
                'constraints' => [new NotBlank(), new Length(max: 120)],
                'attr' => ['class' => 'form-control'],
            ])
            ->add('vendorLabel', TextType::class, [
                'label' => 'Fournisseur / label visible',
                'required' => false,
                'attr' => ['class' => 'form-control', 'placeholder' => 'OpenAI, OpenRouter, Ollama...'],
            ])
            ->add('providerType', ChoiceType::class, [
                'label' => 'Type de connecteur',
                'choices' => array_flip(AiProviderConfig::PROVIDER_TYPES),
                'attr' => ['class' => 'form-select'],
            ])
            ->add('apiBaseUrl', TextType::class, [
                'label' => 'URL API',
                'constraints' => [new NotBlank()],
                'attr' => ['class' => 'form-control', 'placeholder' => 'https://api.openai.com/v1'],
            ])
            ->add('apiPath', TextType::class, [
                'label' => 'Chemin API',
                'required' => false,
                'attr' => ['class' => 'form-control', 'placeholder' => '/chat/completions ou /v1beta/models/{model}:generateContent'],
                'help' => 'Optionnel. Laissez vide pour utiliser le chemin par défaut du fournisseur.',
            ])
            ->add('plainApiKey', PasswordType::class, [
                'label' => 'Clé API',
                'mapped' => false,
                'required' => false,
                'help' => 'Laissez vide pour conserver la clé actuelle.',
                'attr' => ['class' => 'form-control', 'autocomplete' => 'new-password'],
            ])
            ->add('model', TextType::class, [
                'label' => 'Modèle par défaut',
                'constraints' => [new NotBlank()],
                'attr' => ['class' => 'form-control', 'placeholder' => 'gpt-4o-mini, mistral-small, llama3.1...'],
            ])
            ->add('temperature', NumberType::class, [
                'label' => 'Température',
                'scale' => 2,
                'html5' => true,
                'attr' => ['class' => 'form-control', 'step' => '0.1', 'min' => '0', 'max' => '2'],
            ])
            ->add('timeoutSeconds', IntegerType::class, [
                'label' => 'Timeout (secondes)',
                'attr' => ['class' => 'form-control', 'min' => '1', 'max' => '120'],
            ])
            ->add('extraHeaders', TextareaType::class, [
                'label' => 'En-têtes supplémentaires',
                'required' => false,
                'attr' => ['class' => 'form-control', 'rows' => 4],
                'help' => 'Un header par ligne au format `Nom: valeur`.',
            ])
            ->add('systemPrompt', TextareaType::class, [
                'label' => 'Prompt système',
                'required' => false,
                'attr' => ['class' => 'form-control', 'rows' => 6],
                'help' => 'Consignes globales données au modèle.',
            ])
            ->add('contextTemplate', TextareaType::class, [
                'label' => 'Contexte métier / template',
                'required' => false,
                'attr' => ['class' => 'form-control', 'rows' => 8],
                'help' => 'Variables : {{titre}}, {{description}}, {{type}}, {{gravite}}, {{canal}}, {{agent}}, {{statut}}, {{commentaires}}, {{traduction}}, {{note_vocale}}, {{source}}, {{video_timer}}, {{plainte}}',
            ])
            ->add('active', CheckboxType::class, [
                'label' => 'Configuration active',
                'required' => false,
            ])
            ->add('isDefault', CheckboxType::class, [
                'label' => 'Configuration par défaut',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => AiProviderConfig::class,
        ]);
    }
}
