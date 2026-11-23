// Data layer. Uses Supabase (Postgres via PostgREST) when SUPABASE_URL + SUPABASE_SERVICE_ROLE_KEY are set,
// otherwise an in-memory demo store with clearly marked sample data (resets on every cold start).
const crypto = require('crypto');

const URL_ = (process.env.SUPABASE_URL || '').replace(/\/$/, '');
const KEY = process.env.SUPABASE_SERVICE_ROLE_KEY || '';
const LIVE = !!(URL_ && KEY);

const STAGES = ['design', 'mockup', 'production', 'transit', 'delivered'];
const STAGE_LABEL = { design: 'Design', mockup: 'Mockup approval', production: 'In progress', transit: 'In transit', delivered: 'Delivered', cancelled: 'Cancelled' };

const uid = () => crypto.randomUUID();
const now = () => new Date().toISOString();

// ---------- Supabase REST ----------
async function rest(method, table, { query = '', body, prefer } = {}) {
  const r = await fetch(`${URL_}/rest/v1/${table}${query ? '?' + query : ''}`, {
    method,
    headers: {
      apikey: KEY, Authorization: `Bearer ${KEY}`, 'Content-Type': 'application/json',
      Prefer: prefer || (method === 'GET' ? '' : 'return=representation'),
    },
    body: body ? JSON.stringify(body) : undefined,
  });
  const txt = await r.text();
  if (!r.ok) throw new Error(`db ${method} ${table} ${r.status}: ${txt.slice(0, 200)}`);
  return txt ? JSON.parse(txt) : null;
}
const enc = encodeURIComponent;

// ---------- demo store ----------
const demo = (() => {
  const d = { customers: [], orders: [], events: [], invoices: [], quotes: [], codes: [] };
  const c = { id: uid(), email: 'demo@elevenfashion.co.th', name: 'Demo customer', company: 'Demo Gym Amsterdam', phone: '', country: 'Netherlands', address: { line1: 'Demo street 1', city: 'Amsterdam', postcode: '1000 AA', country: 'Netherlands' }, vat: '', created_at: now(), demo: true };
  d.customers.push(c);
  const mk = (n, title, status, extra) => {
    const o = Object.assign({ id: uid(), number: n, customer_id: c.id, title, status, quote_ref: '', items: [], currency: 'EUR', mockups: [], mockup_status: null, mockup_feedback: '', carrier: '', tracking_number: '', tracking_url: '', eta: null, created_at: now(), updated_at: now(), demo: true }, extra);
    d.orders.push(o);
    return o;
  };
  const o1 = mk('EF-1001', 'Team T-shirts 2027', 'mockup', {
    items: [{ product: 'T-shirt', weight: '180 gsm', colors: ['Black'], quantity: 100, sizes: { S: 10, M: 40, L: 35, XL: 15 }, decoration: ['Screen print'] }],
    mockups: [{ label: 'Front', url: 'https://images.unsplash.com/photo-1651761179569-4ba2aa054997?auto=format&fit=crop&w=900&q=80' }],
    mockup_status: 'pending',
  });
  const o2 = mk('EF-1000', 'Hoodies with embroidery', 'transit', {
    items: [{ product: 'Hoodie', weight: '320 gsm', colors: ['Heather grey'], quantity: 40, sizes: { M: 20, L: 20 }, decoration: ['Embroidery'] }],
    carrier: 'DHL Express', tracking_number: 'DEMO123456', tracking_url: '', eta: new Date(Date.now() + 4 * 864e5).toISOString().slice(0, 10), mockup_status: 'approved',
  });
  const ev = (o, s, note, daysAgo) => d.events.push({ id: uid(), order_id: o.id, status: s, note, created_at: new Date(Date.now() - daysAgo * 864e5).toISOString() });
  ev(o1, 'design', 'Quote request received', 6); ev(o1, 'mockup', 'Mockup ready for your approval', 1);
  ev(o2, 'design', 'Quote request received', 30); ev(o2, 'mockup', 'Mockup ready for your approval', 26); ev(o2, 'production', 'Mockup approved, production started', 24); ev(o2, 'transit', 'Shipped with DHL Express', 2);
  const inv = (o, n, status, lines) => {
    const sub = lines.reduce((a, l) => a + l.qty * l.price, 0);
    d.invoices.push({ id: uid(), number: n, order_id: o.id, customer_id: c.id, currency: 'EUR', lines, subtotal: sub, tax_rate: 0, tax: 0, total: sub, status, due_date: new Date(Date.now() + 14 * 864e5).toISOString().slice(0, 10), issued_at: now(), paid_at: status === 'paid' ? now() : null, payment_method: status === 'paid' ? 'card' : '', payment_ref: '', demo: true });
  };
  inv(o1, 'DEMO-INV-0002', 'open', [{ description: 'DEMO – T-shirt 180 gsm, black, screen print 1 colour', qty: 100, price: 1 }]);
  inv(o2, 'DEMO-INV-0001', 'paid', [{ description: 'DEMO – Hoodie 320 gsm, embroidery', qty: 40, price: 1 }]);
  return d;
})();

