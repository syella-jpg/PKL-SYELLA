<?php
const TM_BOD_CONTENT_MAX_BYTES = 32 * 1024 * 1024;

/** Keep older plain JSON readable; compress larger rich-text/image documents losslessly. */
function encodeTmBodContent(array $content): string {
    $json = json_encode($content, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    if (strlen($json) > TM_BOD_CONTENT_MAX_BYTES) throw new InvalidArgumentException('Isi kuartal terlalu besar (maksimal 32 MB). Perkecil gambar lalu simpan kembali.');
    if (strlen($json) < 65536 || !function_exists('gzencode')) return $json;
    // Values occur in both the ordered slide array and the stable-ID map.
    // Store that array once when it can be reconstructed exactly from its IDs.
    $compact = $content; $restore = [];
    foreach (['Q1','Q2','Q3','Q4'] as $q) {
        $values = $content[$q] ?? null; $ids = $content[$q.'-section-ids'] ?? null; $map = $content[$q.'-section-values'] ?? null;
        if (!is_array($values) || !$values || !is_array($ids) || !is_array($map) || count($values) !== count($ids)) continue;
        $same = true;
        foreach ($ids as $i=>$id) if (!is_string($id) || !array_key_exists($id,$map) || $map[$id] !== $values[$i]) { $same=false; break; }
        if ($same) { $compact[$q]=null; $restore[]=$q; }
    }
    $compactJson = json_encode(['content'=>$compact,'restore'=>$restore], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    $compressed = gzencode($compactJson, 6);
    if ($compressed === false) throw new RuntimeException('Kompresi isi kuartal gagal.');
    $packed = json_encode(['encoding'=>'tm-bod-compact-gzip-v1','payload'=>base64_encode($compressed)], JSON_THROW_ON_ERROR);
    return strlen($packed) < strlen($json) ? $packed : $json;
}
function decodeTmBodContent(string $stored): array {
    $content = json_decode($stored, true, 512, JSON_THROW_ON_ERROR);
    if (is_array($content) && in_array(($content['encoding'] ?? ''), ['tm-bod-gzip-v1','tm-bod-compact-gzip-v1'], true)) {
        $bytes = base64_decode($content['payload'] ?? '', true);
        if ($bytes === false || !function_exists('gzdecode')) throw new RuntimeException('Isi kuartal terkompresi belum dapat dibaca pada server ini.');
        $json = gzdecode($bytes, TM_BOD_CONTENT_MAX_BYTES + 65536);
        if ($json === false) throw new RuntimeException('Isi kuartal terkompresi rusak atau melampaui batas.');
        $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        if ($content['encoding'] === 'tm-bod-compact-gzip-v1') {
            $content = $decoded['content'];
            foreach ($decoded['restore'] as $q) $content[$q] = array_map(fn($id) => $content[$q.'-section-values'][$id], $content[$q.'-section-ids']);
        } else $content = $decoded;
    }
    if (!is_array($content)) throw new RuntimeException('Struktur isi kuartal tersimpan tidak valid.');
    return $content;
}
