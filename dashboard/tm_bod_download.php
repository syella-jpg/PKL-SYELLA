<?php
// One analysis per slide; retain the corporate master, logo, and background.
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit;
}
$temp = null;
try {
    $input = json_decode(file_get_contents('php://input'), true, 32, JSON_THROW_ON_ERROR);
    $slides = $input['slides'] ?? null;
    if (!is_array($slides) || !array_is_list($slides) || count($slides) < 1 || count($slides) > 200) throw new InvalidArgumentException('Diperlukan 1 sampai 200 slide analisis.');
    $temp = tempnam(sys_get_temp_dir(), 'tm-bod-');
    if (!$temp || !copy(__DIR__ . '/assets/tm-bod/analysis-template.odp', $temp)) throw new RuntimeException('Template tidak tersedia.');
    $zip = new ZipArchive();
    if ($zip->open($temp) !== true) throw new RuntimeException('Template gagal dibuka.');
    $doc = new DOMDocument();
    if (!$doc->loadXML($zip->getFromName('content.xml'), LIBXML_NONET)) throw new RuntimeException('Template tidak valid.');
    $xp = new DOMXPath($doc);
    $xp->registerNamespace('draw', 'urn:oasis:names:tc:opendocument:xmlns:drawing:1.0');
    $xp->registerNamespace('text', 'urn:oasis:names:tc:opendocument:xmlns:text:1.0');
    $xp->registerNamespace('office', 'urn:oasis:names:tc:opendocument:xmlns:office:1.0');
    $xp->registerNamespace('style', 'urn:oasis:names:tc:opendocument:xmlns:style:1.0');
    $presentation = $xp->query('//office:body/office:presentation')->item(0);
    $base = $xp->query('./draw:page', $presentation)->item(0);
    $styles = $xp->query('//office:automatic-styles')->item(0);
    $manifest = new DOMDocument();
    if (!$manifest->loadXML($zip->getFromName('META-INF/manifest.xml'), LIBXML_NONET)) throw new RuntimeException('Manifest template tidak valid.');
    if (!$base || !$styles) throw new RuntimeException('Template slide analisis tidak valid.');
    foreach ($slides as $i => $slide) {
        $values = $slide['fields'] ?? null;
        $sizes = $slide['fontSizes'] ?? null;
        if (!is_array($values) || !array_is_list($values) || count($values) !== 3 || !is_array($sizes) || !array_is_list($sizes) || count($sizes) !== 3) {
            throw new InvalidArgumentException('Setiap slide memerlukan judul, subjudul, isi, dan ukuran teks.');
        }
        $page = $base->cloneNode(true);
        $page->setAttributeNS('urn:oasis:names:tc:opendocument:xmlns:drawing:1.0', 'draw:name', 'analysis-' . ($i + 1));
        $isCover = ($slide['type'] ?? '') === 'cover';
        if ($isCover) {
            if ($i !== 0) throw new InvalidArgumentException('Cover harus menjadi slide pertama.');
            $page->setAttributeNS('urn:oasis:names:tc:opendocument:xmlns:drawing:1.0', 'draw:master-page-name', 'Blank_20_Slide');
            $frames = $xp->query('./draw:frame', $page);
            foreach ([[40,164,500,60],[40,218,500,60],[40,324,500,40]] as $k => $geometry) {
                foreach (['x','y','width','height'] as $axis => $attribute) {
                    $frames->item($k)->setAttributeNS('urn:oasis:names:tc:opendocument:xmlns:svg-compatible:1.0', 'svg:' . $attribute, ($geometry[$axis] * 25.4 / 960) . 'cm');
                }
            }
        }
        $presentation->appendChild($page);
        $boxes = $xp->query('./draw:frame/draw:text-box', $page);
        foreach ($values as $k => $value) {
            if (!is_string($value) || strlen($value) > 12000) throw new InvalidArgumentException('Teks slide terlalu panjang atau tidak valid.');
            $size = $sizes[$k];
            if (!is_int($size) || $size < 10 || $size > 42) throw new InvalidArgumentException('Ukuran teks slide tidak valid.');
            $styleName = 'AnalysisText' . $i . '_' . $k;
            $style = $doc->createElementNS('urn:oasis:names:tc:opendocument:xmlns:style:1.0', 'style:style');
            $style->setAttributeNS('urn:oasis:names:tc:opendocument:xmlns:style:1.0', 'style:name', $styleName);
            $style->setAttributeNS('urn:oasis:names:tc:opendocument:xmlns:style:1.0', 'style:family', 'paragraph');
            $style->setAttributeNS('urn:oasis:names:tc:opendocument:xmlns:style:1.0', 'style:parent-style-name', ['AnalysisTitle', 'AnalysisSubtitle', 'AnalysisBody'][$k]);
            $properties = $doc->createElementNS('urn:oasis:names:tc:opendocument:xmlns:style:1.0', 'style:text-properties');
            $properties->setAttributeNS('urn:oasis:names:tc:opendocument:xmlns:xsl-fo-compatible:1.0', 'fo:font-size', ($size * 0.75) . 'pt');
            $properties->setAttributeNS('urn:oasis:names:tc:opendocument:xmlns:xsl-fo-compatible:1.0', 'fo:font-family', 'Arial');
            $properties->setAttributeNS('urn:oasis:names:tc:opendocument:xmlns:xsl-fo-compatible:1.0', 'fo:font-weight', $k < 2 ? 'bold' : 'normal');
            if ($isCover) $properties->setAttributeNS('urn:oasis:names:tc:opendocument:xmlns:xsl-fo-compatible:1.0', 'fo:color', '#ffffff');
            $style->appendChild($properties);
            $styles->appendChild($style);
            $box = $boxes->item($k);
            while ($box->firstChild) $box->removeChild($box->firstChild);
            foreach (explode("\n", str_replace(["\r\n", "\r"], "\n", $value)) as $line) {
                $paragraph = $doc->createElementNS('urn:oasis:names:tc:opendocument:xmlns:text:1.0', 'text:p');
                $paragraph->setAttributeNS('urn:oasis:names:tc:opendocument:xmlns:text:1.0', 'text:style-name', $styleName);
                $box->appendChild($paragraph);
                foreach (preg_split('/( +)/u', $line, -1, PREG_SPLIT_DELIM_CAPTURE) as $part) {
                    if ($part !== '' && $part[0] === ' ') {
                        $space = $doc->createElementNS('urn:oasis:names:tc:opendocument:xmlns:text:1.0', 'text:s');
                        $space->setAttributeNS('urn:oasis:names:tc:opendocument:xmlns:text:1.0', 'text:c', (string)strlen($part));
                        $paragraph->appendChild($space);
                    } else $paragraph->appendChild($doc->createTextNode($part));
                }
            }
        }
        $bodyImage = $slide['bodyImage'] ?? '';
        if (!is_string($bodyImage)) throw new InvalidArgumentException('Gambar slide tidak valid.');
        if ($bodyImage !== '') {
            if (strlen($bodyImage) > 3000000 || !str_starts_with($bodyImage, 'data:image/png;base64,')) throw new InvalidArgumentException('Gambar slide terlalu besar atau tidak valid.');
            $imageBytes = base64_decode(substr($bodyImage, 22), true);
            $info = $imageBytes === false ? false : @getimagesizefromstring($imageBytes);
            if (!$info || $info[2] !== IMAGETYPE_PNG || $info[0] !== 1640 || $info[1] !== 620) throw new InvalidArgumentException('Ukuran gambar slide tidak valid.');
            $imagePath = 'Pictures/Analysis-' . ($i + 1) . '.png';
            $zip->addFromString($imagePath, $imageBytes);
            $bodyFrame = $boxes->item(2)->parentNode;
            $frame = $bodyFrame->cloneNode(false);
            $frame->setAttributeNS('urn:oasis:names:tc:opendocument:xmlns:drawing:1.0', 'draw:name', 'FormattedAnalysis-' . ($i + 1));
            $picture = $doc->createElementNS('urn:oasis:names:tc:opendocument:xmlns:drawing:1.0', 'draw:image');
            $picture->setAttributeNS('http://www.w3.org/1999/xlink', 'xlink:href', $imagePath);
            $picture->setAttributeNS('http://www.w3.org/1999/xlink', 'xlink:type', 'simple');
            $picture->setAttributeNS('http://www.w3.org/1999/xlink', 'xlink:show', 'embed');
            $picture->setAttributeNS('http://www.w3.org/1999/xlink', 'xlink:actuate', 'onLoad');
            $frame->appendChild($picture);
            $page->replaceChild($frame, $bodyFrame);
            $entry = $manifest->createElementNS('urn:oasis:names:tc:opendocument:xmlns:manifest:1.0', 'manifest:file-entry');
            $entry->setAttributeNS('urn:oasis:names:tc:opendocument:xmlns:manifest:1.0', 'manifest:full-path', $imagePath);
            $entry->setAttributeNS('urn:oasis:names:tc:opendocument:xmlns:manifest:1.0', 'manifest:media-type', 'image/png');
            $manifest->documentElement->appendChild($entry);
        }
    }
    $presentation->removeChild($base);
    $zip->addFromString('content.xml', $doc->saveXML());
    $zip->addFromString('META-INF/manifest.xml', $manifest->saveXML());
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
