<?php
// Proxy endpoint: fetches mobilized article HTML from Instapaper on behalf of
// a webOS device (which cannot set custom HTTP headers for OAuth signing).
//
// Query params:
//   id  — bookmark_id
//   t   — oauth_token
//   s   — oauth_token_secret
//
// The consumer key/secret come from config.php.

include("config.php");
include("common.php");
spl_autoload_register(function($classes) {
    include 'classes/' . $classes . ".php";
});

$bookmarkId       = isset($_GET['id']) ? intval($_GET['id']) : 0;
$oauthToken       = isset($_GET['t'])  ? trim($_GET['t'])    : '';
$oauthTokenSecret = isset($_GET['s'])  ? trim($_GET['s'])    : '';

if (!$bookmarkId || empty($oauthToken) || empty($oauthTokenSecret)) {
    header('HTTP/1.1 400 Bad Request');
    die('Missing required parameters.');
}

$auth = new InstapaperAuth($consumerKey, $consumerSecret);
$html = $auth->getBookmarkText($bookmarkId, $oauthToken, $oauthTokenSecret);

if ($html === false) {
    header('HTTP/1.1 502 Bad Gateway');
    die('<html><body><p>Could not retrieve article from Instapaper. Please check connectivity and try again.</p></body></html>');
}

header('Content-Type: text/html; charset=utf-8');
echo $html;
?>
