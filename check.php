<?php
// ══════════════════════════════════════════════════
//  check.php — نسخة MongoDB
//  افتحه في المتصفح للتحقق من أن كل شيء يعمل
// ══════════════════════════════════════════════════

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/config.php';

$results = [];

// 1. إصدار PHP
$results['php_version'] = PHP_VERSION;

// 2. هل امتداد MongoDB مثبّت؟
$results['mongodb_extension'] = extension_loaded('mongodb')
    ? '✅ مثبّت (v' . phpversion('mongodb') . ')'
    : '❌ غير مثبّت — نفّذ: pecl install mongodb';

// 3. هل مكتبة Composer موجودة؟
$results['mongodb_library'] = class_exists('MongoDB\Client')
    ? '✅ موجودة'
    : '❌ غير موجودة — نفّذ: composer require mongodb/mongodb';

// 4. محاولة الاتصال
if (class_exists('MongoDB\Client')) {
    try {
        $client = new MongoDB\Client(MONGO_URI);

        // ping لاختبار الاتصال الفعلي
        $client->selectDatabase(MONGO_DB)->command(['ping' => 1]);

        $results['connection'] = '✅ متصل بنجاح';
        $results['database']   = MONGO_DB;

        // عدد السجلات في كل مجموعة
        $db = $client->selectDatabase(MONGO_DB);
        foreach ([COL_PARTICIPANTS, COL_QUESTIONS, 'results'] as $col) {
            $results['collections'][$col] = (int)$db->selectCollection($col)->countDocuments();
        }

    } catch (Exception $e) {
        $results['connection'] = '❌ فشل: ' . $e->getMessage();
    }
} else {
    $results['connection'] = '⏭ تم التخطي (المكتبة غير موجودة)';
}

// 5. معلومات إضافية
$results['mongo_uri_set'] = str_contains(MONGO_URI, 'USERNAME') ? '⚠ لم يُعدَّل بعد' : '✅ تم الضبط';
$results['server']        = $_SERVER['SERVER_SOFTWARE'] ?? 'unknown';

echo json_encode($results, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
