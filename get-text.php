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

// Fall back to fetching the article URL directly if text API is unavailable.
if ($html === false && !empty($articleUrl)) {
    $html = fetchUrlDirect($articleUrl);
}

if ($html === false) {
    header('HTTP/1.1 502 Bad Gateway');
    die('<html><body><p>Could not retrieve article from Instapaper. Please check connectivity and try again.</p></body></html>');
}

// Inject <base href> so relative and protocol-relative URLs resolve correctly
// when the saved HTML file is loaded from file:// by the webOS WebView.
if (!empty($articleUrl)) {
    $html = injectBaseTag($html, $articleUrl);
}

header('Content-Type: text/html; charset=utf-8');
echo $html;

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

function injectBaseTag($html, $url) {
    $base = '<base href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '">';
    // Insert after <head> (with any attributes), or before </head>, or at the top.
    if (preg_match('/<head(\s[^>]*)?>/i', $html)) {
        return preg_replace('/(<head(\s[^>]*)?>)/i', '$1' . $base, $html, 1);
    } elseif (stripos($html, '</head>') !== false) {
        return str_ireplace('</head>', $base . '</head>', $html);
    } else {
        return $base . $html;
    }
}
?>
