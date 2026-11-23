// Read-only list of quote requests for the Eleven Fashion team. Protected by ADMIN_PASSWORD (env).
const crypto = require('crypto');
const { list } = require('@vercel/blob');

function ok(given) {
  const pw = process.env.ADMIN_PASSWORD || '';
  if (!pw || !given) return false;
  const a = crypto.createHash('sha256').update(String(given)).digest();
  const b = crypto.createHash('sha256').update(pw).digest();
  return crypto.timingSafeEqual(a, b);
}

module.exports = async (req, res) => {
  res.setHeader('Cache-Control', 'no-store');
  if (req.method !== 'POST') return res.status(405).json({ error: 'Method not allowed' });
  if (!process.env.ADMIN_PASSWORD) return res.status(503).json({ error: 'Admin is not set up yet (ADMIN_PASSWORD missing).' });
  if (!ok((req.body || {}).password)) return res.status(401).json({ error: 'Wrong password.' });
  try {
    const out = [];
    let cursor;
    do {
      const r = await list({ prefix: 'quotes/', cursor, limit: 1000 });
      out.push(...r.blobs);
      cursor = r.hasMore ? r.cursor : undefined;
    } while (cursor && out.length < 3000);
    out.sort((a, b) => new Date(b.uploadedAt) - new Date(a.uploadedAt));
    const recent = out.slice(0, 200);
    const quotes = await Promise.all(recent.map((b) => fetch(b.url).then((r) => r.json()).catch(() => null)));
    return res.status(200).json({ total: out.length, quotes: quotes.filter(Boolean) });
  } catch (e) {
    console.error('admin list failed', e && e.message);
    return res.status(500).json({ error: 'Could not load quotes.' });
  }
};
