<?php
// Debug script to check environment configuration on Vercel
require_once __DIR__ . '/../src/lib/Supabase.php';

echo "<h2>KMG Hospital - Authentication Debugger</h2>";

function mask($str) {
    if (!$str) return "<i>(empty)</i>";
    $len = strlen($str);
    if ($len < 8) return "******** (too short?)";
    return substr($str, 0, 4) . "..." . substr($str, -4) . " (Length: $len)";
}

$vars = ['SUPABASE_URL', 'SUPABASE_ANON_KEY', 'SUPABASE_SERVICE_ROLE_KEY', 'ADMIN_SECRET_KEY'];
echo "<ul>";
foreach ($vars as $v) {
    $val = getenv($v) ?: ($_ENV[$v] ?? ($_SERVER[$v] ?? null));
    echo "<li><strong>$v:</strong> " . mask($val) . "</li>";
}
echo "</ul>";

if (isset($_COOKIE['sb_user'])) {
    echo "<p style='color: green;'>✅ <b>Cookie Found:</b> sb_user is present.</p>";
} else {
    echo "<p style='color: orange;'>⚠️ <b>Cookie Missing:</b> sb_user is NOT present. Authentication will fail on redirect.</p>";
}

echo "<p><b>Protocol:</b> " . ($_SERVER['HTTPS'] ?? 'HTTP') . "</p>";

echo "<h3>Diagnostic Test</h3>";
$ch = curl_init("https://www.google.com");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
$res = curl_exec($ch);
if ($res === false) {
    echo "<p style='color: red;'>❌ <b>CURL Test Failed:</b> " . curl_error($ch) . "<br>This server might have outbound request restrictions.</p>";
} else {
    echo "<p style='color: green;'>✅ <b>CURL Test Success:</b> Outbound requests are allowed.</p>";
}
curl_close($ch);
