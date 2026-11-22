<?php
/**
 * Streams a repository document after checking the viewer may see it.
 *   Federation: every document.
 *   Barangay official: own barangay's documents and federation-level ones.
 *   KK member: published (is_public) documents for their barangay or all.
 * Proposal attachments are never public.
 */
require_once __DIR__ . '/includes/bootstrap.php';
$user = require_login();

$doc = q_one('SELECT * FROM documents WHERE document_id = ?', [get_int('id')]);
$allowed = false;
if ($doc) {
    $docBrgy = $doc['barangay_id'] === null ? null : (int) $doc['barangay_id'];
    switch (role()) {
        case ROLE_FEDERATION:
            $allowed = true;
            break;
        case ROLE_BARANGAY:
            $allowed = $docBrgy === null || $docBrgy === my_barangay();
            break;
        case ROLE_MEMBER:
            $allowed = (int) $doc['is_public'] === 1 && $doc['category'] !== 'proposal' && ($docBrgy === null || $docBrgy === my_barangay());
            break;
    }
}
$path = $doc ? UPLOAD_DIR . '/' . $doc['file_path'] : '';
if (!$allowed || str_contains($doc['file_path'], '..') || !is_file($path)) {
    http_response_code(404);
    exit('File not found or you do not have access to it.');
}

$ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
$mime = (new finfo(FILEINFO_MIME_TYPE))->file($path) ?: 'application/octet-stream';
$name = preg_replace('/[^A-Za-z0-9 ._()-]/', '', $doc['title']) . '.' . $ext;
$inline = in_array($ext, ['pdf', 'jpg', 'jpeg', 'png'], true) && get('download') === '';

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($path));
header('X-Content-Type-Options: nosniff');
header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . $name . '"');
readfile($path);
