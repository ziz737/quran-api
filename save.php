<?php
// ══════════════════════════════════════════════════
//  save.php — نسخة MongoDB
//  يدعم: GET / POST / DELETE
//  ?type=participants  أو  ?type=questions
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

// ── دالة الرد الموحّدة ───────────────────────────
function sendJSON($data, $code = 200) {
    ob_end_clean();
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// ── الاتصال بـ MongoDB ───────────────────────────
function getMongo() {
    try {
        $client = new MongoDB\Client(MONGO_URI);
        return $client->selectDatabase(MONGO_DB);
    } catch (Exception $e) {
        sendJSON(['error' => 'فشل الاتصال بـ MongoDB: ' . $e->getMessage()], 500);
    }
}

// ── التحقق من وجود امتداد MongoDB ───────────────
if (!class_exists('MongoDB\Client')) {
    sendJSON([
        'error'   => 'امتداد MongoDB PHP غير مثبّت',
        'install' => 'composer require mongodb/mongodb  أو  pecl install mongodb',
    ], 500);
}

// ── نوع البيانات ─────────────────────────────────
$type = isset($_GET['type']) ? trim($_GET['type']) : 'participants';

$allowedTypes = [
    'participants' => COL_PARTICIPANTS,
    'questions'    => COL_QUESTIONS,
];

if (!array_key_exists($type, $allowedTypes)) {
    sendJSON(['error' => 'نوع غير معروف. استخدم: participants أو questions'], 400);
}

$db         = getMongo();
$collection = $db->selectCollection($allowedTypes[$type]);
$method     = $_SERVER['REQUEST_METHOD'];

// ════════════════════════════════════════════════
//  GET — جلب البيانات
// ════════════════════════════════════════════════
if ($method === 'GET') {

    $cursor  = $collection->find([], ['projection' => ['_id' => 0]]);
    $records = iterator_to_array($cursor, false);

    // تحويل BSON إلى مصفوفات PHP عادية
    $records = array_map(fn($r) => (array)$r, $records);

    if ($type === 'participants') {
        $total   = count($records);
        $perfect = count(array_filter($records, fn($r) => (int)($r['pct'] ?? 0) === 100));
        $avgPct  = $total ? round(array_sum(array_column($records, 'pct')) / $total, 1) : 0;

        sendJSON([
            'records'    => $records,
            'total'      => $total,
            'perfect100' => $perfect,
            'avg_pct'    => $avgPct,
        ]);
    }

    sendJSON($records);
}

// ════════════════════════════════════════════════
//  POST — إضافة / استيراد
// ════════════════════════════════════════════════
if ($method === 'POST') {

    $raw   = file_get_contents('php://input');
    $input = json_decode($raw, true);

    if ($input === null) {
        sendJSON(['error' => 'JSON غير صحيح: ' . json_last_error_msg()], 400);
    }

    // ── participants ─────────────────────────────
    if ($type === 'participants') {
        if (empty($input['name'])) {
            sendJSON(['error' => 'حقل name مطلوب'], 400);
        }

        $doc = [
            'id'    => uniqid('p_', true),
            'name'  => htmlspecialchars(trim($input['name']), ENT_QUOTES, 'UTF-8'),
            'score' => (int)($input['score'] ?? 0),
            'total' => (int)($input['total'] ?? 0),
            'pct'   => (int)($input['pct']   ?? 0),
            'date'  => date('Y-m-d H:i'),
        ];

        $collection->insertOne($doc);
        $count = $collection->countDocuments();

        sendJSON(['success' => true, 'count' => (int)$count]);
    }

    // ── questions ────────────────────────────────
    if ($type === 'questions') {

        // استيراد جماعي (bulk)
        $isBulk = (is_array($input) && isset($input[0]))
               || (isset($input['questions']) && is_array($input['questions']));

        if ($isBulk) {
            $bulk    = isset($input['questions']) ? $input['questions'] : $input;
            $cleaned = [];

            foreach ($bulk as $q) {
                if (empty($q['ayah']) || empty($q['answer'])) continue;
                $cleaned[] = [
                    'id'      => $q['id'] ?? uniqid('q_', true),
                    'surah'   => htmlspecialchars($q['surah']  ?? '', ENT_QUOTES, 'UTF-8'),
                    'ayah'    => $q['ayah'],
                    'answer'  => $q['answer'],
                    'options' => array_values((array)($q['options'] ?? [])),
                    'added'   => $q['added'] ?? date('Y-m-d'),
                ];
            }

            if (empty($cleaned)) {
                sendJSON(['error' => 'لا توجد أسئلة صحيحة للاستيراد'], 400);
            }

            // حذف القديمة ثم إدراج الجديدة
            $collection->deleteMany([]);
            $collection->insertMany($cleaned);

            sendJSON(['success' => true, 'count' => count($cleaned), 'action' => 'bulk_import']);
        }

        // سؤال واحد
        if (empty($input['ayah']) || empty($input['answer'])) {
            sendJSON(['error' => 'الحقول المطلوبة: ayah, answer'], 400);
        }

        // التحقق من التكرار
        $exists = $collection->findOne(
            ['ayah' => trim($input['ayah'])],
            ['projection' => ['_id' => 0]]
        );
        if ($exists) {
            sendJSON(['error' => 'السؤال موجود مسبقاً', 'duplicate' => true], 409);
        }

        $doc = [
            'id'      => uniqid('q_', true),
            'surah'   => htmlspecialchars($input['surah'] ?? '', ENT_QUOTES, 'UTF-8'),
            'ayah'    => $input['ayah'],
            'answer'  => $input['answer'],
            'options' => array_values((array)($input['options'] ?? [])),
            'added'   => date('Y-m-d'),
        ];

        $collection->insertOne($doc);
        $count = $collection->countDocuments();

        sendJSON(['success' => true, 'count' => (int)$count, 'action' => 'added']);
    }
}

// ════════════════════════════════════════════════
//  DELETE — حذف سجل أو مسح الكل
// ════════════════════════════════════════════════
if ($method === 'DELETE') {

    $raw   = file_get_contents('php://input');
    $input = json_decode($raw, true) ?? [];

    if (!empty($input['id'])) {
        $result  = $collection->deleteOne(['id' => $input['id']]);
        $deleted = $result->getDeletedCount();
        $remain  = (int)$collection->countDocuments();

        sendJSON(['success' => true, 'deleted' => $deleted, 'remaining' => $remain]);
    }

    // مسح الكل
    $collection->deleteMany([]);
    sendJSON(['success' => true, 'action' => 'cleared']);
}

sendJSON(['error' => 'الطريقة غير مدعومة'], 405);
