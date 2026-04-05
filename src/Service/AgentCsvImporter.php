<?php

namespace App\Service;

use App\Entity\Agent;
use App\Repository\AgentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class AgentCsvImporter
{
    public function __construct(
        private readonly AgentRepository $agentRepository,
        private readonly EntityManagerInterface $em,
    ) {}

    public function import(UploadedFile $file): array
    {
        $handle = fopen($file->getPathname(), 'rb');
        if ($handle === false) {
            throw new \RuntimeException('Impossible de lire le fichier CSV.');
        }

        $header = fgetcsv($handle, separator: ';');
        $separator = ';';
        if (is_array($header) && count($header) === 1 && str_contains($header[0], ',')) {
            rewind($handle);
            $header = fgetcsv($handle, separator: ',');
            $separator = ',';
        }
        if (!is_array($header)) {
            fclose($handle);
            throw new \RuntimeException('Fichier CSV vide.');
        }

        $normalizedHeader = array_map($this->normalize(...), $header);
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];
        $line = 1;

        while (($row = fgetcsv($handle, separator: $separator)) !== false) {
            $line++;
            if ($row === [null]) {
                continue;
            }

            $data = array_combine($normalizedHeader, array_pad($row, count($normalizedHeader), null));
            if ($data === false) {
                $skipped++;
                $errors[] = sprintf('Ligne %d ignorée : colonnes incohérentes.', $line);
                continue;
            }

            $matricule = trim((string) ($data['matricule'] ?? ''));
            $nom = trim((string) ($data['nom'] ?? ''));
            $prenom = trim((string) ($data['prenom'] ?? ''));

            if ($matricule === '' || $nom === '' || $prenom === '') {
                $skipped++;
                $errors[] = sprintf('Ligne %d ignorée : matricule/nom/prénom requis.', $line);
                continue;
            }

            $agent = $this->agentRepository->findOneBy(['matricule' => $matricule]) ?? new Agent();
            $isNew = $agent->getId() === null;

            $agent->setMatricule($matricule)
                ->setNom($nom)
                ->setPrenom($prenom)
                ->setCentre($this->nullableString($data['centre'] ?? null))
                ->setActif($this->parseBoolean($data['actif'] ?? '1'));

            $dateNaissance = $this->nullableString($data['date_naissance'] ?? null);
            if ($dateNaissance !== null) {
                try {
                    $agent->setDateNaissance(new \DateTimeImmutable($dateNaissance));
                } catch (\Throwable) {
                    $errors[] = sprintf('Ligne %d : date_naissance invalide, valeur ignorée.', $line);
                }
            }

            $this->em->persist($agent);
            $isNew ? $created++ : $updated++;
        }

        fclose($handle);
        $this->em->flush();

        return [
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
            'errors' => $errors,
        ];
    }

    private function normalize(string $value): string
    {
        $value = preg_replace('/^\xEF\xBB\xBF/', '', $value) ?? $value;
        return strtolower(str_replace([' ', '-'], '_', trim($value)));
    }

    private function nullableString(mixed $value): ?string
    {
        $string = trim((string) $value);
        return $string === '' ? null : $string;
    }

    private function parseBoolean(mixed $value): bool
    {
        $normalized = strtolower(trim((string) $value));
        return !in_array($normalized, ['0', 'false', 'non', 'no', 'inactive', 'inactif'], true);
    }
}
