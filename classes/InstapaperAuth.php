<?php

class InstapaperAuth {

    private $consumerKey;
    private $consumerSecret;

    public function __construct($consumerKey, $consumerSecret) {
        $this->consumerKey    = $consumerKey;
        $this->consumerSecret = $consumerSecret;
    }

    // Exchange username + password for an OAuth 1.0a access token via xAuth.
    // Returns array with oauth_token, oauth_token_secret, username on success.
    // Returns false on failure.
    public function xAuth($username, $password) {
        $url = 'https://www.instapaper.com/api/1/oauth/access_token';
        $bodyParams = array(
            'x_auth_mode'     => 'client_auth',
            'x_auth_password' => $password,
            'x_auth_username' => $username,
        );
        $oauthParams = $this->buildOAuthParams();
        $allParams   = array_merge($oauthParams, $bodyParams);
        $signature   = $this->buildSignature('POST', $url, $allParams, '');
        $oauthParams['oauth_signature'] = $signature;

        $authHeader = $this->buildAuthorizationHeader($oauthParams);
        $body       = http_build_query($bodyParams);
        $response   = $this->httpPost($url, $body, $authHeader);

        if ($response === false) {
            return false;
        }
        $result = array();
        parse_str($response, $result);
        if (!isset($result['oauth_token']) || !isset($result['oauth_token_secret'])) {
            error_log("InstapaperAuth: unexpected xAuth response: " . $response);
            return false;
        }
        // Instapaper returns username in the response
        if (!isset($result['username'])) {
            $result['username'] = $username;
        }
        return $result;
    }

    // Fetch the mobilized article HTML for a given bookmark_id.
    // Used by the get-text.php proxy.
    public function getBookmarkText($bookmarkId, $oauthToken, $oauthTokenSecret) {
        $url = 'https://www.instapaper.com/api/1.1/bookmarks/' . intval($bookmarkId) . '/get_text';
        $oauthParams = $this->buildOAuthParams($oauthToken);
        $signature   = $this->buildSignature('GET', $url, $oauthParams, $oauthTokenSecret);
        $oauthParams['oauth_signature'] = $signature;
        $authHeader = $this->buildAuthorizationHeader($oauthParams);
        return $this->httpGet($url, $authHeader);
    }

    // ---- OAuth helpers ----

    private function buildOAuthParams($token = '') {
        $params = array(
            'oauth_consumer_key'     => $this->consumerKey,
            'oauth_nonce'            => md5(uniqid(mt_rand(), true)),
            'oauth_signature_method' => 'HMAC-SHA1',
            'oauth_timestamp'        => time(),
            'oauth_version'          => '1.0',
        );
        if ($token !== '') {
            $params['oauth_token'] = $token;
        }
        return $params;
    }

    private function buildSignature($method, $url, $params, $tokenSecret) {
        ksort($params);
        $parts = array();
        foreach ($params as $k => $v) {
            $parts[] = rawurlencode($k) . '=' . rawurlencode($v);
        }
        $paramString = implode('&', $parts);
        $baseString  = strtoupper($method) . '&' . rawurlencode($url) . '&' . rawurlencode($paramString);
        $signingKey  = rawurlencode($this->consumerSecret) . '&' . rawurlencode($tokenSecret);
        return base64_encode(hash_hmac('sha1', $baseString, $signingKey, true));
    }

    private function buildAuthorizationHeader($params) {
        $parts = array();
        foreach ($params as $k => $v) {
            $parts[] = rawurlencode($k) . '="' . rawurlencode($v) . '"';
        }
        return 'OAuth ' . implode(', ', $parts);
    }

    private function httpPost($url, $body, $authHeader) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Authorization: ' . $authHeader,
            'Content-Type: application/x-www-form-urlencoded',
        ));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($httpCode !== 200) {
            error_log("InstapaperAuth httpPost: HTTP $httpCode from $url — $response");
            return false;
        }
        return $response;
    }

    private function httpGet($url, $authHeader) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Authorization: ' . $authHeader));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($httpCode !== 200) {
            error_log("InstapaperAuth httpGet: HTTP $httpCode from $url");
            return false;
        }
        return $response;
    }
}
?>
