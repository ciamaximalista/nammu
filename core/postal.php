<?php

declare(strict_types=1);

function postal_data_file(): string
{
    return dirname(__DIR__) . '/config/postal-subscribers.json';
}

function postal_load_entries(): array
{
    $file = postal_data_file();
    if (!is_file($file)) {
        return [];
    }
    $raw = file_get_contents($file);
    if ($raw === false || $raw === '') {
        return [];
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function postal_save_entries(array $entries): void
{
    $file = postal_data_file();
    $dir = dirname($file);
    nammu_ensure_directory($dir, 0775);
    $payload = json_encode($entries, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($payload === false) {
        throw new RuntimeException('No se pudo serializar la libreta postal.');
    }
    file_put_contents($file, $payload, LOCK_EX);
    @chmod($file, 0664);
}

function postal_normalize_email(string $email): string
{
    return strtolower(trim($email));
}

function postal_get_entry(string $email, array $entries): ?array
{
    $key = postal_normalize_email($email);
    if ($key !== '' && isset($entries[$key])) {
        return $entries[$key];
    }
    if ($key === '') {
        return null;
    }
    foreach ($entries as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        if (postal_normalize_email((string) ($entry['email'] ?? '')) === $key) {
            return $entry;
        }
    }
    return null;
}

function postal_upsert_entry(array $data, ?string $passwordHash, array $entries): array
{
    $email = postal_normalize_email((string) ($data['email'] ?? ''));
    $key = $email;
    if ($key === '') {
        $key = trim((string) ($data['id'] ?? ''));
        if ($key === '') {
            $key = 'id-' . bin2hex(random_bytes(6));
        }
    }
    $current = $entries[$key] ?? null;
    $now = date('c');
    $entryId = $current['id'] ?? '';
    if ($entryId === '') {
        $entryId = trim((string) ($data['id'] ?? ''));
    }
    if ($entryId === '' && $email === '') {
        $entryId = $key;
    }
    $entries[$key] = [
        'id' => $entryId,
        'email' => $email,
        'name' => trim((string) ($data['name'] ?? '')),
        'address' => trim((string) ($data['address'] ?? '')),
        'city' => trim((string) ($data['city'] ?? '')),
        'postal_code' => trim((string) ($data['postal_code'] ?? '')),
        'region' => trim((string) ($data['region'] ?? '')),
        'country' => trim((string) ($data['country'] ?? '')),
        'password_hash' => $passwordHash ?? ($current['password_hash'] ?? ''),
        'created_at' => $current['created_at'] ?? $now,
        'updated_at' => $now,
    ];
    return $entries;
}

function postal_delete_entry(string $email, array $entries): array
{
    $key = postal_normalize_email($email);
    if (isset($entries[$key])) {
        unset($entries[$key]);
        return $entries;
    }
    if ($key === '') {
        return $entries;
    }
    foreach ($entries as $entryKey => $entry) {
        if (!is_array($entry)) {
            continue;
        }
        if (postal_normalize_email((string) ($entry['email'] ?? '')) === $key) {
            unset($entries[$entryKey]);
            break;
        }
    }
    return $entries;
}

function postal_csv_export(array $entries): string
{
    $output = fopen('php://temp', 'r+');
    fputcsv($output, ['Email', 'Nombre', 'Direccion', 'Poblacion', 'Codigo Postal', 'Provincia/Region', 'Pais']);
    foreach ($entries as $entry) {
        fputcsv($output, [
            $entry['email'] ?? '',
            $entry['name'] ?? '',
            $entry['address'] ?? '',
            $entry['city'] ?? '',
            $entry['postal_code'] ?? '',
            $entry['region'] ?? '',
            $entry['country'] ?? '',
        ]);
    }
    rewind($output);
    $csv = stream_get_contents($output) ?: '';
    fclose($output);
    return $csv;
}

function postal_reset_data_file(): string
{
    return dirname(__DIR__) . '/config/postal-reset.json';
}

function postal_load_reset_tokens(): array
{
    $file = postal_reset_data_file();
    if (!is_file($file)) {
        return [];
    }
    $raw = file_get_contents($file);
    if ($raw === false || $raw === '') {
        return [];
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function postal_save_reset_tokens(array $tokens): void
{
    $file = postal_reset_data_file();
    $dir = dirname($file);
    nammu_ensure_directory($dir, 0775);
    $payload = json_encode(array_values($tokens), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($payload === false) {
        throw new RuntimeException('No se pudo serializar los tokens de reset.');
    }
    file_put_contents($file, $payload, LOCK_EX);
    @chmod($file, 0664);
}

function postal_prune_reset_tokens(array $tokens): array
{
    $now = time();
    return array_values(array_filter($tokens, static function ($item) use ($now) {
        if (!is_array($item)) {
            return false;
        }
        $expires = $item['expires_at'] ?? 0;
        return is_numeric($expires) && (int) $expires > $now;
    }));
}

function postal_pick_pdf_font(string $fontName): string
{
    $normalized = strtolower($fontName);
    if (str_contains($normalized, 'mono') || str_contains($normalized, 'code')) {
        return 'Courier';
    }
    if (str_contains($normalized, 'serif') || str_contains($normalized, 'times') || str_contains($normalized, 'garamond')) {
        return 'Times-Roman';
    }
    return 'Helvetica';
}

function postal_pdf_escape(string $text): string
{
    $text = postal_pdf_normalize_text($text);
    $text = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
    return str_replace(["\r", "\n"], [' ', ' '], $text);
}

function postal_pdf_normalize_text(string $text): string
{
    $replacements = [
        'á' => 'a', 'à' => 'a', 'ä' => 'a', 'â' => 'a', 'ã' => 'a', 'å' => 'a',
        'Á' => 'A', 'À' => 'A', 'Ä' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Å' => 'A',
        'é' => 'e', 'è' => 'e', 'ë' => 'e', 'ê' => 'e',
        'É' => 'E', 'È' => 'E', 'Ë' => 'E', 'Ê' => 'E',
        'í' => 'i', 'ì' => 'i', 'ï' => 'i', 'î' => 'i',
        'Í' => 'I', 'Ì' => 'I', 'Ï' => 'I', 'Î' => 'I',
        'ó' => 'o', 'ò' => 'o', 'ö' => 'o', 'ô' => 'o', 'õ' => 'o',
        'Ó' => 'O', 'Ò' => 'O', 'Ö' => 'O', 'Ô' => 'O', 'Õ' => 'O',
        'ú' => 'u', 'ù' => 'u', 'ü' => 'u', 'û' => 'u',
        'Ú' => 'U', 'Ù' => 'U', 'Ü' => 'U', 'Û' => 'U',
        'ñ' => 'n', 'Ñ' => 'N',
        'ç' => 'c', 'Ç' => 'C',
        '¿' => '?', '¡' => '!',
    ];
    return strtr($text, $replacements);
}

function postal_wrap_label_lines(string $text, int $maxChars): array
{
    $text = trim($text);
    if ($text === '') {
        return [];
    }
    if ($maxChars < 5) {
        return [$text];
    }
    $words = preg_split('/\s+/u', $text) ?: [];
    $lines = [];
    $current = '';
    foreach ($words as $word) {
        if ($current === '') {
            $current = $word;
            continue;
        }
        if (mb_strlen($current . ' ' . $word, 'UTF-8') <= $maxChars) {
            $current .= ' ' . $word;
            continue;
        }
        $lines[] = $current;
        $current = $word;
    }
    if ($current !== '') {
        $lines[] = $current;
    }
    return $lines;
}

function postal_build_labels_pdf(array $entries, string $fontName): string
{
    $entries = array_values($entries);
    $font = postal_pick_pdf_font($fontName);
    $pageWidth = 595.28;
    $pageHeight = 841.89;
    $marginX = 36;
    $marginY = 36;
    $cols = 3;
    $rows = 8;
    $labelWidth = ($pageWidth - ($marginX * 2)) / $cols;
    $labelHeight = ($pageHeight - ($marginY * 2)) / $rows;
    $fontSize = 10;
    $lineHeight = 12;
    $perPage = $cols * $rows;
    $textWidth = $labelWidth - 12;
    $charsPerLine = (int) floor($textWidth / ($fontSize * 0.55));
    $maxLines = (int) floor(($labelHeight - 20) / $lineHeight);

    $pages = [];
    for ($offset = 0; $offset < count($entries); $offset += $perPage) {
        $chunk = array_slice($entries, $offset, $perPage);
        $stream = [];
        $stream[] = 'q';
        $stream[] = '0.85 0.85 0.85 RG';
        $stream[] = '0.3 w';
        for ($row = 0; $row < $rows; $row++) {
            for ($col = 0; $col < $cols; $col++) {
                $x = $marginX + ($col * $labelWidth);
                $y = $pageHeight - $marginY - (($row + 1) * $labelHeight);
                $stream[] = sprintf('%.2f %.2f %.2f %.2f re S', $x, $y, $labelWidth, $labelHeight);
            }
        }
        $stream[] = 'Q';
        $stream[] = 'BT';
        $stream[] = '/F1 ' . $fontSize . ' Tf';
        foreach ($chunk as $index => $entry) {
            $col = $index % $cols;
            $row = (int) floor($index / $cols);
            $x = $marginX + ($col * $labelWidth) + 6;
            $yTop = $pageHeight - $marginY - ($row * $labelHeight) - 16;
            $rawLines = [
                $entry['name'] ?? '',
                $entry['address'] ?? '',
                trim(($entry['postal_code'] ?? '') . ' ' . ($entry['city'] ?? '')),
                $entry['region'] ?? '',
                $entry['country'] ?? '',
            ];
            $lines = [];
            foreach ($rawLines as $line) {
                $wrapped = postal_wrap_label_lines((string) $line, $charsPerLine);
                foreach ($wrapped as $wrappedLine) {
                    $lines[] = $wrappedLine;
                }
            }
            $lineOffset = 0;
            foreach ($lines as $lineText) {
                if ($lineOffset >= $maxLines) {
                    break;
                }
                $lineText = trim((string) $lineText);
                if ($lineText === '') {
                    continue;
                }
                $y = $yTop - ($lineOffset * $lineHeight);
                $stream[] = sprintf('1 0 0 1 %.2f %.2f Tm', $x, $y);
                $stream[] = '(' . postal_pdf_escape($lineText) . ') Tj';
                $lineOffset++;
            }
        }
        $stream[] = 'ET';
        $pages[] = implode("\n", $stream);
    }

    if (empty($pages)) {
        $pages[] = "BT\n/F1 {$fontSize} Tf\n1 0 0 1 50 700 Tm\n(Sin datos) Tj\nET";
    }

    $objects = [];
    $pageCount = count($pages);
    $catalogNum = 1;
    $pagesNum = 2;
    $pageStartNum = 3;
    $fontNum = $pageStartNum + $pageCount;
    $contentStartNum = $fontNum + 1;
    $objectIndex = $contentStartNum + $pageCount - 1;

    $objects[$catalogNum] = "<< /Type /Catalog /Pages {$pagesNum} 0 R >>";

    $pageRefs = [];
    for ($i = 0; $i < $pageCount; $i++) {
        $pageRefs[] = ($pageStartNum + $i) . ' 0 R';
    }
    $objects[$pagesNum] = "<< /Type /Pages /Kids [ " . implode(' ', $pageRefs) . " ] /Count {$pageCount} >>";

    for ($i = 0; $i < $pageCount; $i++) {
        $pageNum = $pageStartNum + $i;
        $contentNum = $contentStartNum + $i;
        $objects[$pageNum] = "<< /Type /Page /Parent {$pagesNum} 0 R /MediaBox [0 0 {$pageWidth} {$pageHeight}] /Resources << /Font << /F1 {$fontNum} 0 R >> >> /Contents {$contentNum} 0 R >>";
    }

    $objects[$fontNum] = "<< /Type /Font /Subtype /Type1 /BaseFont /{$font} >>";

    for ($i = 0; $i < $pageCount; $i++) {
        $contentNum = $contentStartNum + $i;
        $stream = $pages[$i];
        $objects[$contentNum] = "<< /Length " . strlen($stream) . " >>\nstream\n{$stream}\nendstream";
    }

    $xref = [];
    $pdf = "%PDF-1.4\n";
    $xref[] = '0000000000 65535 f ';
    for ($i = 1; $i <= $objectIndex; $i++) {
        $xref[] = sprintf('%010d 00000 n ', strlen($pdf));
        $pdf .= "{$i} 0 obj\n" . ($objects[$i] ?? '') . "\nendobj\n";
    }
    $xrefOffset = strlen($pdf);
    $pdf .= "xref\n0 " . ($objectIndex + 1) . "\n" . implode("\n", $xref) . "\n";
    $pdf .= "trailer\n<< /Size " . ($objectIndex + 1) . " /Root {$catalogNum} 0 R >>\n";
    $pdf .= "startxref\n{$xrefOffset}\n%%EOF";
    return $pdf;
}
