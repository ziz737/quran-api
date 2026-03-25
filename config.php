<?php
// ══════════════════════════════════════════════════
//  إعدادات MongoDB — عدّل هذا الملف فقط
// ══════════════════════════════════════════════════

// Connection string من MongoDB Atlas
// مثال: mongodb+srv://user:pass@cluster0.xxxxx.mongodb.net/
define('MONGO_URI',    getenv('MONGO_URI') ?: 'mongodb+srv://quran_test:9aKzsIUstc1kQ7yf@ziz737.rukv0uk.mongodb.net/?appName=qurantest');

// اسم قاعدة البيانات
define('MONGO_DB',     getenv('MONGO_DB')  ?: 'quran_test');

// أسماء المجموعات (Collections)
define('COL_PARTICIPANTS', 'participants');
define('COL_QUESTIONS',    'questions');
