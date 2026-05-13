<?php
// Proxy endpoint: fetches mobilized article HTML from Instapaper on behalf of
// a webOS device (which cannot set custom HTTP headers for OAuth signing).
//
// Query params:
//   id  — bookmark_id
//   t   — oauth_token
//   s   — oauth_token_secret
//   u   — article URL (fallback if Instapaper text API is unavailable)

include("config.php");
include("common.php");
spl_autoload_register(function($classes) {
    include 'classes/' . $classes . ".php";
});

if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require __DIR__ . '/vendor/autoload.php';
}

$bookmarkId       = isset($_GET['id']) ? intval($_GET['id']) : 0;
$oauthToken       = isset($_GET['t'])  ? trim($_GET['t'])    : '';
$oauthTokenSecret = isset($_GET['s'])  ? trim($_GET['s'])    : '';
$articleUrl       = isset($_GET['u'])  ? trim($_GET['u'])    : '';

if (!$bookmarkId || empty($oauthToken) || empty($oauthTokenSecret)) {
    header('HTTP/1.1 400 Bad Request');
    die('Missing required parameters.');
}

$auth = new InstapaperAuth($consumerKey, $consumerSecret);
$html = $auth->getBookmarkText($bookmarkId, $oauthToken, $oauthTokenSecret);

if ($html === false && !empty($articleUrl)) {
    $rawHtml = fetchUrlDirect($articleUrl);
    if ($rawHtml !== false) {
        $html = applyReadability($rawHtml, $articleUrl);
        if ($html === false) {
            // Readability not installed or failed — rewrite URLs manually
            $html = rewriteAbsoluteUrls($rawHtml, $articleUrl);
        }
    }
}

if ($html === false) {
    header('HTTP/1.1 502 Bad Gateway');
    die('<html><body><p>Could not retrieve article from Instapaper. Please check connectivity and try again.</p></body></html>');
}

header('Content-Type: text/html; charset=utf-8');
echo $html;

// ---- helpers ----

function fetchUrlDirect($url) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; ReadOnTouch/3.1)');
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($httpCode !== 200 || empty($response)) {
        return false;
    }
    return $response;
}

function applyReadability($html, $url) {
    if (!class_exists('andreskrey\Readability\Readability')) {
        return false;
    }
    try {
        $config = new \andreskrey\Readability\Configuration([
            'originalURL'     => $url,
            'fixRelativeURLs' => true,
        ]);
        $readability = new \andreskrey\Readability\Readability($config);
        $readability->parse($html);
        $title   = $readability->getTitle() ?: '';
        $content = $readability->getContent();
        if (empty($content)) {
            return false;
        }
        $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
        return '<!DOCTYPE html><html><head><meta charset="utf-8"><title>' . $safeTitle . '</title>'
            . '<style>body{font-family:serif;max-width:760px;margin:20px auto;padding:0 12px;font-size:16px;line-height:1.6}img{max-width:100%;height:auto}</style>'
            . '</head><body><h1>' . $safeTitle . '</h1>' . $content . '</body></html>';
    } catch (\Exception $e) {
        error_log('Readability failed for ' . $url . ': ' . $e->getMessage());
        return false;
    }
}

// Rewrites relative and protocol-relative URLs to absolute using DOMDocument.
// Used when Readability is not installed.
function rewriteAbsoluteUrls($html, $baseUrl) {
    $p      = parse_url($baseUrl);
    $scheme = isset($p['scheme']) ? $p['scheme'] : 'https';
    $host   = isset($p['host'])   ? $p['host']   : '';
    $port   = isset($p['port'])   ? ':' . $p['port'] : '';
    $origin = $scheme . '://' . $host . $port;
    $dir    = $origin . (isset($p['path']) ? substr($p['path'], 0, strrpos($p['path'], '/') + 1) : '/');

    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
    libxml_clear_errors();

    $attrs = ['src', 'href', 'action', 'poster'];
    foreach ($dom->getElementsByTagName('*') as $node) {
        foreach ($attrs as $attr) {
            $val = $node->getAttribute($attr);
            if (empty($val)) continue;
            $node->setAttribute($attr, resolveUrl($val, $scheme, $origin, $dir));
        }
        // srcset needs split handling
        $srcset = $node->getAttribute('srcset');
        if (!empty($srcset)) {
            $parts = preg_split('/,\s+/', $srcset);
            foreach ($parts as &$part) {
                $tokens = preg_split('/\s+/', trim($part), 2);
                $tokens[0] = resolveUrl($tokens[0], $scheme, $origin, $dir);
                $part = implode(' ', $tokens);
            }
            $node->setAttribute('srcset', implode(', ', $parts));
        }
    }

    return $dom->saveHTML();
}

function resolveUrl($url, $scheme, $origin, $dir) {
    if (empty($url)) return $url;
    // Leave data URIs, anchors, javascript, mailto, and already-absolute URLs
    foreach (['data:', '#', 'javascript:', 'mailto:', 'http://', 'https://'] as $prefix) {
        if (strpos($url, $prefix) === 0) return $url;
    }
    if (strpos($url, '//') === 0) return $scheme . ':' . $url;  // protocol-relative
    if (strpos($url, '/') === 0)  return $origin . $url;          // root-relative
    return $dir . $url;                                             // path-relative
}
?>
