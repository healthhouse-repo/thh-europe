// Small helpers shared by the API routes: query/cookies, signed sessions, admin check, e-mail.
const crypto = require('crypto');

const SECRET = () => process.env.SESSION_SECRET || process.env.ADMIN_PASSWORD || 'demo-only-secret';
const SITE = () => (process.env.SITE_URL || '').replace(/\/$/, '');

function query(req) { return Object.fromEntries(new URL(req.url, 'http://x').searchParams); }
function cookies(req) {
  const out = {};
  String(req.headers.cookie || '').split(';').forEach((p) => { const i = p.indexOf('='); if (i > 0) out[p.slice(0, i).trim()] = decodeURIComponent(p.slice(i + 1).trim()); });
  return out;
}
function sign(obj, days) {
  const body = Buffer.from(JSON.stringify(Object.assign({}, obj, { exp: Date.now() + days * 864e5 }))).toString('base64url');
  const mac = crypto.createHmac('sha256', SECRET()).update(body).digest('base64url');
  return `${body}.${mac}`;
}
function unsign(tok) {
  if (!tok || tok.indexOf('.') < 0) return null;
  const [body, mac] = tok.split('.');
  const want = crypto.createHmac('sha256', SECRET()).update(body).digest('base64url');
  if (mac.length !== want.length || !crypto.timingSafeEqual(Buffer.from(mac), Buffer.from(want))) return null;
  try { const o = JSON.parse(Buffer.from(body, 'base64url').toString()); return o.exp > Date.now() ? o : null; } catch (e) { return null; }
}
function setCookie(res, name, val, days) {
  const secure = process.env.VERCEL ? '; Secure' : '';
  res.setHeader('Set-Cookie', `${name}=${encodeURIComponent(val)}; Path=/; HttpOnly; SameSite=Lax; Max-Age=${Math.round(days * 86400)}${secure}`);
}
function pwOk(given) {
  const pw = process.env.ADMIN_PASSWORD || '';
  if (!pw || !given) return false;
  const a = crypto.createHash('sha256').update(String(given)).digest();
  const b = crypto.createHash('sha256').update(pw).digest();
  return crypto.timingSafeEqual(a, b);
}
function isAdmin(req) { const s = unsign(cookies(req).ef_admin); return !!(s && s.admin); }
function customerSession(req) { const s = unsign(cookies(req).ef_session); return s && s.cid ? s : null; }

async function rawBody(req) {
  if (req.body !== undefined) return req.body;
  let d = ''; for await (const c of req) d += c;
  try { return JSON.parse(d); } catch (e) { return {}; }
}

const esc = (s) => String(s == null ? '' : s).replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));

// Sends only when RESEND_API_KEY is configured; otherwise it is a no-op (returns false).
async function mail(to, subject, html) {
  if (!process.env.RESEND_API_KEY || !to) return false;
  try {
    const r = await fetch('https://api.resend.com/emails', {
      method: 'POST',
      headers: { Authorization: `Bearer ${process.env.RESEND_API_KEY}`, 'Content-Type': 'application/json' },
      body: JSON.stringify({ from: process.env.QUOTE_FROM || 'Eleven Fashion <quotes@elevenfashion.co.th>', to: Array.isArray(to) ? to : [to], subject, html }),
    });
    return r.ok;
  } catch (e) { console.error('mail failed', e && e.message); return false; }
}
function mailLayout(title, inner) {
  return `<div style="font-family:Arial,sans-serif;max-width:560px;margin:0 auto;color:#3f3e3d"><p style="font-size:12px;letter-spacing:2px;text-transform:uppercase;color:#8a8a8a">Eleven Fashion</p><h2 style="font-weight:600;color:#222">${esc(title)}</h2>${inner}<p style="margin-top:28px;font-size:13px;color:#8a8a8a">Eleven Fashion Co., Ltd. · Bangkok, Thailand · info@elevenfashion.co.th</p></div>`;
}

module.exports = { query, cookies, sign, unsign, setCookie, pwOk, isAdmin, customerSession, rawBody, esc, mail, mailLayout, SITE };
