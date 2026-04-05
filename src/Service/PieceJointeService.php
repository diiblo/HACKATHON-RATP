<?php

namespace App\Service;

use App\Entity\PieceJointe;
use App\Entity\Signalement;
use App\Entity\User;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class PieceJointeService
{
    public function __construct(private readonly string $uploadDir) {}

    public function getUploadDir(): string
    {
        return $this->uploadDir;
    }

    /**
     * Traite un fichier uploadé, crée et retourne une PieceJointe prête à persister.
     * Ne persiste pas — c'est au contrôleur de le faire.
     */
    public function handleUpload(
        UploadedFile $file,
        Signalement $signalement,
        ?User $uploadedBy = null,
        string $visibility = PieceJointe::VISIBILITY_INTERNAL,
        string $category = 'document'
    ): PieceJointe {
        // Vérification taille
        if ($file->getSize() > PieceJointe::TAILLE_MAX) {
            throw new \RuntimeException('Fichier trop volumineux (max 10 Mo).');
        }

        // Vérification extension
        $extension = strtolower($file->getClientOriginalExtension());
        if (!in_array($extension, PieceJointe::EXTENSIONS_AUTORISEES)) {
            throw new \RuntimeException(sprintf(
                'Extension non autorisée : .%s. Extensions acceptées : %s.',
                $extension,
                implode(', ', PieceJointe::EXTENSIONS_AUTORISEES)
            ));
        }

        // Génère un nom unique
        $filename = bin2hex(random_bytes(16)) . '.' . $extension;
        $originalName = $file->getClientOriginalName();
        $mimeType = $file->getClientMimeType() ?? 'application/octet-stream';
        $size = $file->getSize() ?: 0;

        // Crée le répertoire si nécessaire
        if (!is_dir($this->uploadDir)) {
            mkdir($this->uploadDir, 0775, true);
        }

        // Déplace le fichier
        $file->move($this->uploadDir, $filename);

        $piece = new PieceJointe();
        $piece->setSignalement($signalement)
            ->setFilename($filename)
            ->setOriginalName($originalName)
            ->setMimeType($mimeType)
            ->setSize($size)
            ->setVisibility($visibility)
            ->setCategory($category)
            ->setUploadedBy($uploadedBy);

        return $piece;
    }

    public function deleteFile(PieceJointe $piece): void
    {
        $path = $this->uploadDir . '/' . $piece->getFilename();
        if (file_exists($path)) {
            unlink($path);
        }
    }
}
