<?php

/**
 * Safe SVG uploads.
 *
 * SVG files are XML documents that can carry scripts, event handlers and
 * external references, so an unfiltered SVG upload is a stored XSS vector.
 * Here SVG uploads are:
 *  - allowed only to users with the `sage/svg_upload_capability` capability
 *    (default `edit_others_posts`, i.e. editors and administrators);
 *  - always sanitized with enshrined/svg-sanitize before WordPress stores
 *    them, and rejected when the sanitizer cannot produce a clean document;
 *  - rejected when compressed (.svgz), since they could not be inspected;
 *  - rejected when written through wp_upload_bits() (XML-RPC
 *    wp.uploadFile / metaWeblog.newMediaObject), which bypasses the upload
 *    prefilters and cannot be sanitized.
 */

namespace App\Svg;

use enshrined\svgSanitize\Sanitizer;
use WP_User;

const MIME_TYPE = 'image/svg+xml';

/**
 * Capability required to upload SVG files.
 */
function capability(): string
{
    return (string) apply_filters('sage/svg_upload_capability', 'edit_others_posts');
}

/**
 * Whether the given user (default: current user) may upload SVG files.
 */
function user_can_upload(int|WP_User|null $user = null): bool
{
    return user_can($user ?? wp_get_current_user(), capability());
}

/**
 * Allow the svg extension only to authorized users.
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
 * Detect the real MIME type of a file from its content.
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
 * Sanitize SVG uploads before WordPress moves them into the uploads folder.
 *
 * Runs on both wp_handle_upload_prefilter (form/REST multipart uploads) and
 * wp_handle_sideload_prefilter (sideload, REST raw body, WP-CLI).
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
        $file['error'] = __('Compressed SVG files (.svgz) are not allowed.', 'sage');

        return $file;
    }

    $looks_like_svg = $mime === MIME_TYPE;

    if ($ext !== 'svg' && ! $looks_like_svg) {
        return $file;
    }

    if ($ext !== 'svg') {
        $file['error'] = __('SVG content is only allowed in files with the .svg extension.', 'sage');

        return $file;
    }

    if (! user_can_upload()) {
        $file['error'] = __('Sorry, you are not allowed to upload SVG files.', 'sage');

        return $file;
    }

    $dirty = is_readable($path) ? file_get_contents($path) : false;

    $sanitizer = new Sanitizer;
    $sanitizer->removeRemoteReferences(true);
    $clean = $dirty === false || $dirty === '' ? false : $sanitizer->sanitize($dirty);

    if (! is_string($clean) || trim($clean) === '' || file_put_contents($path, $clean) === false) {
        $file['error'] = __('This SVG file could not be sanitized and has been rejected.', 'sage');

        return $file;
    }

    $file['size'] = strlen($clean);
    $file['type'] = MIME_TYPE;

    return $file;
}
add_filter('wp_handle_upload_prefilter', __NAMESPACE__.'\\sanitize_upload');
add_filter('wp_handle_sideload_prefilter', __NAMESPACE__.'\\sanitize_upload');

/**
 * Whether raw file contents look like an SVG document (plain or gzipped).
 */
function is_svg_content(string $bits): bool
{
    if (str_starts_with($bits, "\x1f\x8b")) {
        // Inflate in small chunks with a size cap to avoid gzip bombs
        // (gzdecode() does not enforce its max_length). Anything that cannot
        // be decoded within the limit is treated as SVG, so it is rejected.
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
 * Reject SVG files written with wp_upload_bits() (XML-RPC media uploads).
 *
 * wp_upload_bits() skips the upload prefilters, and the core ignores any
 * change made to 'bits' by this filter, so these files cannot be sanitized:
 * returning a string makes the core abort the upload with that error.
 */
function reject_upload_bits(mixed $upload): mixed
{
    if (! is_array($upload)) {
        return $upload;
    }

    $ext = strtolower(pathinfo((string) ($upload['name'] ?? ''), PATHINFO_EXTENSION));
    $bits = $upload['bits'] ?? '';

    if (in_array($ext, ['svg', 'svgz'], true) || (is_string($bits) && is_svg_content($bits))) {
        return __('SVG files must be uploaded through the media library.', 'sage');
    }

    return $upload;
}
add_filter('wp_upload_bits', __NAMESPACE__.'\\reject_upload_bits');

/**
 * Read the intrinsic size of an SVG from its width/height or viewBox.
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
 * Store width/height for SVG attachments.
 *
 * WordPress treats image/svg+xml as an image (wp_attachment_is_image) but
 * cannot measure it, so code reading $meta['width'] (e.g. the REST
 * missing_image_sizes field used by the block editor) raises warnings.
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
