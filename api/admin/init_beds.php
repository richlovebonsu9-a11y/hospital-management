<?php
// API: Initialize Beds Table
session_start();
require_once __DIR__ . '/../../src/lib/Supabase.php';
use App\Lib\Supabase;

// Admin check (simple session check)
if (isset($_COOKIE['sb_user'])) { $_SESSION['user'] = json_decode($_COOKIE['sb_user'], true); }
if (!isset($_SESSION['user']) || ($_SESSION['user']['user_metadata']['role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized. Admin access required.']);
    exit;
}

$sb = new Supabase();

$sql = "
-- 1. Create beds table
CREATE TABLE IF NOT EXISTS beds (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    ward_id UUID REFERENCES wards(id) ON DELETE CASCADE,
    bed_number TEXT NOT NULL,
    status TEXT DEFAULT 'available' CHECK (status IN ('available', 'occupied', 'maintenance')),
    last_occupied_at TIMESTAMPTZ,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    UNIQUE(ward_id, bed_number)
);

-- 2. Populate beds for all existing wards dynamically
INSERT INTO beds (ward_id, bed_number)
SELECT 
    id, 
    CASE 
        WHEN ward_name = 'ICU' THEN 'ICU'
        ELSE UPPER(SUBSTR(ward_name, 1, 3)) 
    END || '-' || LPAD(s.n::text, 2, '0')
FROM wards, generate_series(1, total_beds) AS s(n)
ON CONFLICT DO NOTHING;

-- 3. Enable RLS
ALTER TABLE beds ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS \"Beds are viewable by authenticated users\" ON beds;
CREATE POLICY \"Beds are viewable by authenticated users\" ON beds FOR SELECT USING (true);
DROP POLICY IF EXISTS \"Admins can manage beds\" ON beds;
DROP POLICY IF EXISTS \"Staff can manage beds\" ON beds;
CREATE POLICY \"Staff can manage beds\" ON beds FOR ALL TO authenticated 
USING (auth.jwt() -> 'user_metadata' ->> 'role' IN ('admin', 'doctor', 'nurse'));

-- 4. Enable RLS for Wards (Ensuring doctors/nurses can update occupancy)
ALTER TABLE wards ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS \"Wards are viewable by all authenticated users\" ON wards;
CREATE POLICY \"Wards are viewable by all authenticated users\" ON wards FOR SELECT USING (true);
DROP POLICY IF EXISTS \"Admins can manage wards\" ON wards;
DROP POLICY IF EXISTS \"Staff can manage wards\" ON wards;
CREATE POLICY \"Staff can manage wards\" ON wards FOR ALL TO authenticated 
USING (auth.jwt() -> 'user_metadata' ->> 'role' IN ('admin', 'doctor', 'nurse'));
";

// Execute migration
$res = $sb->request('POST', '/rest/v1/rpc/exec_sql', ['query' => $sql], true);

header('Content-Type: text/html');
if ($res['status'] === 200 || $res['status'] === 204) {
    echo "<h1>Success!</h1><p>Beds table initialized and populated successfully.</p>";
    echo "<p><a href='/api/admin/reconcile_beds.php' style='display:inline-block; padding:10px 20px; background:#1a237e; color:white; text-decoration:none; border-radius:5px;'>Sync with Active Admissions Now</a></p>";
    echo "<p><a href='/dashboard_admin.php'>Return to Dashboard</a></p>";
} else {
    echo "<h1 style='color:red;'>Migration Failed</h1>";
    echo "<p>Status Code: " . $res['status'] . "</p>";
    echo "<p>Error Detail: " . json_encode($res['data'] ?? 'No detail available') . "</p>";
    echo "<h3>If this continues to fail:</h3>";
    echo "<p>Please copy the SQL below and run it manually in your <b>Supabase SQL Editor</b>:</p>";
    echo "<pre style='background:#f4f4f4; padding:15px; border-radius:8px; overflow:auto;'>" . htmlspecialchars($sql) . "</pre>";
}
