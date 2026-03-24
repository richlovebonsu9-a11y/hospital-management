<?php
// API: Migrate Admissions Table (Add anticipated_days)
session_start();
require_once __DIR__ . '/../../src/lib/Supabase.php';
use App\Lib\Supabase;

// Admin check
if (isset($_COOKIE['sb_user'])) { $_SESSION['user'] = json_decode($_COOKIE['sb_user'], true); }
if (!isset($_SESSION['user']) || ($_SESSION['user']['user_metadata']['role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo "Unauthorized. Admin access required.";
    exit;
}

$sb = new Supabase();

$sql = "ALTER TABLE admissions ADD COLUMN IF NOT EXISTS anticipated_days INTEGER DEFAULT 1;";

// Execute migration
$res = $sb->request('POST', '/rest/v1/rpc/exec_sql', ['query' => $sql], true);

header('Content-Type: text/html');
if ($res['status'] === 200 || $res['status'] === 204) {
    echo "<h1>Migration Success!</h1><p>Column 'anticipated_days' added to 'admissions' table successfully.</p>";
    echo "<p><a href='/dashboard_admin.php'>Return to Dashboard</a></p>";
} else {
    echo "<h1 style='color:red;'>Migration Failed</h1>";
    echo "<p>Status Code: " . $res['status'] . "</p>";
    echo "<p>Error Detail: " . json_encode($res['data'] ?? 'No detail available') . "</p>";
    echo "<h3>Manual Fix:</h3>";
    echo "<p>Please run this SQL in your <b>Supabase SQL Editor</b>:</p>";
    echo "<pre style='background:#f4f4f4; padding:15px; border-radius:8px; overflow:auto;'>$sql</pre>";
}
