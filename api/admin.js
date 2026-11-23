// Eleven Fashion team: quotes, orders (status, mockups, tracking), customers and invoices.
// Login with ADMIN_PASSWORD -> signed cookie. Without a database and without Blob it runs as a demo on sample data.
const S = require('../lib/store');
const H = require('../lib/http');
const { methods } = require('./pay');
const { seller } = require('./account');

const str = (v, n = 300) => String(v == null ? '' : v).trim().slice(0, n);
const CURRENCIES = ['EUR', 'USD', 'THB', 'GBP', 'AUD', 'SGD'];
const demoAdminAllowed = () => !S.LIVE && !process.env.BLOB_READ_WRITE_TOKEN && !process.env.ADMIN_PASSWORD;

async function blobQuotes() {
  if (!process.env.BLOB_READ_WRITE_TOKEN) return [];
  const { list } = require('@vercel/blob');
  const out = []; let cursor;
  do { const r = await list({ prefix: 'quotes/', cursor, limit: 1000 }); out.push(...r.blobs); cursor = r.hasMore ? r.cursor : undefined; } while (cursor && out.length < 3000);
  out.sort((a, b) => new Date(b.uploadedAt) - new Date(a.uploadedAt));
  return (await Promise.all(out.slice(0, 200).map((b) => fetch(b.url).then((r) => r.json()).catch(() => null)))).filter(Boolean);
}
async function quotes() { return S.LIVE ? S.listQuotes() : (process.env.BLOB_READ_WRITE_TOKEN ? blobQuotes() : S.listQuotes()); }

function site(req) { return H.SITE() || `https://${req.headers['x-forwarded-host'] || req.headers.host}`; }
async function notify(req, order, subject, text) {
  const c = await S.customerById(order.customer_id);
  if (!c || c.demo) return false;
  return H.mail(c.email, subject, H.mailLayout(subject, `<p>${H.esc(text)}</p><p><a href="${site(req)}/account" style="display:inline-block;background:#3f3e3d;color:#fff;padding:12px 20px;text-decoration:none">View your order</a></p>`));
}

