<?php

namespace App\Controller;

use App\Entity\PieceJointe;
use App\Repository\PieceJointeRepository;
use App\Service\PieceJointeService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/piece-jointe')]
class PieceJointeController extends AbstractController
{
    #[Route('/{id}/download', name: 'app_piece_jointe_download', methods: ['GET'])]
    public function download(PieceJointe $piece, PieceJointeService $service): Response
    {
        if ($piece->getVisibility() === 'restricted' && !$this->isGranted('ROLE_RH') && !$this->isGranted('ROLE_JURIDIQUE')) {
            throw $this->createAccessDeniedException('Accès réservé aux équipes RH et juridique.');
        }
        if ($piece->getVisibility() === 'internal' && !$this->isGranted('ROLE_USER')) {
            throw $this->createAccessDeniedException('Connexion requise.');
        }

        $filepath = $service->getUploadDir() . '/' . $piece->getFilename();

        if (!file_exists($filepath)) {
            throw $this->createNotFoundException('Fichier introuvable.');
        }

        return $this->file(
            $filepath,
            $piece->getOriginalName(),
            ResponseHeaderBag::DISPOSITION_INLINE
        );
    }

    #[Route('/{id}/delete', name: 'app_piece_jointe_delete', methods: ['POST'])]
    #[IsGranted('ROLE_MANAGER')]
    public function delete(
        PieceJointe $piece,
        PieceJointeService $service,
        EntityManagerInterface $em
    ): Response {
        $signalementId = $piece->getSignalement()->getId();
        $service->deleteFile($piece);
        $em->remove($piece);
        $em->flush();

        $this->addFlash('success', 'Pièce jointe supprimée.');
        return $this->redirectToRoute('app_signalement_show', ['id' => $signalementId]);
    }
}
