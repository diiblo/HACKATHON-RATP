<?php

namespace App\Controller;

use App\Entity\AiProviderConfig;
use App\Entity\User;
use App\Form\AiProviderConfigType;
use App\Form\UserType;
use App\Repository\AuditLogRepository;
use App\Repository\AiProviderConfigRepository;
use App\Repository\UserRepository;
use App\Service\AiConfigurationManager;
use App\Service\AiGateway;
use App\Service\AuditLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
class AdminController extends AbstractController
{
    #[Route('/ai', name: 'app_admin_ai_configs', methods: ['GET'])]
    #[IsGranted('ROLE_DEV')]
    public function aiConfigs(AiProviderConfigRepository $repository, AiConfigurationManager $configurationManager): Response
    {
        return $this->render('admin/ai_configs.html.twig', [
            'configs' => $repository->findBy([], ['name' => 'ASC']),
            'configurationManager' => $configurationManager,
        ]);
    }

    #[Route('/ai/new', name: 'app_admin_ai_config_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_DEV')]
    public function newAiConfig(
        Request $request,
        AiConfigurationManager $configurationManager
    ): Response {
        $config = new AiProviderConfig();
        $form = $this->createForm(AiProviderConfigType::class, $config);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $configurationManager->save($config, $form->get('plainApiKey')->getData(), $this->getUser());
            $this->addFlash('success', 'Configuration IA créée.');
            return $this->redirectToRoute('app_admin_ai_configs');
        }

        return $this->render('admin/ai_config_form.html.twig', [
            'form' => $form,
            'config' => null,
            'maskedApiKey' => null,
        ]);
    }

    #[Route('/ai/{id}/edit', name: 'app_admin_ai_config_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_DEV')]
    public function editAiConfig(
        AiProviderConfig $config,
        Request $request,
        AiConfigurationManager $configurationManager
    ): Response {
        $form = $this->createForm(AiProviderConfigType::class, $config);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $configurationManager->save($config, $form->get('plainApiKey')->getData(), $this->getUser());
            $this->addFlash('success', 'Configuration IA mise à jour.');
            return $this->redirectToRoute('app_admin_ai_configs');
        }

        return $this->render('admin/ai_config_form.html.twig', [
            'form' => $form,
            'config' => $config,
            'maskedApiKey' => $configurationManager->maskApiKey($config),
            'testResult' => null,
        ]);
    }

    #[Route('/ai/{id}/test', name: 'app_admin_ai_config_test', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_DEV')]
    public function testAiConfig(
        AiProviderConfig $config,
        Request $request,
        AiGateway $gateway,
        AuditLogger $auditLogger
    ): Response {
        $prompt = trim((string) $request->request->get('test_prompt', ''));
        if ($prompt === '') {
            $this->addFlash('warning', 'Merci de saisir un prompt de test.');
            return $this->redirectToRoute('app_admin_ai_config_edit', ['id' => $config->getId()]);
        }

        try {
            $raw = $gateway->chat($config, [
                ['role' => 'system', 'content' => 'Tu es un assistant de test. Réponds brièvement.'],
                ['role' => 'user', 'content' => $prompt],
            ]);
            $auditLogger->log(
                'ai.config.test',
                sprintf('Test IA exécuté sur la configuration %s.', $config->getName()),
                ['prompt' => $prompt],
                $config,
                $this->getUser()
            );
            $this->addFlash('success', 'Test IA exécuté.');
            $this->addFlash('info', 'Réponse test IA : ' . mb_strimwidth($raw, 0, 500, '…'));
        } catch (\Throwable $e) {
            $this->addFlash('error', 'Échec du test IA : ' . $e->getMessage());
        }

        return $this->redirectToRoute('app_admin_ai_config_edit', ['id' => $config->getId()]);
    }

    #[Route('/audit', name: 'app_admin_audit', methods: ['GET'])]
    #[IsGranted('ROLE_DEV')]
    public function audit(AuditLogRepository $repository): Response
    {
        return $this->render('admin/audit.html.twig', [
            'logs' => $repository->findLatest(200),
        ]);
    }

    #[Route('/users', name: 'app_admin_users', methods: ['GET'])]
    public function users(UserRepository $repo): Response
    {
        return $this->render('admin/users.html.twig', [
            'users' => $repo->findBy([], ['nom' => 'ASC']),
        ]);
    }

    #[Route('/users/new', name: 'app_admin_user_new', methods: ['GET', 'POST'])]
    public function newUser(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher
    ): Response {
        $user = new User();
        $form = $this->createForm(UserType::class, $user, ['is_edit' => false]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plainPassword = $form->get('plainPassword')->getData();
            $user->setPassword($hasher->hashPassword($user, $plainPassword));
            $em->persist($user);
            $em->flush();

            $this->addFlash('success', 'Utilisateur créé.');
            return $this->redirectToRoute('app_admin_users');
        }

        return $this->render('admin/user_form.html.twig', ['form' => $form, 'user' => null]);
    }

    #[Route('/users/{id}/edit', name: 'app_admin_user_edit', methods: ['GET', 'POST'])]
    public function editUser(
        User $user,
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher
    ): Response {
        $form = $this->createForm(UserType::class, $user, ['is_edit' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plainPassword = $form->get('plainPassword')->getData();
            if (!empty($plainPassword)) {
                $user->setPassword($hasher->hashPassword($user, $plainPassword));
            }
            $em->flush();
            $this->addFlash('success', 'Utilisateur mis à jour.');
            return $this->redirectToRoute('app_admin_users');
        }

        return $this->render('admin/user_form.html.twig', ['form' => $form, 'user' => $user]);
    }
}
