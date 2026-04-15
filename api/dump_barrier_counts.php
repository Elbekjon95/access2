<?php
require_once 'c:/OSPanel/home/acsess4/config.php';
$output = "";

function auditDb($dbName, $label) {
    global $output, $pdo;
    $output .= "=== AUDIT: $label (DB: $dbName) ===\n";
    try {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=$dbName;charset=utf8mb4";
        $db = new PDO($dsn, DB_USER, DB_PASS);
        
        $stmt = $db->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $output .= "Tables: " . implode(", ", $tables) . "\n";
        
        if (in_array('map_barriers', $tables)) {
            $stmt = $db->query("SELECT map_id, COUNT(*) as cnt FROM map_barriers GROUP BY map_id");
            while($r = $stmt->fetch()) {
                $output .= "  [map_barriers] Map ID: " . $r['map_id'] . " Count: " . $r['cnt'] . "\n";
            }
        }
        
        if (in_array('map_points', $tables)) {
            $stmt = $db->query("SELECT map_id, COUNT(*) as cnt FROM map_points GROUP BY map_id");
            while($r = $stmt->fetch()) {
                $output .= "  [map_points] Map ID: " . $r['map_id'] . " Count: " . $r['cnt'] . "\n";
            }
        }
    } catch (Exception $e) {
        $output .= "Error: " . $e->getMessage() . "\n";
    }
    $output .= "\n";
}

auditDb('acsess', 'FRO DB');
auditDb('acsess4', 'CURRENT DB');

file_put_contents('c:/OSPanel/home/acsess4/api/audit_results.txt', $output);
echo "Audit complete. Check audit_results.txt\n";
