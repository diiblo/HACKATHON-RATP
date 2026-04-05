<?php

namespace App\Service;

class PdfGenerator
{
    private const PAGE_WIDTH = 595;
    private const PAGE_HEIGHT = 842;
    private const MARGIN_LEFT = 50;
    private const MARGIN_TOP = 800;
    private const MARGIN_BOTTOM = 60;
    private const BODY_FONT_SIZE = 12;
    private const TITLE_FONT_SIZE = 15;
    private const LINE_HEIGHT = 16;
    private const MAX_CHARS_PER_LINE = 88;

    public function generateFromText(string $title, string $content): string
    {
        $pages = $this->paginate($title, $content);

        $objects = [];
        $catalogObjectId = 1;
        $pagesObjectId = 2;
        $bodyFontObjectId = 3;
        $titleFontObjectId = 4;
        $nextObjectId = 5;
        $pageObjectIds = [];

        $objects[$catalogObjectId] = "<< /Type /Catalog /Pages {$pagesObjectId} 0 R >>";
        $objects[$bodyFontObjectId] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>";
        $objects[$titleFontObjectId] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>";

        foreach ($pages as $stream) {
            $pageObjectId = $nextObjectId++;
            $contentObjectId = $nextObjectId++;
            $pageObjectIds[] = $pageObjectId;

            $objects[$pageObjectId] = sprintf(
                "<< /Type /Page /Parent %d 0 R /MediaBox [0 0 %d %d] /Resources << /Font << /F1 %d 0 R /F2 %d 0 R >> >> /Contents %d 0 R >>",
                $pagesObjectId,
                self::PAGE_WIDTH,
                self::PAGE_HEIGHT,
                $bodyFontObjectId,
                $titleFontObjectId,
                $contentObjectId
            );
            $objects[$contentObjectId] = sprintf("<< /Length %d >>\nstream\n%s\nendstream", strlen($stream), $stream);
        }

        $kids = implode(' ', array_map(static fn (int $id) => sprintf('%d 0 R', $id), $pageObjectIds));
        $objects[$pagesObjectId] = sprintf(
            "<< /Type /Pages /Kids [%s] /Count %d >>",
            $kids,
            count($pageObjectIds)
        );

        ksort($objects);

        $pdf = "%PDF-1.4\n";
        $offsets = [0];

        foreach ($objects as $id => $object) {
            $offsets[$id] = strlen($pdf);
            $pdf .= sprintf("%d 0 obj\n%s\nendobj\n", $id, $object);
        }

        $xrefOffset = strlen($pdf);
        $size = max(array_keys($objects)) + 1;
        $pdf .= sprintf("xref\n0 %d\n", $size);
        $pdf .= "0000000000 65535 f \n";

        for ($i = 1; $i < $size; $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i] ?? 0);
        }

        $pdf .= sprintf("trailer\n<< /Size %d /Root %d 0 R >>\nstartxref\n%d\n%%%%EOF", $size, $catalogObjectId, $xrefOffset);

        return $pdf;
    }

    /**
     * @return list<string>
     */
    private function paginate(string $title, string $content): array
    {
        $pages = [];
        $stream = "BT\n";
        $y = self::MARGIN_TOP;

        $stream .= sprintf("/F2 %d Tf\n", self::TITLE_FONT_SIZE);
        $stream .= $this->writeLine($title, self::MARGIN_LEFT, $y);
        $y -= self::LINE_HEIGHT * 2;

        $stream .= sprintf("/F1 %d Tf\n", self::BODY_FONT_SIZE);
        foreach ($this->wrapParagraphs($content) as $line) {
            if ($y < self::MARGIN_BOTTOM) {
                $stream .= "ET";
                $pages[] = $stream;
                $stream = "BT\n";
                $y = self::MARGIN_TOP;
                $stream .= sprintf("/F2 %d Tf\n", self::TITLE_FONT_SIZE);
                $stream .= $this->writeLine($title . ' (suite)', self::MARGIN_LEFT, $y);
                $y -= self::LINE_HEIGHT * 2;
                $stream .= sprintf("/F1 %d Tf\n", self::BODY_FONT_SIZE);
            }

            $stream .= $this->writeLine($line, self::MARGIN_LEFT, $y);
            $y -= self::LINE_HEIGHT;
        }

        $stream .= "ET";
        $pages[] = $stream;

        return $pages;
    }

    /**
     * @return list<string>
     */
    private function wrapParagraphs(string $content): array
    {
        $paragraphs = preg_split('/\R/u', $content) ?: [];
        $lines = [];

        foreach ($paragraphs as $paragraph) {
            $normalized = trim(preg_replace('/\s+/u', ' ', $paragraph) ?? '');
            if ($normalized === '') {
                $lines[] = '';
                continue;
            }

            $current = '';
            foreach (preg_split('/\s+/u', $normalized) ?: [] as $word) {
                $candidate = $current === '' ? $word : $current . ' ' . $word;
                if (mb_strlen($candidate) <= self::MAX_CHARS_PER_LINE) {
                    $current = $candidate;
                    continue;
                }

                if ($current !== '') {
                    $lines[] = $current;
                    $current = '';
                }

                while (mb_strlen($word) > self::MAX_CHARS_PER_LINE) {
                    $lines[] = mb_substr($word, 0, self::MAX_CHARS_PER_LINE - 1) . '-';
                    $word = mb_substr($word, self::MAX_CHARS_PER_LINE - 1);
                }
                $current = $word;
            }

            if ($current !== '') {
                $lines[] = $current;
            }
        }

        return $lines === [] ? [''] : $lines;
    }

    private function writeLine(string $text, int $x, int $y): string
    {
        return sprintf("1 0 0 1 %d %d Tm (%s) Tj\n", $x, $y, $this->escapePdfText($text));
    }

    private function escapePdfText(string $text): string
    {
        $text = str_replace(["\r", "\n"], ' ', $text);
        $text = iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $text);
        if ($text === false) {
            $text = preg_replace('/[^\x20-\x7E]/', '?', $text) ?? '';
        }

        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
    }
}
