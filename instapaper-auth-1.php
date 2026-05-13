<?php
$docRoot = "./";
include($docRoot . "config.php");
include($docRoot . "common.php");
spl_autoload_register(function($classes) {
    include 'classes/' . $classes . ".php";
});
$cache = new Cache();

if (!isset($_POST["activationCode"]) || !isset($_POST["username"]) || !isset($_POST["password"])) {
    countAttempt();
    diePretty("Missing form data.<br><a href='activate/'>Try Again</a>");
}

$useCode  = strtoupper(trim($_POST["activationCode"]));
$username = trim($_POST["username"]);
$password = $_POST["password"];

if (empty($useCode) || empty($username) || empty($password)) {
    countAttempt();
    diePretty("All fields are required.<br><a href='activate/'>Try Again</a>");
}

if (!is_file($cachePath . $useCode . ".json")) {
    countAttempt();
    error_log("Instapaper auth: activation code cache file not found: " . $cachePath . $useCode . ".json");
    diePretty("Unknown or expired activation code.<br>Get a fresh code from your webOS device, then <a href='activate/'>Try Again</a>");
}

$auth   = new InstapaperAuth($consumerKey, $consumerSecret);
$result = $auth->xAuth($username, $password);

if ($result && isset($result['oauth_token']) && isset($result['oauth_token_secret'])) {
    debugEcho("<i>xAuth succeeded for user: </i>" . htmlspecialchars($result['username']) . "<br>");
    if ($cache->updateLoginCache($useCode, $cachePath, $result['username'], $result['oauth_token'], $result['oauth_token_secret'])) {
        succeedPretty("<b>Logged in!</b><br/><br/>Now press the <b>Verify</b> button on your webOS device to complete sign-in.<br><br>");
    } else {
        diePretty("ERROR updating login cache. Please try again.");
    }
} else {
    countAttempt();
    diePretty("Login failed. Check your Instapaper username and password, then <a href='activate/'>Try Again</a>.");
}

function countAttempt() {
    if (isset($_SESSION["attempts"])) {
        $_SESSION["attempts"] = $_SESSION["attempts"] + 1;
    } else {
        $_SESSION["attempts"] = 0;
    }
}
?>
