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
CREATE POLICY \"Admins can manage beds\" ON beds FOR ALL TO authenticated USING (auth.jwt() -> 'user_metadata' ->> 'role' = 'admin');
";

echo "Attempting to execute SQL migration...\n";

// Execute migration
$res = $sb->request('POST', '/rest/v1/rpc/exec_sql', ['query' => $sql], true);

header('Content-Type: application/json');
if ($res['status'] === 200) {
    echo json_encode(['success' => true, 'message' => 'Beds table initialized and populated successfully.']);
} else {
    echo json_encode(['success' => false, 'error' => 'Migration failed. Ensure the exec_sql RPC is enabled in Supabase.', 'debug' => $res]);
}
