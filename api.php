<?php
// ══════════════════════════════════════════════════
//  api.php — نسخة MongoDB  (يحل محل api.php القديم)
//  يحفظ نتائج المشاركين في مجموعة results
// ══════════════════════════════════════════════════

ob_start();

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    ob_end_clean();
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/config.php';

function sendJSON($data, $code = 200) {
    ob_end_clean();
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

if (!class_exists('MongoDB\Client')) {
    sendJSON(['error' => 'امتداد MongoDB PHP غير مثبّت'], 500);
}

try {
    $client     = new MongoDB\Client(MONGO_URI);
    $db         = $client->selectDatabase(MONGO_DB);
    $collection = $db->selectCollection('results');
} catch (Exception $e) {
    sendJSON(['error' => 'فشل الاتصال: ' . $e->getMessage()], 500);
}

$method = $_SERVER['REQUEST_METHOD'];

// ── GET ──────────────────────────────────────────
if ($method === 'GET') {
    $cursor  = $collection->find([], ['projection' => ['_id' => 0]]);
    $records = array_map(fn($r) => (array)$r, iterator_to_array($cursor, false));
    sendJSON($records);
}

// ── POST ─────────────────────────────────────────
if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input || empty($input['name'])) {
        sendJSON(['error' => 'بيانات غير صحيحة'], 400);
    }

    $doc = [
        'name'  => htmlspecialchars($input['name'], ENT_QUOTES, 'UTF-8'),
        'score' => (int)($input['score'] ?? 0),
        'total' => (int)($input['total'] ?? 0),
        'pct'   => (int)($input['pct']   ?? 0),
        'date'  => date('Y-m-d H:i'),
    ];

    $collection->insertOne($doc);
    $count = (int)$collection->countDocuments();

    sendJSON(['success' => true, 'count' => $count]);
}

// ── DELETE ───────────────────────────────────────
if ($method === 'DELETE') {
    $collection->deleteMany([]);
    sendJSON(['success' => true]);
}

sendJSON(['error' => 'الطريقة غير مدعومة'], 405);
