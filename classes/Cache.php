<?php

class Cache {
    public function __construct() {

    }
    function createLoginCache($codeObj, $cachePath) {
        $file = $cachePath . $codeObj->code . ".json";
        try {
            $fileWriter = fopen($file, "w");
            fwrite($fileWriter, json_encode($codeObj));
            fclose($fileWriter); 
            return true;   
        } catch (Exception $ex) {
            error_log("Instapaper auth: could not create cache file: " . $ex->getMessage(), 0);
            return false;
        }
    }

    function updateLoginCache($code, $cachePath, $username, $oauthToken, $oauthTokenSecret) {
        $this->cleanUp($cachePath);
        //Read cache for update
        $codeObj = false;
        try {
            $file = $cachePath . $code . ".json";
            $str = file_get_contents($file);
            $codeObj = json_decode($str, false);
            $codeObj->username           = $username;
            $codeObj->oauth_token        = $oauthToken;
            $codeObj->oauth_token_secret = $oauthTokenSecret;
        } catch (Exception $ex) {
            error_log("Instapaper auth: could not read cache file for update: " . $ex->getMessage(), 0);
            return false;
        }
        //Write updated cache
        if ($codeObj) {
            try {
                $fileWriter = fopen($file, "w");
                fwrite($fileWriter, json_encode($codeObj));
                fclose($fileWriter);
                return true;
            } catch (Exception $ex) {
                error_log("Instapaper auth: could not write cache file for update: " . $ex->getMessage(), 0);
                return false;
            }
        }
    }

    // Remove cache file after the device has claimed its tokens (one-time pickup).
    function removeActivatedLogin($code, $cachePath) {
        $this->cleanUp($cachePath);
        $removeFile = $cachePath . $code . ".json";
        if (is_file($removeFile)) {
            unlink($removeFile);
        }
    }

    function cleanUp($cachepath) {
        clearstatcache();
        try {
            if ($handle = opendir($cachepath)) {
                while (false !== ($file = readdir($handle))) {
                    if ('.' === $file) continue;
                    if ('..' === $file) continue;
                    //echo $file . date ("F d Y H:i:s", filemtime($cachepath . $file));
                    if (time()-filemtime($cachepath . $file) > 2 * 3600) {
                        //echo "- delete old file!<br>";
                        unlink($cachepath . $file);
                    } else {
                        //echo "- keep young file!<br>";
                    }
                }
                closedir($handle);
                return true;
            }
        } catch (Exception $ex) {
            error_log("Instapaper auth: could not cleanup cache folder: " . $ex->getMessage(), 0);
            return false;
        }
    }
}
?>