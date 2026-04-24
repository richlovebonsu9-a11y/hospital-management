<?php

namespace App\Lib;

class Supabase {
    private $url;
    private $anonKey;
    private $serviceKey;

    public function __construct() {
        $this->url = $this->getEnvVar('SUPABASE_URL');
        $this->anonKey = $this->getEnvVar('SUPABASE_ANON_KEY');
        $this->serviceKey = $this->getEnvVar('SUPABASE_SERVICE_ROLE_KEY');

        if (empty($this->url)) {
            // Throwing an exception is cleaner, but if the app doesn't catch it, 
            // maybe just log it or handle it in the request method.
        }
    }

    private function getEnvVar($key) {
        $val = getenv($key);
        if ($val !== false) return $val;
        if (isset($_ENV[$key])) return $_ENV[$key];
        if (isset($_SERVER[$key])) return $_SERVER[$key];
        return null;
    }

    public function request($method, $path, $data = null, $useServiceKey = false, $extraHeaders = []) {
        if (empty($this->url)) {
            return [
                'status' => 500,
                'data' => ['message' => 'SUPABASE_URL is not configured in environment variables.']
            ];
        }

        $url = $this->url . $path;
        $ch = curl_init($url);
        
        $key = $useServiceKey ? $this->serviceKey : $this->anonKey;
        if (empty($key)) {
            return [
                'status' => 500,
                'data' => ['message' => ($useServiceKey ? 'SUPABASE_SERVICE_ROLE_KEY' : 'SUPABASE_ANON_KEY') . ' is not configured.']
            ];
        }

        $headers = [
            'apikey: ' . $key,
            'Content-Type: application/json',
            'Authorization: Bearer ' . $key
        ];

        foreach ($extraHeaders as $key => $value) {
            $headers[] = "$key: $value";
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            return [
                'status' => 500,
                'data' => ['message' => 'CURL Error: ' . $curlError]
            ];
        }

        return [
            'status' => $httpCode,
            'data' => json_decode($response, true)
        ];
    }

    public function auth() {
        return new class($this) {
            private $supabase;
            public function __construct($supabase) { $this->supabase = $supabase; }

            public function signUp($email, $password, $metadata = []) {
                return $this->supabase->request('POST', '/auth/v1/signup', [
                    'email' => $email,
                    'password' => $password,
                    'data' => $metadata
                ]);
            }

            public function signIn($email, $password) {
                return $this->supabase->request('POST', '/auth/v1/token?grant_type=password', [
                    'email' => $email,
                    'password' => $password
                ]);
            }
        };
    }

    public function adminAuth() {
        return new class($this) {
            private $supabase;
            public function __construct($supabase) { $this->supabase = $supabase; }

            public function createUser($email, $password, $metadata = []) {
                // Supabase Admin API to create user without signing them in
                return $this->supabase->request('POST', '/auth/v1/admin/users', [
                    'email' => $email,
                    'password' => $password,
                    'user_metadata' => $metadata,
                    'email_confirm' => true
                ], true);
            }
        };
    }

    public function from($table) {
        return new class($this, $table) {
            private $supabase;
            private $table;
            public function __construct($supabase, $table) { 
                $this->supabase = $supabase;
                $this->table = $table;
            }

            public function select($columns = '*') {
                return $this->supabase->request('GET', '/rest/v1/' . $this->table . '?select=' . $columns);
            }

            public function insert($data) {
                return $this->supabase->request('POST', '/rest/v1/' . $this->table, $data);
            }
        };
    }
}
