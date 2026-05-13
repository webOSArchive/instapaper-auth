<?php
include("config.php");
include("common.php");
spl_autoload_register(function($classes){
    include 'classes/'.$classes.".php";
  });
$cache = new Cache();
  
header('Content-type: application/json; charset=utf-8');

if (!isset($_GET['code'])) {
    die("{\"error\":\"No activation code in query.\"}");
}

$codeObj = false;
$code = strtoupper($_GET['code']);
$file = $cachePath . $code . ".json";
if (is_file($file)) {
    try {
        $str = file_get_contents($file);
        $codeObj = json_decode($str, false);
        echo json_encode($codeObj);
    } catch (Exception $ex) {
        die("{\"error\":\"Unable to open activation code cache file.\"}");
    }
    if ($codeObj && isset($codeObj->oauth_token) && isset($codeObj->username))
        $cache->removeActivatedLogin($code, $cachePath);
} else {
    die("{\"error\":\"Activation code not found or expired.\"}");
}

?>