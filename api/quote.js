// Quote request: validate, store as JSON in Vercel Blob, optionally notify by e-mail (Resend).
// Env: BLOB_READ_WRITE_TOKEN (required), RESEND_API_KEY + QUOTE_TO + QUOTE_FROM (optional e-mail).
const crypto = require('crypto');
const { put } = require('@vercel/blob');
const C = require('../lib/catalog');
const S = require('../lib/store');
const H = require('../lib/http');

const str = (v, n = 300) => String(v == null ? '' : v).trim().slice(0, n);
const esc = (s) => String(s).replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
const isUrl = (u) => /^https:\/\/[^\s]+$/i.test(u);

function cleanItem(it) {
  const p = C.PRODUCTS.find((x) => x.id === it.product) || null;
  const sizes = {};
  let total = 0;
  for (const s of C.SIZES.concat(['One size'])) {
    const n = Math.max(0, Math.min(100000, parseInt(it.sizes && it.sizes[s], 10) || 0));
    if (n) { sizes[s] = n; total += n; }
  }
  const qty = Math.max(0, Math.min(1000000, parseInt(it.quantity, 10) || 0));
  const arr = (v, n, len) => (Array.isArray(v) ? v : []).map((x) => str(x, len || 60)).filter(Boolean).slice(0, n || 10);
  return {
    product: p ? p.name : 'Other',
    product_other: str(it.product_other, 120),
    weight: str(it.weight, 60),
    knit: str(it.knit, 60),
    fit: str(it.fit, 60),
    material: str(it.material, 80),
    colors: arr(it.colors, 10),
    pantone: str(it.pantone, 80),
    more_colors: str(it.more_colors, 200),
    quantity: total || qty,
    sizes,
    decoration: arr(it.decoration, 5, 60),
    positions: arr(it.positions, 8, 40),
    print_colors: str(it.print_colors, 40),
    extras: (Array.isArray(it.extras) ? it.extras : []).filter((d) => C.EXTRAS.includes(d)),
    notes: str(it.notes, 1500),
    design: (Array.isArray(it.design) ? it.design : []).slice(0, 30).map((d) => ({
      view: str(d.view, 30), type: d.type === 'text' ? 'text' : 'logo', text: str(d.text, 80), font: str(d.font, 40),
      color: str(d.color, 20), logo_name: str(d.logo_name, 120), logo_url: isUrl(str(d.logo_url, 500)) ? str(d.logo_url, 500) : '',
    })),
    mockups: (Array.isArray(it.mockups) ? it.mockups : []).slice(0, 6).map((m) => ({ view: str(m.view, 30), url: str(m.url, 500) })).filter((m) => isUrl(m.url)),
  };
}

