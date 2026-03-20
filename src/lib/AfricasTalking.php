<?php

namespace App\Lib;

class AfricasTalking {
    private $username;
    private $apiKey;
    private $apiUrl = "https://api.africastalking.com/version1/messaging";

    public function __construct() {
        $this->username = getenv('AFRICASTALKING_USERNAME');
        $this->apiKey = getenv('AFRICASTALKING_API_KEY');
    }

    public function sendSms($to, $message) {
        $ch = curl_init($this->apiUrl);
        
        $data = [
            'username' => $this->username,
            'to' => $to,
            'message' => $message
        ];

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'apikey: ' . $this->apiKey,
            'Accept: application/json'
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return [
            'status' => $httpCode,
            'data' => json_decode($response, true)
        ];
    }
}
