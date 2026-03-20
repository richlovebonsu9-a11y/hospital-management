<?php

namespace App\Lib;

class Supabase {
    private $url;
    private $anonKey;
    private $serviceKey;

    public function __construct() {
        $this->url = getenv('SUPABASE_URL');
        $this->anonKey = getenv('SUPABASE_ANON_KEY');
        $this->serviceKey = getenv('SUPABASE_SERVICE_ROLE_KEY');
    }

    private function request($method, $path, $data = null, $useServiceKey = false) {
        $url = $this->url . $path;
        $ch = curl_init($url);
        
        $headers = [
            'apikey: ' . ($useServiceKey ? $this->serviceKey : $this->anonKey),
            'Content-Type: application/json',
            'Authorization: Bearer ' . ($useServiceKey ? $this->serviceKey : $this->anonKey)
        ];

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

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
