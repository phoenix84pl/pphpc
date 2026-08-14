<?php

namespace Phoenix\Core\Library;

/**
 * Klasa do obsługi wysyłania tweetów przez Twitter API v2 (OAuth 1.0a User Context)
 * 
 * 20250701 Start klasy
 * 20260814 Przeniesienie do Core (Phoenix\Core\Library\Twitter) i refaktoryzacja PSR-12
 */
class Twitter
{
    private string $consumerKey;
    private string $consumerSecret;
    private string $accessToken;
    private string $accessTokenSecret;

    public ?string $error = null;

    public function __construct(
        string $consumerKey,
        string $consumerSecret,
        string $accessToken,
        string $accessTokenSecret
    ) {
        $this->consumerKey = $consumerKey;
        $this->consumerSecret = $consumerSecret;
        $this->accessToken = $accessToken;
        $this->accessTokenSecret = $accessTokenSecret;
    }

    /**
     * Wysyła tweet na konto połączone z tokenem
     */
    public function tweet(string $message): bool
    {
        $url = 'https://api.twitter.com/2/tweets';
        
        $oauth = [
            'oauth_consumer_key'     => $this->consumerKey,
            'oauth_token'            => $this->accessToken,
            'oauth_nonce'            => bin2hex(random_bytes(16)),
            'oauth_timestamp'        => (string)time(),
            'oauth_signature_method' => 'HMAC-SHA1',
            'oauth_version'          => '1.0',
        ];

        // Dane do wysyłki w formacie JSON
        $postData = json_encode(['text' => $message], JSON_UNESCAPED_UNICODE);

        // Parametry do podpisu OAuth (w OAuth 1.0a dla treści JSON parametry z body nie wchodzą w podpis)
        $params = $oauth;
        ksort($params);

        // Budowanie base stringa dla podpisu
        $encodedParams = [];
        foreach ($params as $k => $v) {
            $encodedParams[rawurlencode((string)$k)] = rawurlencode((string)$v);
        }
        
        $baseString = 'POST&' . rawurlencode($url) . '&' . rawurlencode(http_build_query($encodedParams, '', '&', PHP_QUERY_RFC3986));
        $signingKey = rawurlencode($this->consumerSecret) . '&' . rawurlencode($this->accessTokenSecret);
        
        $oauth['oauth_signature'] = base64_encode(hash_hmac('sha1', $baseString, $signingKey, true));

        // Generowanie nagłówka Authorization
        $headerParts = [];
        foreach ($oauth as $k => $v) {
            $headerParts[] = rawurlencode((string)$k) . '="' . rawurlencode((string)$v) . '"';
        }
        $authHeader = 'OAuth ' . implode(', ', $headerParts);

        // Zapytanie cURL
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: ' . $authHeader,
            'Content-Type: application/json',
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        // API v2 zwraca HTTP 201 przy udanym utworzeniu wpisu
        if ($httpCode !== 201) {
            $this->error = "HTTP: {$httpCode}, Odpowiedź: {$response}, cURL błąd: {$curlError}";
            return false;
        }

        return true;
    }
}