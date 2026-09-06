<?php
declare(strict_types=1);

$htmlPath = __DIR__ . '/perfil-publico.html';
$html = is_file($htmlPath) ? (string) file_get_contents($htmlPath) : '';

if ($html === '') {
    http_response_code(500);
    echo 'No se pudo cargar el perfil publico.';
    exit;
}

$business = null;
$slug = trim((string) ($_GET['slug'] ?? $_GET['business_slug'] ?? ''));

if ($slug !== '') {
    try {
        require_once __DIR__ . '/config/database.php';

        $pdo = db();
        $stmt = $pdo->prepare(
            "SELECT nombre_negocio, slug, tipo, descripcion, pais, provincia, avatar_url, portada_url
             FROM negocios
             WHERE slug = :slug
               AND estado = 'activo'
             LIMIT 1"
        );
        $stmt->execute(['slug' => $slug]);
        $business = $stmt->fetch() ?: null;
    } catch (Throwable $e) {
        error_log('[TuyoMall:perfil-publico-meta] ' . $e->getMessage());
    }
}

function tm_meta_escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function tm_absolute_url(?string $path): string
{
    $host = (string) ($_SERVER['HTTP_HOST'] ?? 'tuyomall.com');
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $path = trim((string) $path);

    if ($path === '') {
        return $scheme . '://' . $host . '/assets/img/logo-tuyomall.png';
    }

    if (preg_match('/^https?:\/\//i', $path)) {
        return $path;
    }

    return $scheme . '://' . $host . '/' . ltrim($path, '/');
}

$name = trim((string) ($business['nombre_negocio'] ?? 'Negocio TuyoMall'));
$location = trim(implode(', ', array_filter([
    (string) ($business['provincia'] ?? ''),
    (string) ($business['pais'] ?? ''),
])));
$description = trim((string) ($business['descripcion'] ?? 'Conoce este negocio en TuyoMall y contacta directamente con el emprendedor.'));

if ($description === '') {
    $description = 'Conoce ' . $name . ' en TuyoMall y contacta directamente con el emprendedor.';
}

if ($location !== '') {
    $description .= ' ' . $location . '.';
}

$title = 'TuyoMall | ' . $name;
$shareUrl = tm_absolute_url($business && !empty($business['slug']) ? '/negocio/' . rawurlencode((string) $business['slug']) : ($_SERVER['REQUEST_URI'] ?? '/perfil-publico.php'));
$shareImage = tm_absolute_url((string) ($business['portada_url'] ?? $business['avatar_url'] ?? 'assets/img/logo-tuyomall.png'));

$meta = "\n" .
    '<meta property="og:type" content="website">' . "\n" .
    '<meta property="og:site_name" content="TuyoMall">' . "\n" .
    '<meta property="og:title" content="' . tm_meta_escape($title) . '">' . "\n" .
    '<meta property="og:description" content="' . tm_meta_escape($description) . '">' . "\n" .
    '<meta property="og:url" content="' . tm_meta_escape($shareUrl) . '">' . "\n" .
    '<meta property="og:image" content="' . tm_meta_escape($shareImage) . '">' . "\n" .
    '<meta name="twitter:card" content="summary_large_image">' . "\n" .
    '<meta name="twitter:title" content="' . tm_meta_escape($title) . '">' . "\n" .
    '<meta name="twitter:description" content="' . tm_meta_escape($description) . '">' . "\n" .
    '<meta name="twitter:image" content="' . tm_meta_escape($shareImage) . '">' . "\n" .
    '<link rel="canonical" href="' . tm_meta_escape($shareUrl) . '">' . "\n";

$html = preg_replace('/<title>.*?<\/title>/s', '<title>' . tm_meta_escape($title) . '</title>', $html, 1) ?? $html;
$html = preg_replace('/<meta name="description" content=".*?">/s', '<meta name="description" content="' . tm_meta_escape($description) . '">', $html, 1) ?? $html;
$html = preg_replace('/<\/head>/i', $meta . '</head>', $html, 1) ?? $html;

echo $html;
