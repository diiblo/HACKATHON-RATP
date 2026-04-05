<?php

namespace App\Controller;

use App\Entity\CommentaireSignalement;
use App\Entity\Signalement;
use App\Form\CommentaireType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class CommentaireController extends AbstractController
{
    #[Route('/signalement/{id}/commentaire', name: 'app_commentaire_add', methods: ['POST'])]
    public function add(Signalement $signalement, Request $request, EntityManagerInterface $em): Response
    {
        $commentaire = new CommentaireSignalement();
        $form = $this->createForm(CommentaireType::class, $commentaire);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $commentaire->setSignalement($signalement);
            $commentaire->setUser($this->getUser());
            $em->persist($commentaire);
            $em->flush();
            $this->addFlash('success', 'Commentaire ajouté.');
        } else {
            $this->addFlash('error', 'Le commentaire est invalide.');
        }

        return $this->redirectToRoute('app_signalement_show', ['id' => $signalement->getId()]);
    }
}
