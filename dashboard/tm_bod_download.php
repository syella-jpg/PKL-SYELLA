<?php
// Edit only text paragraphs; retain all masters, artwork, styles and embedded assets.
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit;
}
$temp = null;
try {
    $input = json_decode(file_get_contents('php://input'), true, 32, JSON_THROW_ON_ERROR);
    $slides = $input['slides'] ?? null;
    if (!is_array($slides) || count($slides) !== 7) throw new InvalidArgumentException('Isi tujuh slide diperlukan.');
    $temp = tempnam(sys_get_temp_dir(), 'tm-bod-');
    if (!$temp || !copy(__DIR__ . '/assets/tm-bod/template.odp', $temp)) throw new RuntimeException('Template tidak tersedia.');
    $zip = new ZipArchive();
    if ($zip->open($temp) !== true) throw new RuntimeException('Template gagal dibuka.');
    $doc = new DOMDocument();
    if (!$doc->loadXML($zip->getFromName('content.xml'), LIBXML_NONET)) throw new RuntimeException('Template tidak valid.');
    $xp = new DOMXPath($doc);
    $xp->registerNamespace('draw', 'urn:oasis:names:tc:opendocument:xmlns:drawing:1.0');
    $xp->registerNamespace('text', 'urn:oasis:names:tc:opendocument:xmlns:text:1.0');
    $pages = $xp->query('//draw:page');
    foreach ($pages as $i => $page) {
        $paragraphs = $xp->query('./draw:custom-shape//text:p', $page);
        $values = $slides[$i]['fields'] ?? null;
        if (!is_array($values) || count($values) !== $paragraphs->length) throw new InvalidArgumentException('Jumlah bidang slide tidak sesuai template.');
        foreach ($paragraphs as $k => $paragraph) {
            $value = $values[$k];
            if (!is_string($value) || strlen($value) > 12000) throw new InvalidArgumentException('Teks slide terlalu panjang atau tidak valid.');
            if ($value === $paragraph->textContent) continue;
            $span = $xp->query('.//text:span', $paragraph)->item(0);
            $target = $span ? $span->cloneNode(false) : $paragraph;
            while ($paragraph->firstChild) $paragraph->removeChild($paragraph->firstChild);
            if ($span) $paragraph->appendChild($target);
            foreach (explode("\n", str_replace(["\r\n", "\r"], "\n", $value)) as $lineIndex => $line) {
                if ($lineIndex) $target->appendChild($doc->createElementNS('urn:oasis:names:tc:opendocument:xmlns:text:1.0', 'text:line-break'));
                // Explicit spaces preserve indentation and repeated spaces in ODF readers.
                foreach (preg_split('/( +)/u', $line, -1, PREG_SPLIT_DELIM_CAPTURE) as $part) {
                    if ($part !== '' && $part[0] === ' ') {
                        $space = $doc->createElementNS('urn:oasis:names:tc:opendocument:xmlns:text:1.0', 'text:s');
                        $space->setAttributeNS('urn:oasis:names:tc:opendocument:xmlns:text:1.0', 'text:c', (string)strlen($part));
                        $target->appendChild($space);
                    } else $target->appendChild($doc->createTextNode($part));
                }
            }
        }
    }
    $zip->addFromString('content.xml', $doc->saveXML());
    $zip->close();
    header('Content-Type: application/vnd.oasis.opendocument.presentation');
    header('Content-Disposition: attachment; filename="TM-BOD.odp"');
    header('Cache-Control: no-store');
    readfile($temp);
} catch (Throwable $e) {
    http_response_code($e instanceof InvalidArgumentException || $e instanceof JsonException ? 400 : 500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['message' => $e->getMessage()]);
} finally {
    if ($temp && is_file($temp)) unlink($temp);
}
