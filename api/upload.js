// Logo / artwork upload. The browser sends the file as the raw request body.
// Stored in Vercel Blob under a random name; the URL goes into the quote request.
const { put } = require('@vercel/blob');

const MAX = 4 * 1024 * 1024; // Vercel function body limit is 4.5 MB
const ALLOWED = {
  'image/png': 'png', 'image/jpeg': 'jpg', 'image/svg+xml': 'svg', 'image/webp': 'webp',
  'application/pdf': 'pdf', 'application/postscript': 'ai', 'application/illustrator': 'ai',
  'application/eps': 'eps', 'image/x-eps': 'eps',
};
const EXT_OK = /\.(png|jpe?g|svg|webp|pdf|ai|eps)$/i;

function readBody(req) {
  return new Promise((resolve, reject) => {
    const chunks = []; let size = 0;
    req.on('data', (c) => { size += c.length; if (size > MAX) { reject(new Error('too_big')); req.destroy(); } else chunks.push(c); });
    req.on('end', () => resolve(Buffer.concat(chunks)));
    req.on('error', reject);
  });
}

module.exports = async (req, res) => {
  if (req.method !== 'POST') return res.status(405).json({ error: 'Method not allowed' });
  if (!process.env.BLOB_READ_WRITE_TOKEN) return res.status(503).json({ error: 'Uploads are not available yet. Please add a link to your logo instead.' });
  const name = String(req.headers['x-filename'] || 'logo').replace(/[^\w.\-]+/g, '_').slice(-80);
  const type = String(req.headers['content-type'] || '').split(';')[0].trim().toLowerCase();
  if (!ALLOWED[type] && !EXT_OK.test(name)) return res.status(400).json({ error: 'Please upload a PNG, JPG, SVG, PDF, AI or EPS file.' });
  let body;
  try { body = await readBody(req); } catch (e) {
    return res.status(413).json({ error: 'File is larger than 4 MB. Please add a download link (WeTransfer, Google Drive) instead.' });
  }
  if (!body.length) return res.status(400).json({ error: 'Empty file.' });
  try {
    const blob = await put(`logos/${name}`, body, { access: 'public', addRandomSuffix: true, contentType: type || undefined });
    return res.status(200).json({ url: blob.url, name, size: body.length });
  } catch (e) {
    console.error('upload failed', e && e.message);
    return res.status(500).json({ error: 'Upload failed. Please try again or add a link instead.' });
  }
};

module.exports.config = { api: { bodyParser: false } };
