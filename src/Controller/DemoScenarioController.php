<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/public/demo')]
class DemoScenarioController extends AbstractController
{
    #[Route('', name: 'app_public_demo', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('public/demo.html.twig', [
            'accounts' => [
                ['email' => 'admin@ratp.fr', 'password' => 'Admin1234!', 'role' => 'Admin'],
                ['email' => 'manager1@ratp.fr', 'password' => 'Manager1!', 'role' => 'Manager'],
                ['email' => 'rh1@ratp.fr', 'password' => 'Rh12345!', 'role' => 'RH'],
                ['email' => 'devadmin@ratp.fr', 'password' => 'DevAdmin1!', 'role' => 'Admin développeur'],
            ],
        ]);
    }
}
