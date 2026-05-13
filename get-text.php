<?php
// Proxy endpoint: fetches article content on behalf of a webOS device.
// The returned HTML is injected directly into the app's DOM via enyo.HtmlContent,
// so it must be a clean fragment — no <style>, <link>, or <script> tags, no full
// document wrapper, all URLs absolute.
//
// Query params:
//   id  — bookmark_id
//   t   — oauth_token
//   s   — oauth_token_secret
//   u   — article URL (fallback + base for URL rewriting)

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
        // Try Readability for a clean mobilised extract.
        $html = applyReadability($rawHtml, $articleUrl);
        if ($html === false) {
            // Readability not installed — clean and rewrite the full page.
            $html = cleanAndRewrite($rawHtml, $articleUrl);
        }
    }
}

if ($html === false) {
    header('HTTP/1.1 502 Bad Gateway');
    die('<p>Could not retrieve article. Please check connectivity and try again.</p>');
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

// Tries fivefilters/readability.php, then andreskrey/readability.php.
// Returns a clean HTML fragment on success, false if neither library is present.
function applyReadability($html, $url) {
    if (class_exists('fivefilters\Readability\Readability')) {
        $readabilityClass  = 'fivefilters\Readability\Readability';
        $configClass       = 'fivefilters\Readability\Configuration';
        $exceptionClass    = 'fivefilters\Readability\ParseException';
    } elseif (class_exists('andreskrey\Readability\Readability')) {
        $readabilityClass  = 'andreskrey\Readability\Readability';
        $configClass       = 'andreskrey\Readability\Configuration';
        $exceptionClass    = 'andreskrey\Readability\ParseException';
    } else {
        return false;
    }
    try {
        $config      = new $configClass(['originalURL' => $url, 'fixRelativeURLs' => true]);
        $readability = new $readabilityClass($config);
        $readability->parse($html);
        $title   = $readability->getTitle() ?: '';
        $content = $readability->getContent();
        if (empty($content)) {
            return false;
        }
        return '<h2>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h2>' . addImageConstraints($content);
    } catch (Exception $e) {
        error_log('Readability failed for ' . $url . ': ' . $e->getMessage());
        return false;
    }
}

// Strips <style>/<link>/<script> tags, rewrites URLs to absolute, constrains images.
// Returns a body-content HTML fragment suitable for injection into the app DOM.
function cleanAndRewrite($html, $baseUrl) {
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

    // Remove tags that would pollute the app's CSS/JS context.
    foreach (['style', 'link', 'script', 'noscript', 'iframe', 'object', 'embed'] as $tag) {
        $nodes = $dom->getElementsByTagName($tag);
        while ($nodes->length > 0) {
            $node = $nodes->item(0);
            $node->parentNode->removeChild($node);
        }
    }

    // Rewrite URL attributes to absolute.
    foreach ($dom->getElementsByTagName('*') as $node) {
        foreach (['src', 'href', 'action', 'poster'] as $attr) {
            $val = $node->getAttribute($attr);
            if (!empty($val)) {
                $node->setAttribute($attr, resolveUrl($val, $scheme, $origin, $dir));
            }
        }
        $srcset = $node->getAttribute('srcset');
        if (!empty($srcset)) {
            $parts = preg_split('/,\s+/', $srcset);
            foreach ($parts as &$part) {
                $tokens  = preg_split('/\s+/', trim($part), 2);
                $tokens[0] = resolveUrl($tokens[0], $scheme, $origin, $dir);
                $part    = implode(' ', $tokens);
            }
            $node->setAttribute('srcset', implode(', ', $parts));
        }
    }

    // Constrain images so they don't break the app layout.
    foreach ($dom->getElementsByTagName('img') as $img) {
        $existing = $img->getAttribute('style');
        $img->setAttribute('style', 'max-width:100%;height:auto;' . $existing);
    }

    // Extract just the body content as a fragment.
    $body = $dom->getElementsByTagName('body')->item(0);
    if ($body) {
        $fragment = '';
        foreach ($body->childNodes as $child) {
            $fragment .= $dom->saveHTML($child);
        }
        return $fragment;
    }
    return $dom->saveHTML();
}

function addImageConstraints($html) {
    return preg_replace('/<img\s/i', '<img style="max-width:100%;height:auto;" ', $html);
}

function resolveUrl($url, $scheme, $origin, $dir) {
    if (empty($url)) return $url;
    foreach (['data:', '#', 'javascript:', 'mailto:', 'http://', 'https://'] as $prefix) {
        if (strpos($url, $prefix) === 0) return $url;
    }
    if (strpos($url, '//') === 0) return $scheme . ':' . $url;
    if (strpos($url, '/') === 0)  return $origin . $url;
    return $dir . $url;
}
?>
