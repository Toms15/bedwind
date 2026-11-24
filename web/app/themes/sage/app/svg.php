<?php

declare(strict_types=1);

/**
 * Caricamento sicuro degli SVG.
 *
 * I file SVG sono documenti XML che possono contenere script, event handler e
 * riferimenti esterni, quindi un caricamento SVG non filtrato è un vettore di XSS
 * persistente. Qui i caricamenti SVG sono:
 *  - consentiti solo agli utenti con la capability `sage/svg_upload_capability`
 *    (default `edit_others_posts`, cioè editor e amministratori);
 *  - sempre sanificati con enshrined/svg-sanitize prima che WordPress li salvi,
 *    e rifiutati se il sanitizer non riesce a produrre un documento pulito;
 *  - rifiutati se compressi (.svgz), perché non si potrebbero ispezionare;
 *  - rifiutati se scritti tramite wp_upload_bits() (XML-RPC
 *    wp.uploadFile / metaWeblog.newMediaObject), che salta i prefiltri di
 *    caricamento e non permette la sanificazione.
 */

namespace App\Svg;

use enshrined\svgSanitize\Sanitizer;
use WP_User;

const MIME_TYPE = 'image/svg+xml';

/**
 * Capability richiesta per caricare file SVG.
 */
function capability(): string
{
    return (string) apply_filters('sage/svg_upload_capability', 'edit_others_posts');
}

/**
 * Indica se l'utente dato (default: l'utente corrente) può caricare file SVG.
 */
function user_can_upload(int|WP_User|null $user = null): bool
{
    return user_can($user ?? wp_get_current_user(), capability());
}

/**
 * Consente l'estensione svg solo agli utenti autorizzati.
 *
 * @param  array<string, string>  $mimes
 * @return array<string, string>
 */
function filter_upload_mimes(array $mimes, int|WP_User|null $user = null): array
{
    unset($mimes['svgz']);

    if (user_can_upload($user)) {
        $mimes['svg'] = MIME_TYPE;
    } else {
        unset($mimes['svg']);
    }

    return $mimes;
}
add_filter('upload_mimes', __NAMESPACE__.'\\filter_upload_mimes', 10, 2);

/**
 * Rileva il vero MIME type di un file dal suo contenuto.
 */
function real_mime(string $path): ?string
{
    if ($path === '' || ! is_readable($path) || ! extension_loaded('fileinfo')) {
        return null;
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $path);
    finfo_close($finfo);

    return $mime ?: null;
}

/**
 * Sanifica i caricamenti SVG prima che WordPress li sposti nella cartella uploads.
 *
 * Gira sia su wp_handle_upload_prefilter (caricamenti da form/REST multipart) sia
 * su wp_handle_sideload_prefilter (sideload, body REST grezzo, WP-CLI).
 *
 * @param  array<string, mixed>  $file
 * @return array<string, mixed>
 */
function sanitize_upload(array $file): array
{
    if (! empty($file['error'])) {
        return $file;
    }

    $name = (string) ($file['name'] ?? '');
    $path = (string) ($file['tmp_name'] ?? '');
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    $mime = real_mime($path);

    if ($ext === 'svgz') {
        $file['error'] = __('I file SVG compressi (.svgz) non sono consentiti.', 'sage');

        return $file;
    }

    $looks_like_svg = $mime === MIME_TYPE;

    if ($ext !== 'svg' && ! $looks_like_svg) {
        return $file;
    }

    if ($ext !== 'svg') {
        $file['error'] = __('Il contenuto SVG è consentito solo nei file con estensione .svg.', 'sage');

        return $file;
    }

    if (! user_can_upload()) {
        $file['error'] = __('Non hai i permessi per caricare file SVG.', 'sage');

        return $file;
    }

    $dirty = is_readable($path) ? file_get_contents($path) : false;

    $sanitizer = new Sanitizer;
    $sanitizer->removeRemoteReferences(true);
    $clean = $dirty === false || $dirty === '' ? false : $sanitizer->sanitize($dirty);

    if (! is_string($clean) || trim($clean) === '' || file_put_contents($path, $clean) === false) {
        $file['error'] = __('Non è stato possibile ripulire questo file SVG, che è stato quindi rifiutato.', 'sage');

        return $file;
    }

    $file['size'] = strlen($clean);
    $file['type'] = MIME_TYPE;

    return $file;
}
add_filter('wp_handle_upload_prefilter', __NAMESPACE__.'\\sanitize_upload');
add_filter('wp_handle_sideload_prefilter', __NAMESPACE__.'\\sanitize_upload');

