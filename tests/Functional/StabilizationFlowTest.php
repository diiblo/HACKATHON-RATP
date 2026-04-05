<?php

namespace App\Tests\Functional;

use App\Entity\Agent;
use App\Entity\HistoriqueStatut;
use App\Entity\PieceJointe;
use App\Entity\Signalement;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class StabilizationFlowTest extends WebTestCase
{
    private string $uploadDir;

    protected function setUp(): void
    {
        self::ensureKernelShutdown();
        self::bootKernel();

        $container = static::getContainer();
        $this->uploadDir = $container->getParameter('app.upload_dir');

        $this->resetSchema();
        $this->resetUploadDirectory();
        $this->seedData();
        self::ensureKernelShutdown();
    }

    protected function tearDown(): void
    {
        $this->resetUploadDirectory();
        parent::tearDown();
    }

    public function testPublicRoutesAreAccessibleWithoutAuthentication(): void
    {
        $client = static::createClient();

        $client->request('GET', '/public/signalement');
        self::assertResponseIsSuccessful();

        $client->request('GET', '/public/signalement/reseaux-sociaux');
        self::assertResponseIsSuccessful();
    }

    public function testQuickAccessQrRedirectsToPrefilledForm(): void
    {
        $client = static::createClient();

        $client->request('GET', '/public/scan/BUS-72-4589-NATION');

        self::assertResponseRedirects();
        $client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Véhicule 4589', $client->getCrawler()->filter('body')->text());
    }

    public function testPublicFormCanBePrefilledFromNfcStyleContext(): void
    {
        $client = static::createClient();

        $client->request('GET', '/public/signalement?line=72&vehicle=4589&stop=Nation&occurredAt=2026-04-03T08:30:00');

        self::assertResponseIsSuccessful();
        $content = $client->getCrawler()->filter('body')->text();
        self::assertStringContainsString('Ligne 72', $content);
        self::assertStringContainsString('Véhicule 4589', $content);
        self::assertStringContainsString('Nation', $content);
        self::assertStringContainsString('Conducteur de la ligne 72', (string) $client->getResponse()->getContent());
    }

    public function testPublicAttachmentDownloadIsAccessible(): void
    {
        $client = static::createClient();
        $piece = $this->getEntityManager()->getRepository(PieceJointe::class)->findOneBy(['originalName' => 'public-proof.pdf']);
        self::assertNotNull($piece);

        $client->request('GET', '/piece-jointe/' . $piece->getId() . '/download');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('inline', (string) $client->getResponse()->headers->get('content-disposition'));
        self::assertStringContainsString('public-proof.pdf', (string) $client->getResponse()->headers->get('content-disposition'));
    }

    public function testRestrictedAttachmentDownloadRequiresAuthentication(): void
    {
        $client = static::createClient();
        $signalement = $this->getEntityManager()->getRepository(Signalement::class)->findOneBy(['titre' => 'Signalement agent test']);
        self::assertNotNull($signalement);

        $filepath = $this->uploadDir . '/private-proof.pdf';
        file_put_contents($filepath, 'preuve privée');
        $piece = (new PieceJointe())
            ->setSignalement($signalement)
            ->setFilename('private-proof.pdf')
            ->setOriginalName('private-proof.pdf')
            ->setMimeType('application/pdf')
            ->setSize(filesize($filepath) ?: 0)
            ->setVisibility(PieceJointe::VISIBILITY_RESTRICTED)
            ->setCategory('complaint_proof');
        $em = $this->getEntityManager();
        $em->persist($piece);
        $em->flush();

        $client->request('GET', '/piece-jointe/' . $piece->getId() . '/download');

        self::assertResponseRedirects('http://localhost/login', Response::HTTP_FOUND);
    }

    public function testPublicSignalementCanBeViewedWithoutAssignedAgent(): void
    {
        $client = static::createClient();
        $client->loginUser($this->getUser('admin@ratp.fr'));

        $signalement = $this->getEntityManager()->getRepository(Signalement::class)->findOneBy(['titre' => 'Signalement public test']);
        self::assertNotNull($signalement);

        $client->request('GET', '/signalement/' . $signalement->getId());

        self::assertResponseIsSuccessful();
        $content = $client->getCrawler()->filter('body')->text();
        self::assertStringContainsString('À identifier', $content);
        self::assertStringContainsString('Conducteur ligne 13', $content);
    }

    public function testDashboardAndListingIncludePublicSignalements(): void
    {
        $client = static::createClient();
        $client->loginUser($this->getUser('admin@ratp.fr'));

        $client->request('GET', '/');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Signalement public test', $client->getCrawler()->filter('body')->text());

        $client->request('GET', '/signalement/');
        self::assertResponseIsSuccessful();
        $content = $client->getCrawler()->filter('body')->text();
        self::assertStringContainsString('Signalement public test', $content);
        self::assertStringContainsString('À identifier', $content);
    }

    public function testSignalementListingIsPaginated(): void
    {
        $client = static::createClient();
        $em = $this->getEntityManager();
        $admin = $this->getUser('admin@ratp.fr');

        for ($i = 1; $i <= 12; $i++) {
            $label = sprintf('%02d', $i);
            $signalement = (new Signalement())
                ->setAgent(null)
                ->setAgentDescription('Agent pagination ' . $i)
                ->setType('incident')
                ->setCanal('formulaire')
                ->setTitre('Pagination test ' . $label)
                ->setDescription('Signalement pour tester la pagination.')
                ->setDateFait(new \DateTimeImmutable(sprintf('-%d hours', $i)))
                ->setGravite('faible')
                ->setStatut('nouveau')
                ->setCreatedBy($admin);
            $em->persist($signalement);
        }
        $em->flush();

        $client->loginUser($admin);

        $client->request('GET', '/signalement/');
        self::assertResponseIsSuccessful();
        $pageOne = $client->getCrawler()->filter('body')->text();
        self::assertStringContainsString('Page 1/2', $pageOne);
        self::assertStringContainsString('Pagination test 01', $pageOne);
        self::assertStringNotContainsString('Pagination test 12', $pageOne);

        $client->request('GET', '/signalement/?page=2');
        self::assertResponseIsSuccessful();
        $pageTwo = $client->getCrawler()->filter('body')->text();
        self::assertStringContainsString('Page 2/2', $pageTwo);
        self::assertStringContainsString('Pagination test 12', $pageTwo);
        self::assertStringNotContainsString('Pagination test 01', $pageTwo);
    }

    public function testValidatedCourrierCanProgressThroughMockMailevaStatuses(): void
    {
        $client = static::createClient();
        $client->loginUser($this->getUser('rh1@ratp.fr'));

        $courrier = $this->getEntityManager()->getRepository(\App\Entity\CourrierDraft::class)->findOneBy([]);
        self::assertNotNull($courrier);

        $client->request('POST', '/courrier/' . $courrier->getId() . '/send');
        self::assertResponseRedirects('/courrier/' . $courrier->getId(), Response::HTTP_FOUND);
        $client->followRedirect();
        self::assertStringContainsString('Envoyé', $client->getCrawler()->filter('body')->text());

        $client->request('POST', '/courrier/' . $courrier->getId() . '/sync-delivery');
        self::assertResponseRedirects('/courrier/' . $courrier->getId(), Response::HTTP_FOUND);
        $client->followRedirect();
        self::assertStringContainsString('Distribué / mis à disposition', $client->getCrawler()->filter('body')->text());

        $client->request('POST', '/courrier/' . $courrier->getId() . '/sync-delivery');
        self::assertResponseRedirects('/courrier/' . $courrier->getId(), Response::HTTP_FOUND);
        $client->followRedirect();
        self::assertStringContainsString('Réceptionné', $client->getCrawler()->filter('body')->text());
    }

    public function testRhCanAccessCourrierQueueFromDashboardAndSidebar(): void
    {
        $client = static::createClient();
        $client->loginUser($this->getUser('rh1@ratp.fr'));

        $client->request('GET', '/');
        self::assertResponseIsSuccessful();
        $content = $client->getCrawler()->filter('body')->text();
        self::assertStringContainsString('Courriers RH', $content);
        self::assertStringContainsString('en validation', mb_strtolower($content));

        $client->request('GET', '/courrier/rh');
        self::assertResponseIsSuccessful();
        $content = $client->getCrawler()->filter('body')->text();
        self::assertStringContainsString('Courriers en validation', $content);
        self::assertStringContainsString('Courriers déjà validés', $content);
        self::assertStringContainsString('Signalement agent test', $content);
    }

    public function testCourrierMovesFromValidationQueueToValidatedQueue(): void
    {
        $managerClient = static::createClient();
        $managerClient->loginUser($this->getUser('manager1@ratp.fr'));

        $draft = $this->getEntityManager()->getRepository(\App\Entity\CourrierDraft::class)->findOneBy(['statut' => 'brouillon']);
        self::assertNotNull($draft);

        $managerClient->request('POST', '/courrier/' . $draft->getId() . '/request-validation');
        self::assertResponseRedirects('/signalement/' . $draft->getSignalement()->getId(), Response::HTTP_FOUND);

        $rhClient = static::createClient();
        $rhClient->loginUser($this->getUser('rh1@ratp.fr'));

        $rhClient->request('GET', '/courrier/rh');
        self::assertResponseIsSuccessful();
        $content = $rhClient->getCrawler()->filter('body')->text();
        self::assertStringContainsString('Courrier de test à valider', $content);
        self::assertStringContainsString('Courriers en validation', $content);

        $rhClient->request('POST', '/courrier/' . $draft->getId() . '/validate');
        self::assertResponseRedirects('/courrier/' . $draft->getId(), Response::HTTP_FOUND);

        $rhClient->request('GET', '/courrier/rh');
        self::assertResponseIsSuccessful();
        $content = $rhClient->getCrawler()->filter('body')->text();
        self::assertStringContainsString('Courrier de test à valider', $content);
        self::assertStringContainsString('Courriers déjà validés', $content);

        $updatedDraft = $this->getEntityManager()->getRepository(\App\Entity\CourrierDraft::class)->find($draft->getId());
        self::assertSame('valide', $updatedDraft?->getStatut());
    }

    public function testSocialInboxImportCreatesSignalement(): void
    {
        $client = static::createClient();
        $client->loginUser($this->getUser('admin@ratp.fr'));

        $client->request('POST', '/social/import/ig-002');
        self::assertResponseRedirects();
        $client->followRedirect();

        $content = $client->getCrawler()->filter('body')->text();
        self::assertStringContainsString('Instagram', $content);
        self::assertStringContainsString('Traduction simulée', $content);
    }

    public function testComplaintProofCanBeSubmittedOnSignalement(): void
    {
        $client = static::createClient();
        $client->loginUser($this->getUser('admin@ratp.fr'));
        $signalement = $this->getEntityManager()->getRepository(Signalement::class)->findOneBy(['titre' => 'Signalement agent test']);
        self::assertNotNull($signalement);

        $client->request('GET', '/signalement/' . $signalement->getId());
        self::assertResponseIsSuccessful();
        $token = $client->getCrawler()
            ->filter(sprintf('form[action="/signalement/%d/complaint-proof"] input[name="_token"]', $signalement->getId()))
            ->attr('value');
        self::assertNotNull($token);

        $filepath = $this->uploadDir . '/complaint-proof.pdf';
        file_put_contents($filepath, 'preuve de plainte');
        $uploadedFile = new UploadedFile($filepath, 'complaint-proof.pdf', 'application/pdf', null, true);

        $client->request(
            'POST',
            '/signalement/' . $signalement->getId() . '/complaint-proof',
            [
                '_token' => $token,
                'plainte_commentaire' => 'Plainte déposée au commissariat central.',
            ],
            [
                'plainte_proof' => $uploadedFile,
            ]
        );

        self::assertResponseRedirects('/signalement/' . $signalement->getId(), Response::HTTP_FOUND);
        $client->followRedirect();
        self::assertStringContainsString('Dépôt de plainte enregistré', $client->getCrawler()->filter('body')->text());
    }

    private function resetSchema(): void
    {
        $em = $this->getEntityManager();
        $metadata = $em->getMetadataFactory()->getAllMetadata();
        $schemaTool = new SchemaTool($em);
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);
    }

    private function resetUploadDirectory(): void
    {
        if (is_dir($this->uploadDir)) {
            foreach (glob($this->uploadDir . '/*') ?: [] as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        } else {
            mkdir($this->uploadDir, 0775, true);
        }
    }

    private function seedData(): void
    {
        $em = $this->getEntityManager();

        $admin = (new User())
            ->setEmail('admin@ratp.fr')
            ->setRoles(['ROLE_ADMIN'])
            ->setPassword('test-password')
            ->setNom('Dupont')
            ->setPrenom('Sophie')
            ->setActif(true);
        $em->persist($admin);

        $rh = (new User())
            ->setEmail('rh1@ratp.fr')
            ->setRoles(['ROLE_RH'])
            ->setPassword('test-password')
            ->setNom('Bernard')
            ->setPrenom('Helene')
            ->setActif(true);
        $em->persist($rh);

        $manager = (new User())
            ->setEmail('manager1@ratp.fr')
            ->setRoles(['ROLE_MANAGER'])
            ->setPassword('test-password')
            ->setNom('Martin')
            ->setPrenom('Paul')
            ->setActif(true);
        $em->persist($manager);

        $agent = (new Agent())
            ->setMatricule('RAT001')
            ->setNom('Martin')
            ->setPrenom('Paul')
            ->setCentre('Centre A')
            ->setDateNaissance(new \DateTimeImmutable('1985-01-01'))
            ->setActif(true);
        $em->persist($agent);

        $incident = (new Signalement())
            ->setAgent($agent)
            ->setType('incident')
            ->setCanal('terrain')
            ->setTitre('Signalement agent test')
            ->setDescription('Signalement interne pour alimenter le dashboard.')
            ->setDateFait(new \DateTimeImmutable('-2 days'))
            ->setGravite('grave')
            ->setStatut('nouveau')
            ->setCreatedBy($admin);
        $em->persist($incident);

        $public = (new Signalement())
            ->setAgent(null)
            ->setAgentDescription('Conducteur ligne 13, veste sombre')
            ->setType('incident')
            ->setCanal('formulaire')
            ->setTitre('Signalement public test')
            ->setDescription('Signalement public sans agent lié.')
            ->setDateFait(new \DateTimeImmutable('-1 day'))
            ->setGravite('moyen')
            ->setStatut('nouveau')
            ->setCreatedBy($admin);
        $em->persist($public);

        $historique = (new HistoriqueStatut())
            ->setSignalement($public)
            ->setUser($admin)
            ->setAncienStatut(null)
            ->setNouveauStatut('nouveau')
            ->setCommentaire('Signalement reçu via formulaire site');
        $em->persist($historique);

        $courrier = (new \App\Entity\CourrierDraft())
            ->setSignalement($incident)
            ->setContenu('Courrier validé de test.')
            ->setStatut('valide')
            ->setValidatedBy($rh);
        $em->persist($courrier);

        $draftCourrier = (new \App\Entity\CourrierDraft())
            ->setSignalement($incident)
            ->setContenu('Courrier de test à valider.')
            ->setStatut('brouillon');
        $em->persist($draftCourrier);

        $em->flush();

        $filepath = $this->uploadDir . '/public-proof.pdf';
        file_put_contents($filepath, 'preuve publique');

        $piece = (new PieceJointe())
            ->setSignalement($public)
            ->setFilename('public-proof.pdf')
            ->setOriginalName('public-proof.pdf')
            ->setMimeType('application/pdf')
            ->setSize(filesize($filepath) ?: 0)
            ->setVisibility(PieceJointe::VISIBILITY_PUBLIC)
            ->setCategory('public_submission')
            ->setUploadedBy(null);
        $em->persist($piece);
        $em->flush();
    }

    private function getUser(string $email): User
    {
        $user = $this->getEntityManager()->getRepository(User::class)->findOneBy(['email' => $email]);
        self::assertNotNull($user);

        return $user;
    }

    private function getEntityManager(): EntityManagerInterface
    {
        return static::getContainer()->get(EntityManagerInterface::class);
    }
}
