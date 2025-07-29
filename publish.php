<?php
/** Maneja la subida de imágenes de TinyMCE o publica un artículo completo */

function require_auth() {
    $user = getenv('BASIC_USER');
    $pass = getenv('BASIC_PASS');
    if (!isset($_SERVER['PHP_AUTH_USER']) ||
        $_SERVER['PHP_AUTH_USER'] !== $user ||
        $_SERVER['PHP_AUTH_PW'] !== $pass) {
        header('WWW-Authenticate: Basic realm="CMS"');
        header('HTTP/1.0 401 Unauthorized');
        echo 'Autenticación requerida';
        exit;
    }
}

require_auth();

// Si es llamada de TinyMCE para subir una imagen
if (isset($_GET['image'])) {
    header('Content-Type: application/json');
    if (!isset($_FILES['file'])) {
        echo json_encode(['error' => 'No se recibió archivo']);
        exit;
    }
    $uploadDir = __DIR__ . '/uploads/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    $dest = $uploadDir . basename($_FILES['file']['name']);
    move_uploaded_file($_FILES['file']['tmp_name'], $dest);
    echo json_encode(['location' => 'uploads/' . basename($dest)]);
    exit;
}

// Función para crear slugs
function slugify($text) {
    $text = iconv('UTF-8', 'ASCII//TRANSLIT', $text);
    $text = preg_replace('/[^a-zA-Z0-9]+/', '-', $text);
    $text = strtolower(trim($text, '-'));
    return $text;
}

$siteUrl = rtrim(getenv('SITE_URL'), '/');
$host = getenv('SFTP_HOST');
$user = getenv('SFTP_USER');
$pass = getenv('SFTP_PASS');
$postsPath = rtrim(getenv('SFTP_POSTS_PATH'), '/');
$imagesPath = rtrim(getenv('SFTP_IMAGES_PATH'), '/');

$title = $_POST['title'] ?? '';
$meta = $_POST['meta_description'] ?? '';
$dateStr = $_POST['date'] ?? '';
$author = $_POST['author'] ?? '';
$categories = $_POST['categories'] ?? [];
$tags = array_slice($_POST['tags'] ?? [], 0, 3);
$content = $_POST['content'] ?? '';

$slug = slugify($title);
$date = DateTime::createFromFormat('d/m/Y', $dateStr);

// Conexión SFTP
$connection = ssh2_connect($host, 22);
ssh2_auth_password($connection, $user, $pass);
$sftp = ssh2_sftp($connection);

// Procesar imagen destacada
$featuredBase = '';
if (!empty($_FILES['featured_image']['tmp_name'])) {
    $ext = pathinfo($_FILES['featured_image']['name'], PATHINFO_EXTENSION);
    $featuredBase = $slug . '-header.' . $ext;
    $remote = "$imagesPath/$featuredBase";
    $stream = fopen("ssh2.sftp://$sftp$remote", 'w');
    $local = fopen($_FILES['featured_image']['tmp_name'], 'r');
    stream_copy_to_stream($local, $stream);
    fclose($stream);
    fclose($local);
}

// Imágenes dentro del contenido
$images = [];
$i = 1;
preg_match_all('/<img[^>]+src="([^"]+)"[^>]*>/i', $content, $matches);
foreach ($matches[1] as $src) {
    if (strpos($src, 'uploads/') === 0) {
        $ext = pathinfo($src, PATHINFO_EXTENSION);
        $newBase = $slug . '-img' . $i . '.' . $ext;
        $remote = "$imagesPath/$newBase";
        $local = __DIR__ . '/' . $src;
        if (file_exists($local)) {
            $stream = fopen("ssh2.sftp://$sftp$remote", 'w');
            $l = fopen($local, 'r');
            stream_copy_to_stream($l, $stream);
            fclose($stream);
            fclose($l);
            unlink($local);
            $content = str_replace($src, $siteUrl . '/' . $newBase, $content);
            $images[] = $newBase;
            $i++;
        }
    }
}

$template = file_get_contents(__DIR__ . '/template.html');
$replacements = [
    '{{TITLE}}' => $title,
    '{{META_DESCRIPTION}}' => $meta,
    '{{ARTICLE_URL}}' => $siteUrl . '/' . $slug . '.html',
    '{{PUBLISHED_TIME}}' => $date ? $date->format('d/m/Y') : '',
    '{{POST_DAY}}' => $date ? $date->format('d') : '',
    '{{POST_MONTH}}' => $date ? $date->format('m') : '',
    '{{POST_YEAR}}' => $date ? $date->format('Y') : '',
    '{{CATEGORY}}' => $categories[0] ?? '',
    '{{CATEGORIES}}' => implode(',', $categories),
    '{{TAG_1}}' => $tags[0] ?? '',
    '{{TAG_2}}' => $tags[1] ?? '',
    '{{TAG_3}}' => $tags[2] ?? '',
    '{{LAST_MODIFIED}}' => $date ? $date->format('d/m/Y') : '',
    '{{FEATURE_IMAGE}}' => $featuredBase,
    '{{IMAGES}}' => implode(',', $images),
    '{{CONTENT}}' => $content,
    '{{SITE_URL}}' => $siteUrl
];
$html = str_replace(array_keys($replacements), array_values($replacements), $template);
$tmpHtml = tempnam(sys_get_temp_dir(), 'html');
file_put_contents($tmpHtml, $html);
$remoteHtml = "$postsPath/$slug.html";
$stream = fopen("ssh2.sftp://$sftp$remoteHtml", 'w');
$local = fopen($tmpHtml, 'r');
stream_copy_to_stream($local, $stream);
fclose($stream);
fclose($local);
unlink($tmpHtml);

// Actualizar posts.json
$postsFile = __DIR__ . '/posts.json';
$posts = file_exists($postsFile) ? json_decode(file_get_contents($postsFile), true) : [];
if (!is_array($posts)) $posts = [];
$index = count($posts);
foreach ($posts as $k => $v) {
    if ($v['slug'] === $slug) {
        $index = $k;
        unset($posts[$k]);
        break;
    }
}
$entry = [
    'slug' => $slug,
    'title' => $title,
    'date' => $dateStr,
    'author' => $author,
    'meta_description' => $meta,
    'categories' => $categories,
    'tags' => $tags,
    'featured_image' => $featuredBase,
    'images' => $images
];
array_splice($posts, $index, 0, [$entry]);
file_put_contents($postsFile, json_encode(array_values($posts), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

// Actualizar sitemap.xml
$xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";
foreach ($posts as $p) {
    $xml .= "    <url><loc>{$siteUrl}/{$p['slug']}.html</loc></url>\n";
}
$xml .= "</urlset>\n";
$sitemapFile = __DIR__ . '/sitemap.xml';
file_put_contents($sitemapFile, $xml);

// Subir posts.json y sitemap.xml
$remotePosts = "$postsPath/posts.json";
$stream = fopen("ssh2.sftp://$sftp$remotePosts", 'w');
$local = fopen($postsFile, 'r');
stream_copy_to_stream($local, $stream);
fclose($stream);
fclose($local);

$remoteSite = "$postsPath/sitemap.xml";
$stream = fopen("ssh2.sftp://$sftp$remoteSite", 'w');
$local = fopen($sitemapFile, 'r');
stream_copy_to_stream($local, $stream);
fclose($stream);
fclose($local);

header('Content-Type: application/json');
echo json_encode(['success' => true]);

