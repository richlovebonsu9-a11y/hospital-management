<?php
session_start();
require_once __DIR__ . '/../../src/lib/Supabase.php';
use App\Lib\Supabase;

$sb = new Supabase();

// Execute SQL migration via RPC
$sql = "
-- 1. Update profiles table role constraint to include ambulance and dispatch_rider
ALTER TABLE profiles DROP CONSTRAINT IF EXISTS profiles_role_check;
ALTER TABLE profiles ADD CONSTRAINT profiles_role_check CHECK (role IN ('admin', 'doctor', 'nurse', 'pharmacist', 'technician', 'patient', 'guardian', 'ambulance', 'dispatch_rider'));

-- 2. Update emergencies table with new columns for routing and resolution
ALTER TABLE emergencies ADD COLUMN IF NOT EXISTS emergency_type TEXT;
ALTER TABLE emergencies ADD COLUMN IF NOT EXISTS assigned_to UUID REFERENCES profiles(id);
ALTER TABLE emergencies ADD COLUMN IF NOT EXISTS escalation_required BOOLEAN DEFAULT FALSE;
ALTER TABLE emergencies ADD COLUMN IF NOT EXISTS response_fee DECIMAL(10,2) DEFAULT 0;
";

$res = $sb->request('POST', '/rpc/exec_sql', ['sql' => $sql], true);

if ($res['status'] >= 200 && $res['status'] < 300) {
    echo json_encode(['success' => true, 'message' => 'Emergency system migration successful']);
} else {
    echo json_encode(['success' => false, 'error' => $res['data']['message'] ?? 'Migration failed', 'details' => $res]);
}