/**
 * Indica se il contenuto grezzo di un file sembra un documento SVG (semplice o gzip).
 */
function is_svg_content(string $bits): bool
{
    if (str_starts_with($bits, "\x1f\x8b")) {
        // Decomprime a piccoli blocchi con un tetto di dimensione per evitare le
        // gzip bomb (gzdecode() non rispetta il suo max_length). Ciò che non si
        // riesce a decodificare entro il limite è trattato come SVG, quindi rifiutato.
        $limit = 10 * MB_IN_BYTES;
        $context = inflate_init(ZLIB_ENCODING_GZIP);
        $decoded = '';

        foreach (str_split($bits, 1024) as $chunk) {
            $part = $context ? @inflate_add($context, $chunk) : false;

            if ($part === false || strlen($decoded .= $part) > $limit) {
                return true;
            }
        }

        $bits = $decoded;
    }

    return stripos($bits, '<svg') !== false;
}

/**
 * Rifiuta i file SVG scritti con wp_upload_bits() (caricamenti media via XML-RPC).
 *
 * wp_upload_bits() salta i prefiltri di caricamento, e il core ignora qualsiasi
 * modifica fatta a 'bits' da questo filtro, quindi questi file non si possono
 * sanificare: restituire una stringa fa interrompere al core il caricamento con quell'errore.
 */
function reject_upload_bits(mixed $upload): mixed
{
    if (! is_array($upload)) {
        return $upload;
    }

    $ext = strtolower(pathinfo((string) ($upload['name'] ?? ''), PATHINFO_EXTENSION));
    $bits = $upload['bits'] ?? '';

    if (in_array($ext, ['svg', 'svgz'], true) || (is_string($bits) && is_svg_content($bits))) {
        return __('I file SVG devono essere caricati dalla libreria media.', 'sage');
    }

    return $upload;
}
add_filter('wp_upload_bits', __NAMESPACE__.'\\reject_upload_bits');

/**
 * Legge la dimensione intrinseca di un SVG da width/height o dal viewBox.
 *
 * @return array{width: int, height: int}|null
 */
function dimensions(string $path): ?array
{
    $xml = is_readable($path) ? file_get_contents($path) : false;

    if (! $xml) {
        return null;
    }

    $dom = new \DOMDocument;

    if (! @$dom->loadXML($xml, LIBXML_NONET) || ! $dom->documentElement) {
        return null;
    }

    $svg = $dom->documentElement;
    $width = (float) $svg->getAttribute('width');
    $height = (float) $svg->getAttribute('height');

    if (($width <= 0 || $height <= 0) && $svg->hasAttribute('viewBox')) {
        $box = preg_split('/[\s,]+/', trim($svg->getAttribute('viewBox')));

        if (count($box) === 4) {
            $width = (float) $box[2];
            $height = (float) $box[3];
        }
    }

    if ($width <= 0 || $height <= 0) {
        return null;
    }

    return ['width' => (int) round($width), 'height' => (int) round($height)];
}

/**
 * Salva width/height per gli allegati SVG.
 *
 * WordPress tratta image/svg+xml come un'immagine (wp_attachment_is_image) ma
 * non sa misurarla, quindi il codice che legge $meta['width'] (es. il campo REST
 * missing_image_sizes usato dal block editor) genera warning.
 *
 * @param  array<string, mixed>|false  $metadata
 * @return array<string, mixed>|false
 */
function attachment_metadata(array|false $metadata, int $attachment_id): array|false
{
    if (get_post_mime_type($attachment_id) !== MIME_TYPE) {
        return $metadata;
    }

    $path = (string) get_attached_file($attachment_id);
    $size = dimensions($path);

    if (! $size) {
        return $metadata;
    }

    return array_merge(is_array($metadata) ? $metadata : [], $size, [
        'file' => _wp_relative_upload_path($path),
        'sizes' => [],
    ]);
}
add_filter('wp_generate_attachment_metadata', __NAMESPACE__.'\\attachment_metadata', 10, 2);