module.exports = async (req, res) => {
  res.setHeader('Cache-Control', 'no-store');
  const q = H.query(req); const a = q.a || (req.method === 'POST' ? 'login' : '');
  const b = req.method === 'POST' ? await H.rawBody(req) : {};
  try {
    if (a === 'login') {
      if (b.demo && demoAdminAllowed()) { H.setCookie(res, 'ef_admin', H.sign({ admin: true, demo: true }, 1), 1); return res.status(200).json({ ok: true }); }
      if (!process.env.ADMIN_PASSWORD) return res.status(503).json({ error: 'Admin is not set up yet (ADMIN_PASSWORD missing).', demo: demoAdminAllowed() });
      if (!H.pwOk(b.password)) return res.status(401).json({ error: 'Wrong password.' });
      H.setCookie(res, 'ef_admin', H.sign({ admin: true }, 1), 1);
      return res.status(200).json({ ok: true });
    }
    if (a === 'logout') { H.setCookie(res, 'ef_admin', '', 0); return res.status(200).json({ ok: true }); }
    if (a === 'status-check') return res.status(200).json({ admin: H.isAdmin(req), demo: demoAdminAllowed(), live: S.LIVE });
    if (!H.isAdmin(req)) return res.status(401).json({ error: 'Please log in.', demo: demoAdminAllowed() });

    if (a === 'data') {
      const [qs, orders, customers, invoices] = await Promise.all([quotes(), S.listOrders(), S.listCustomers(), S.listInvoices()]);
      const byId = Object.fromEntries(customers.map((c) => [c.id, c]));
      const withEv = await Promise.all(orders.map(async (o) => Object.assign({}, o, { customer: byId[o.customer_id] || null, events: await S.events(o.id) })));
      return res.status(200).json({ quotes: qs, orders: withEv, customers, invoices, methods: methods(), live: S.LIVE, stages: S.STAGES, labels: S.STAGE_LABEL, currencies: CURRENCIES });
    }

    if (a === 'order-from-quote') {
      const ref = str(b.reference, 40);
      const qt = (await quotes()).find((x) => x.reference === ref);
      if (!qt) return res.status(404).json({ error: 'Quote not found.' });
      const c = await S.upsertCustomer(qt.contact);
      const o = await S.createOrder({ customer_id: c.id, title: str(b.title, 120) || (qt.items || []).map((i) => i.product).join(' + '), quote_ref: ref, items: qt.items || [], currency: CURRENCIES.includes(b.currency) ? b.currency : 'EUR', note: `Order created from quote ${ref}` });
      return res.status(200).json({ ok: true, order: o });
    }
    if (a === 'order-new') {
      const email = str(b.email, 160).toLowerCase();
      if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) return res.status(400).json({ error: 'Enter the customer e-mail address.' });
      const c = await S.upsertCustomer({ email, name: str(b.name, 120), company: str(b.company, 160), country: str(b.country, 80) });
      const o = await S.createOrder({ customer_id: c.id, title: str(b.title, 120) || 'New order', currency: CURRENCIES.includes(b.currency) ? b.currency : 'EUR' });
      return res.status(200).json({ ok: true, order: o });
    }
    if (a === 'order-save') {
      const o = await S.order(str(b.id, 60)); if (!o) return res.status(404).json({ error: 'Order not found.' });
      const patch = { title: str(b.title, 120) || o.title, carrier: str(b.carrier, 60), tracking_number: str(b.tracking_number, 80), tracking_url: /^https:\/\//.test(str(b.tracking_url, 400)) ? str(b.tracking_url, 400) : '', eta: /^\d{4}-\d{2}-\d{2}$/.test(b.eta || '') ? b.eta : null };
      if (CURRENCIES.includes(b.currency)) patch.currency = b.currency;
      return res.status(200).json({ ok: true, order: await S.updateOrder(o.id, patch) });
    }
    if (a === 'status') {
      const o = await S.order(str(b.id, 60)); if (!o) return res.status(404).json({ error: 'Order not found.' });
      const st = str(b.status, 20);
      if (!S.STAGES.concat(['cancelled']).includes(st)) return res.status(400).json({ error: 'Unknown status.' });
      const note = str(b.note, 500) || `Status: ${S.STAGE_LABEL[st]}`;
      await S.updateOrder(o.id, { status: st });
      await S.addEvent(o.id, st, note);
      const sent = b.notify ? await notify(req, o, `${o.number}: ${S.STAGE_LABEL[st]}`, note) : false;
      return res.status(200).json({ ok: true, mailed: sent });
    }
    if (a === 'mockup') {
      const o = await S.order(str(b.id, 60)); if (!o) return res.status(404).json({ error: 'Order not found.' });
      const mockups = (Array.isArray(b.mockups) ? b.mockups : []).map((m) => ({ label: str(m.label, 40) || 'Mockup', url: str(m.url, 500) })).filter((m) => /^https?:\/\//.test(m.url)).slice(0, 8);
      if (!mockups.length) return res.status(400).json({ error: 'Add at least one mockup image.' });
      const note = str(b.note, 500) || 'Your mockup is ready for approval';
      await S.updateOrder(o.id, { mockups, mockup_status: 'pending', mockup_feedback: '', status: 'mockup' });
      await S.addEvent(o.id, 'mockup', note);
      const sent = b.notify ? await notify(req, o, `${o.number}: please approve your mockup`, note) : false;
      return res.status(200).json({ ok: true, mailed: sent });
    }
    if (a === 'invoice') {
      const o = await S.order(str(b.order_id, 60)); if (!o) return res.status(404).json({ error: 'Order not found.' });
      const lines = (Array.isArray(b.lines) ? b.lines : []).map((l) => ({ description: str(l.description, 200), qty: Math.max(0, Number(l.qty) || 0), price: Math.round((Number(l.price) || 0) * 100) / 100 })).filter((l) => l.description && l.qty > 0).slice(0, 40);
      if (!lines.length) return res.status(400).json({ error: 'Add at least one invoice line with a quantity and price.' });
      const cur = CURRENCIES.includes(b.currency) ? b.currency : o.currency || 'EUR';
      const inv = await S.createInvoice({ order_id: o.id, customer_id: o.customer_id, currency: cur, lines, tax_rate: Math.max(0, Math.min(30, Number(b.tax_rate) || 0)), due_date: /^\d{4}-\d{2}-\d{2}$/.test(b.due_date || '') ? b.due_date : null, notes: str(b.notes, 1000) });
      await S.addEvent(o.id, o.status, `Invoice ${inv.number} issued (${cur} ${inv.total.toFixed(2)})`);
      const sent = b.notify ? await notify(req, o, `Invoice ${inv.number} from Eleven Fashion`, `Your invoice ${inv.number} (${cur} ${inv.total.toFixed(2)}) is ready. You can view and pay it in your account.`) : false;
      return res.status(200).json({ ok: true, invoice: inv, mailed: sent });
    }
    if (a === 'invoice-status') {
      const inv = await S.invoice(str(b.id, 60)); if (!inv) return res.status(404).json({ error: 'Invoice not found.' });
      if (b.status === 'paid') await S.updateInvoice(inv.id, { status: 'paid', paid_at: new Date().toISOString(), payment_method: str(b.method, 60) || 'Bank transfer', payment_ref: str(b.ref, 80) });
      else if (b.status === 'void') await S.updateInvoice(inv.id, { status: 'void' });
      else if (b.status === 'open') await S.updateInvoice(inv.id, { status: 'open', paid_at: null, payment_method: '', payment_ref: '' });
      else return res.status(400).json({ error: 'Unknown status.' });
      return res.status(200).json({ ok: true });
    }
    if (a === 'invoice-view') {
      const inv = await S.invoice(str(q.id, 60)); if (!inv) return res.status(404).json({ error: 'Invoice not found.' });
      return res.status(200).json({ invoice: inv, order: inv.order_id ? await S.order(inv.order_id) : null, customer: await S.customerById(inv.customer_id), seller: seller(), demo: !S.LIVE });
    }
    return res.status(404).json({ error: 'Unknown action.' });
  } catch (e) {
    console.error('admin', a, e && e.message);
    return res.status(500).json({ error: 'Something went wrong.' });
  }
};
