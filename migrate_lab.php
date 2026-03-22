<?php
require_once __DIR__ . '/src/lib/Supabase.php';
use App\Lib\Supabase;

$sb = new Supabase();
$queries = [
    "ALTER TABLE lab_requests ADD COLUMN IF NOT EXISTS requester_id UUID REFERENCES profiles(id);",
    "ALTER TABLE lab_requests ENABLE ROW LEVEL SECURITY;"
];

foreach ($queries as $sql) {
    echo "Executing: $sql\n";
    $res = $sb->request('POST', '/rest/v1/rpc/exec_sql', ['query' => $sql], true);
    if ($res['status'] !== 200) {
        // If exec_sql RPC is not available, we can't directly execute DDL from the API easily.
        echo "Error or RPC not available: " . print_r($res, true) . "\n";
    } else {
        echo "Success.\n";
    }
}
echo "Done.\n";
