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
  const ago = (days) => new Date(Date.now() - days * 864e5).toISOString();
  const inDays = (days) => new Date(Date.now() + days * 864e5).toISOString().slice(0, 10);
  // One demo order in every stage, so the whole route is visible.
  const oD = mk('EF-1005', 'Gym tank tops', 'design', { created_at: ago(1), quote_ref: 'EF-Q-DEMO-5',
    items: [{ product: 'Tank top', weight: '160 gsm', knit: 'Single jersey', colors: ['White'], quantity: 60, sizes: { S: 15, M: 25, L: 20 }, decoration: ['Screen print'] }] });
  const oM = mk('EF-1004', 'Team T-shirts 2027', 'mockup', { created_at: ago(6), quote_ref: 'EF-Q-DEMO-4',
    items: [{ product: 'T-shirt', weight: '180 gsm', knit: 'Combed cotton jersey', colors: ['Black'], quantity: 100, sizes: { S: 10, M: 40, L: 35, XL: 15 }, decoration: ['Screen print'] }],
    mockups: [{ label: 'Front', url: 'https://images.unsplash.com/photo-1583743814966-8936f5b7be1a?auto=format&fit=crop&w=900&q=80' }, { label: 'Back', url: 'https://images.unsplash.com/photo-1651761179569-4ba2aa054997?auto=format&fit=crop&w=900&q=80' }],
    mockup_status: 'pending' });
  const oP = mk('EF-1003', 'Polo shirts staff', 'production', { created_at: ago(14), quote_ref: 'EF-Q-DEMO-3', mockup_status: 'approved',
    mockups: [{ label: 'Front', url: 'https://images.unsplash.com/photo-1625910513399-c9fcba54338c?auto=format&fit=crop&w=900&q=80' }],
    items: [{ product: 'Polo shirt', weight: '220 gsm', knit: 'Pique', colors: ['Black'], quantity: 50, sizes: { M: 20, L: 20, XL: 10 }, decoration: ['Embroidery'] }] });
  const oT = mk('EF-1002', 'Hoodies with embroidery', 'transit', { created_at: ago(30), quote_ref: 'EF-Q-DEMO-2', mockup_status: 'approved',
    mockups: [{ label: 'Back', url: 'https://images.unsplash.com/photo-1556821840-3a63f95609a7?auto=format&fit=crop&w=900&q=80' }],
    items: [{ product: 'Hoodie', weight: '300 gsm', knit: 'Brushed fleece (soft inside)', colors: ['Heather grey'], quantity: 40, sizes: { M: 20, L: 20 }, decoration: ['Embroidery'] }],
    carrier: 'DHL Express', tracking_number: 'DEMO123456', eta: inDays(4) });
  const oX = mk('EF-1001', 'Fight shorts', 'delivered', { created_at: ago(70), quote_ref: 'EF-Q-DEMO-1', mockup_status: 'approved',
    mockups: [{ label: 'Front', url: 'https://images.unsplash.com/photo-1640943136566-3edeb13e3d3b?auto=format&fit=crop&w=900&q=80' }],
    items: [{ product: 'Shorts', weight: '140 gsm', knit: 'Stretch woven (fight shorts)', colors: ['Red'], quantity: 80, sizes: { S: 20, M: 30, L: 30 }, decoration: ['Sublimation (all-over)'] }],
    carrier: 'FedEx', tracking_number: 'DEMO654321' });
  const ev = (o, s, note, daysAgo) => d.events.push({ id: uid(), order_id: o.id, status: s, note, created_at: ago(daysAgo) });
  ev(oD, 'design', 'Quote request received – we are preparing your quote', 1);
  ev(oM, 'design', 'Quote request received', 6); ev(oM, 'design', 'Quote approved, invoice for 50% deposit sent', 4); ev(oM, 'mockup', 'Mockup ready for your approval', 1);
  ev(oP, 'design', 'Quote request received', 14); ev(oP, 'mockup', 'Mockup ready for your approval', 11); ev(oP, 'mockup', 'Mockup approved by customer', 10); ev(oP, 'production', 'Fabric sourced, production started', 8);
  ev(oT, 'design', 'Quote request received', 30); ev(oT, 'mockup', 'Mockup ready for your approval', 26); ev(oT, 'production', 'Mockup approved, production started', 24); ev(oT, 'transit', 'Shipped with DHL Express', 2);
  ev(oX, 'design', 'Quote request received', 70); ev(oX, 'mockup', 'Mockup approved by customer', 64); ev(oX, 'production', 'Production started', 62); ev(oX, 'transit', 'Shipped with FedEx', 48); ev(oX, 'delivered', 'Delivered', 44);
  const inv = (o, n, status, lines, daysAgo) => {
    const sub = lines.reduce((x, l) => x + l.qty * l.price, 0);
    d.invoices.push({ id: uid(), number: n, order_id: o.id, customer_id: c.id, currency: 'EUR', lines, subtotal: sub, tax_rate: 0, tax: 0, total: sub, status, due_date: inDays(14 - daysAgo), issued_at: ago(daysAgo), paid_at: status === 'paid' ? ago(daysAgo - 1) : null, payment_method: status === 'paid' ? 'Card' : '', payment_ref: '', demo: true });
  };
  inv(oM, 'DEMO-INV-0004', 'open', [{ description: 'DEMO – 50% deposit T-shirts 180 gsm, black, screen print', qty: 1, price: 1 }], 4);
  inv(oP, 'DEMO-INV-0003', 'paid', [{ description: 'DEMO – Polo shirts 220 gsm pique, embroidery', qty: 50, price: 1 }], 10);
  inv(oT, 'DEMO-INV-0002', 'paid', [{ description: 'DEMO – Hoodies 300 gsm brushed fleece, embroidery', qty: 40, price: 1 }], 25);
  inv(oX, 'DEMO-INV-0001', 'paid', [{ description: 'DEMO – Fight shorts, sublimation', qty: 80, price: 1 }], 64);
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
