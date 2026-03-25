const express = require('express');
const { MongoClient } = require('mongodb');
const cors = require('cors');

const app = express();
app.use(cors());
app.use(express.json());

const MONGO_URI = process.env.MONGO_URI;
const DB_NAME   = process.env.DB_NAME || 'quran_quiz';
const PORT      = process.env.PORT    || 3000;

let db;

// ── الاتصال بـ MongoDB ────────────────────────────
async function connectDB() {
  const client = new MongoClient(MONGO_URI);
  await client.connect();
  db = client.db(DB_NAME);
  console.log('✅ MongoDB connected');
}

// ════════════════════════════════════════════════
//  GET /results — جلب كل النتائج + إحصائيات
// ════════════════════════════════════════════════
app.get('/results', async (req, res) => {
  try {
    const records = await db.collection('participants')
      .find({}, { projection: { _id: 0 } })
      .toArray();

    const total    = records.length;
    const perfect  = records.filter(r => r.pct === 100).length;
    const avgPct   = total
      ? Math.round(records.reduce((s, r) => s + r.pct, 0) / total * 10) / 10
      : 0;

    res.json({ records, total, perfect100: perfect, avg_pct: avgPct });
  } catch (e) {
    res.status(500).json({ error: e.message });
  }
});

// ════════════════════════════════════════════════
//  POST /results — حفظ نتيجة مشارك
// ════════════════════════════════════════════════
app.post('/results', async (req, res) => {
  try {
    const { name, score, total, pct } = req.body;
    if (!name) return res.status(400).json({ error: 'حقل name مطلوب' });

    const doc = {
      id:    `p_${Date.now()}_${Math.random().toString(36).slice(2)}`,
      name:  String(name).trim().slice(0, 100),
      score: parseInt(score) || 0,
      total: parseInt(total) || 0,
      pct:   parseInt(pct)   || 0,
      date:  new Date().toISOString().slice(0, 16).replace('T', ' '),
    };

    await db.collection('participants').insertOne(doc);
    const count = await db.collection('participants').countDocuments();

    res.json({ success: true, count });
  } catch (e) {
    res.status(500).json({ error: e.message });
  }
});

// ════════════════════════════════════════════════
//  DELETE /results — مسح كل النتائج
// ════════════════════════════════════════════════
app.delete('/results', async (req, res) => {
  try {
    await db.collection('participants').deleteMany({});
    res.json({ success: true, action: 'cleared' });
  } catch (e) {
    res.status(500).json({ error: e.message });
  }
});

// ── تشغيل السيرفر ────────────────────────────────
connectDB()
  .then(() => app.listen(PORT, () => console.log(`🚀 Server running on port ${PORT}`)))
  .catch(err => { console.error('❌ DB connection failed:', err); process.exit(1); });