module.exports = async (req, res) => {
  if (req.method !== 'POST') return res.status(405).json({ error: 'Method not allowed' });
  const b = req.body || {};
  if (str(b.website)) return res.status(200).json({ ok: true, reference: 'EF-Q-0' }); // honeypot
  const contact = {
    name: str(b.name, 120), company: str(b.company, 160), email: str(b.email, 160).toLowerCase(),
    phone: str(b.phone, 40), country: str(b.country, 80), website_url: str(b.website_url, 200),
    customer_type: str(b.customer_type, 60),
  };
  const errors = [];
  if (!contact.name) errors.push('Please enter your name.');
  if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(contact.email)) errors.push('Please enter a valid e-mail address.');
  if (!contact.country) errors.push('Please enter your country.');
  const items = (Array.isArray(b.items) ? b.items : []).slice(0, 15).map(cleanItem);
  if (!items.length) errors.push('Please add at least one product.');
  items.forEach((it, i) => { if (!it.quantity) errors.push(`Product ${i + 1}: please enter a quantity.`); });
  const logos = (Array.isArray(b.logos) ? b.logos : []).map((l) => ({ url: str(l.url, 500), name: str(l.name, 120) })).filter((l) => isUrl(l.url)).slice(0, 10);
  const artwork_link = str(b.artwork_link, 500);
  if (artwork_link && !isUrl(artwork_link)) errors.push('The artwork link must start with https://');
  if (!b.consent) errors.push('Please agree that we may contact you about this request.');
  if (errors.length) return res.status(400).json({ error: errors.join(' ') });
  if (!S.LIVE && !process.env.BLOB_READ_WRITE_TOKEN) return res.status(503).json({ error: 'Quote requests are not available yet. Please e-mail info@elevenfashion.co.th.' });

  const now = new Date();
  const reference = `EF-Q-${now.toISOString().slice(0, 10).replace(/-/g, '')}-${crypto.randomBytes(3).toString('hex').toUpperCase()}`;
  const quote = {
    reference, created_at: now.toISOString(), status: 'new', contact, items, logos, artwork_link,
    deadline: str(b.deadline, 20), delivery_country: str(b.delivery_country, 80), budget: str(b.budget, 80),
    message: str(b.message, 3000), source: str(req.headers.referer, 200),
  };
  let account = false;
  try {
    if (S.LIVE) {
      // Store the request, create/refresh the customer and open an order in stage "Design" so the customer can follow it.
      await S.saveQuote(quote);
      const cust = await S.upsertCustomer(contact);
      await S.createOrder({ customer_id: cust.id, title: items.map((i) => i.product).join(' + '), quote_ref: reference, items, note: `Quote request ${reference} received` });
      account = true;
    } else {
      await put(`quotes/${reference}.json`, JSON.stringify(quote, null, 2), { access: 'public', addRandomSuffix: true, contentType: 'application/json' });
    }
  } catch (e) {
    console.error('store failed', e && e.message);
    return res.status(500).json({ error: 'Something went wrong. Please try again or e-mail info@elevenfashion.co.th.' });
  }

  if (process.env.RESEND_API_KEY && process.env.QUOTE_TO) {
    const lines = items.map((it, i) => `<li><b>${i + 1}. ${esc(it.product)}${it.product_other ? ' – ' + esc(it.product_other) : ''}</b>, ${it.quantity} pcs${it.weight ? ', ' + esc(it.weight) : ''}${it.colors.length ? ', ' + esc(it.colors.join('/')) : ''}${it.decoration.length ? ', ' + esc(it.decoration.join(', ')) : ''}</li>`).join('');
    const html = `<p>New quote request <b>${reference}</b></p><p>${esc(contact.name)}${contact.company ? ' – ' + esc(contact.company) : ''}<br>${esc(contact.email)} ${esc(contact.phone)}<br>${esc(contact.country)}</p><ul>${lines}</ul>${logos.map((l) => `<p>Logo: <a href="${esc(l.url)}">${esc(l.name || l.url)}</a></p>`).join('')}${artwork_link ? `<p>Artwork link: ${esc(artwork_link)}</p>` : ''}${quote.message ? `<p>${esc(quote.message)}</p>` : ''}`;
    try {
      await fetch('https://api.resend.com/emails', {
        method: 'POST',
        headers: { Authorization: `Bearer ${process.env.RESEND_API_KEY}`, 'Content-Type': 'application/json' },
        body: JSON.stringify({ from: process.env.QUOTE_FROM || 'Eleven Fashion <quotes@elevenfashion.co.th>', to: process.env.QUOTE_TO.split(','), reply_to: contact.email, subject: `Quote request ${reference} – ${contact.company || contact.name}`, html }),
      });
    } catch (e) { console.error('mail failed', e && e.message); }
  }
  if (account) {
    const site = H.SITE() || `https://${req.headers['x-forwarded-host'] || req.headers.host}`;
    await H.mail(contact.email, `We received your quote request ${reference}`, H.mailLayout('Thank you for your request', `<p>Hi ${esc(contact.name)},</p><p>We received your quote request <b>${reference}</b> and will reply with a quote. You can follow your request in your account: log in with this e-mail address.</p><p><a href="${site}/account" style="display:inline-block;background:#3f3e3d;color:#fff;padding:12px 20px;text-decoration:none">Go to your account</a></p>`));
  }
  return res.status(200).json({ ok: true, reference, account });
};