const S = {
  LIVE, STAGES, STAGE_LABEL,

  async customerByEmail(email) {
    if (!LIVE) return demo.customers.find((x) => x.email === email) || null;
    return (await rest('GET', 'customers', { query: `email=eq.${enc(email)}&limit=1` }))[0] || null;
  },
  async customerById(id) {
    if (!LIVE) return demo.customers.find((x) => x.id === id) || null;
    return (await rest('GET', 'customers', { query: `id=eq.${enc(id)}&limit=1` }))[0] || null;
  },
  async upsertCustomer(c) {
    const ex = await S.customerByEmail(c.email);
    if (ex) {
      const patch = {};
      ['name', 'company', 'phone', 'country'].forEach((k) => { if (c[k] && !ex[k]) patch[k] = c[k]; });
      if (Object.keys(patch).length) return S.updateCustomer(ex.id, patch);
      return ex;
    }
    const row = { id: uid(), email: c.email, name: c.name || '', company: c.company || '', phone: c.phone || '', country: c.country || '', address: c.address || {}, vat: c.vat || '', created_at: now() };
    if (!LIVE) { demo.customers.push(row); return row; }
    return (await rest('POST', 'customers', { body: row }))[0];
  },
  async updateCustomer(id, patch) {
    if (!LIVE) { const x = demo.customers.find((y) => y.id === id); Object.assign(x, patch); return x; }
    return (await rest('PATCH', 'customers', { query: `id=eq.${enc(id)}`, body: patch }))[0];
  },
  async listCustomers() {
    if (!LIVE) return demo.customers.slice();
    return rest('GET', 'customers', { query: 'order=created_at.desc&limit=500' });
  },

  async ordersFor(customerId) {
    if (!LIVE) return demo.orders.filter((o) => o.customer_id === customerId).sort((a, b) => b.created_at.localeCompare(a.created_at));
    return rest('GET', 'orders', { query: `customer_id=eq.${enc(customerId)}&order=created_at.desc` });
  },
  async listOrders() {
    if (!LIVE) return demo.orders.slice().sort((a, b) => b.created_at.localeCompare(a.created_at));
    return rest('GET', 'orders', { query: 'order=created_at.desc&limit=500' });
  },
  async order(id) {
    if (!LIVE) return demo.orders.find((o) => o.id === id) || null;
    return (await rest('GET', 'orders', { query: `id=eq.${enc(id)}&limit=1` }))[0] || null;
  },
  async nextNumber(prefix, table) {
    const year = new Date().getFullYear();
    const rows = !LIVE ? demo[table].filter((r) => (r.number || '').startsWith(`${prefix}-${year}-`))
      : await rest('GET', table, { query: `number=like.${enc(prefix + '-' + year + '-*')}&select=number&order=number.desc&limit=1` });
    const max = rows.reduce((m, r) => Math.max(m, parseInt(String(r.number).split('-').pop(), 10) || 0), 0);
    return `${prefix}-${year}-${String(max + 1).padStart(4, '0')}`;
  },
  async createOrder(o) {
    const row = Object.assign({ id: uid(), number: await S.nextNumber('EF', 'orders'), status: 'design', items: [], currency: 'EUR', mockups: [], mockup_status: null, mockup_feedback: '', carrier: '', tracking_number: '', tracking_url: '', eta: null, created_at: now(), updated_at: now() }, o);
    if (!LIVE) demo.orders.push(row); else await rest('POST', 'orders', { body: row });
    await S.addEvent(row.id, row.status, o.note || 'Order created');
    return row;
  },
  async updateOrder(id, patch) {
    patch.updated_at = now();
    if (!LIVE) { const x = demo.orders.find((y) => y.id === id); Object.assign(x, patch); return x; }
    return (await rest('PATCH', 'orders', { query: `id=eq.${enc(id)}`, body: patch }))[0];
  },
  async events(orderId) {
    if (!LIVE) return demo.events.filter((e) => e.order_id === orderId).sort((a, b) => a.created_at.localeCompare(b.created_at));
    return rest('GET', 'order_events', { query: `order_id=eq.${enc(orderId)}&order=created_at.asc` });
  },
  async addEvent(orderId, status, note) {
    const row = { id: uid(), order_id: orderId, status, note: note || '', created_at: now() };
    if (!LIVE) demo.events.push(row); else await rest('POST', 'order_events', { body: row, prefer: 'return=minimal' });
    return row;
  },

  async invoicesFor(customerId) {
    if (!LIVE) return demo.invoices.filter((i) => i.customer_id === customerId).sort((a, b) => b.issued_at.localeCompare(a.issued_at));
    return rest('GET', 'invoices', { query: `customer_id=eq.${enc(customerId)}&order=issued_at.desc` });
  },
  async listInvoices() {
    if (!LIVE) return demo.invoices.slice();
    return rest('GET', 'invoices', { query: 'order=issued_at.desc&limit=500' });
  },
  async invoice(id) {
    if (!LIVE) return demo.invoices.find((i) => i.id === id) || null;
    return (await rest('GET', 'invoices', { query: `id=eq.${enc(id)}&limit=1` }))[0] || null;
  },
  async createInvoice(inv) {
    const subtotal = Math.round(inv.lines.reduce((a, l) => a + l.qty * l.price, 0) * 100) / 100;
    const tax = Math.round(subtotal * (inv.tax_rate || 0)) / 100;
    const row = Object.assign({ id: uid(), number: await S.nextNumber('INV', 'invoices'), status: 'open', issued_at: now(), paid_at: null, payment_method: '', payment_ref: '' }, inv, { subtotal, tax, total: Math.round((subtotal + tax) * 100) / 100 });
    if (!LIVE) demo.invoices.push(row); else await rest('POST', 'invoices', { body: row });
    return row;
  },
  async updateInvoice(id, patch) {
    if (!LIVE) { const x = demo.invoices.find((y) => y.id === id); Object.assign(x, patch); return x; }
    return (await rest('PATCH', 'invoices', { query: `id=eq.${enc(id)}`, body: patch }))[0];
  },

  async saveQuote(q) {
    if (!LIVE) { demo.quotes.unshift(q); return q; }
    await rest('POST', 'quotes', { body: { reference: q.reference, created_at: q.created_at, email: q.contact.email, data: q }, prefer: 'return=minimal' });
    return q;
  },
  async listQuotes() {
    if (!LIVE) return demo.quotes.slice(0, 200);
    return (await rest('GET', 'quotes', { query: 'order=created_at.desc&limit=200&select=data' })).map((r) => r.data);
  },

  // login codes (hashed)
  async putCode(email, hash) {
    const row = { email, code_hash: hash, expires_at: new Date(Date.now() + 15 * 60e3).toISOString(), attempts: 0 };
    if (!LIVE) { demo.codes = demo.codes.filter((c) => c.email !== email); demo.codes.push(row); return; }
    await rest('DELETE', 'login_codes', { query: `email=eq.${enc(email)}`, prefer: 'return=minimal' });
    await rest('POST', 'login_codes', { body: row, prefer: 'return=minimal' });
  },
  async getCode(email) {
    if (!LIVE) return demo.codes.find((c) => c.email === email) || null;
    return (await rest('GET', 'login_codes', { query: `email=eq.${enc(email)}&limit=1` }))[0] || null;
  },
  async bumpCode(email, attempts) {
    if (!LIVE) { const c = demo.codes.find((x) => x.email === email); if (c) c.attempts = attempts; return; }
    await rest('PATCH', 'login_codes', { query: `email=eq.${enc(email)}`, body: { attempts }, prefer: 'return=minimal' });
  },
  async delCode(email) {
    if (!LIVE) { demo.codes = demo.codes.filter((c) => c.email !== email); return; }
    await rest('DELETE', 'login_codes', { query: `email=eq.${enc(email)}`, prefer: 'return=minimal' });
  },
};

module.exports = S;
