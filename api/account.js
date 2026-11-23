// Customer account: e-mail code login, orders with tracking, mockup approval, invoices, profile.
const crypto = require('crypto');
const S = require('../lib/store');
const H = require('../lib/http');

const str = (v, n = 300) => String(v == null ? '' : v).trim().slice(0, n);
const hash = (email, code) => crypto.createHmac('sha256', process.env.SESSION_SECRET || 'x').update(email + ':' + code).digest('hex');

async function bundle(cust) {
  const [orders, invoices] = await Promise.all([S.ordersFor(cust.id), S.invoicesFor(cust.id)]);
  const withEvents = await Promise.all(orders.map(async (o) => Object.assign({}, o, { events: await S.events(o.id) })));
  return { customer: cust, orders: withEvents, invoices, demo: !S.LIVE, stages: S.STAGES, labels: S.STAGE_LABEL };
}

module.exports = async (req, res) => {
  res.setHeader('Cache-Control', 'no-store');
  const a = H.query(req).a || '';
  const body = req.method === 'POST' ? await H.rawBody(req) : {};
  try {
    if (a === 'start') {
      const email = str(body.email, 160).toLowerCase();
      if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) return res.status(400).json({ error: 'Please enter a valid e-mail address.' });
      if (!S.LIVE) return res.status(200).json({ ok: true, demo: true, message: 'This is the demo version. Use "Open demo account" below.' });
      if (!process.env.SESSION_SECRET || !process.env.RESEND_API_KEY) return res.status(503).json({ error: 'Login is not available yet. Please e-mail info@elevenfashion.co.th.' });
      const cust = await S.customerByEmail(email);
      if (cust) {
        const code = String(crypto.randomInt(100000, 1000000));
        await S.putCode(email, hash(email, code));
        await H.mail(email, `Your Eleven Fashion login code: ${code}`, H.mailLayout('Your login code', `<p>Use this code to log in to your Eleven Fashion account:</p><p style="font-size:30px;letter-spacing:6px;font-weight:700;color:#222">${code}</p><p>The code is valid for 15 minutes. Did not ask for it? Then you can ignore this e-mail.</p>`));
      }
      // Same answer whether or not the address is known.
      return res.status(200).json({ ok: true, message: 'If this e-mail address is known to us, you will receive a login code within a minute.' });
    }
    if (a === 'verify') {
      if (!S.LIVE) return res.status(400).json({ error: 'Demo version: use "Open demo account".' });
      const email = str(body.email, 160).toLowerCase(); const code = str(body.code, 10).replace(/\D/g, '');
      const row = await S.getCode(email);
      if (!row || new Date(row.expires_at) < new Date() || row.attempts >= 5) return res.status(400).json({ error: 'This code has expired. Please request a new one.' });
      const want = Buffer.from(row.code_hash); const got = Buffer.from(hash(email, code));
      if (want.length !== got.length || !crypto.timingSafeEqual(want, got)) { await S.bumpCode(email, row.attempts + 1); return res.status(400).json({ error: 'Wrong code. Please try again.' }); }
      await S.delCode(email);
      const cust = await S.customerByEmail(email);
      if (!cust) return res.status(400).json({ error: 'Account not found.' });
      H.setCookie(res, 'ef_session', H.sign({ cid: cust.id }, 30), 30);
      return res.status(200).json({ ok: true });
    }
    if (a === 'demo') {
      if (S.LIVE) return res.status(404).json({ error: 'Not available.' });
      const cust = await S.customerByEmail('demo@elevenfashion.co.th');
      H.setCookie(res, 'ef_session', H.sign({ cid: cust.id, demo: true }, 1), 1);
      return res.status(200).json({ ok: true });
    }
    if (a === 'logout') { H.setCookie(res, 'ef_session', '', 0); return res.status(200).json({ ok: true }); }

    // everything below needs a logged-in customer
    const s = H.customerSession(req);
    const cust = s && (await S.customerById(s.cid));
    if (!cust) return res.status(401).json({ error: 'Please log in.', demo: !S.LIVE });

    if (a === 'me') return res.status(200).json(await bundle(cust));

    if (a === 'mockup') {
      const o = await S.order(str(body.order_id, 60));
      if (!o || o.customer_id !== cust.id) return res.status(404).json({ error: 'Order not found.' });
      if (o.mockup_status !== 'pending') return res.status(400).json({ error: 'There is no mockup waiting for approval.' });
      const approve = body.decision === 'approve';
      const feedback = str(body.feedback, 2000);
      if (!approve && !feedback) return res.status(400).json({ error: 'Please tell us what you would like to change.' });
      await S.updateOrder(o.id, { mockup_status: approve ? 'approved' : 'changes', mockup_feedback: feedback });
      await S.addEvent(o.id, o.status, approve ? 'Mockup approved by customer' + (feedback ? ': ' + feedback : '') : 'Changes requested: ' + feedback);
      if (process.env.QUOTE_TO) await H.mail(process.env.QUOTE_TO.split(','), `${o.number}: mockup ${approve ? 'approved' : 'changes requested'}`, H.mailLayout(`${o.number} – ${approve ? 'mockup approved' : 'changes requested'}`, `<p>${H.esc(cust.company || cust.name)} (${H.esc(cust.email)})</p>${feedback ? `<p>${H.esc(feedback)}</p>` : ''}`));
      return res.status(200).json(await bundle(cust));
    }
    if (a === 'profile') {
      const ad = body.address || {};
      const patch = { name: str(body.name, 120), company: str(body.company, 160), phone: str(body.phone, 40), country: str(body.country, 80), vat: str(body.vat, 40), address: { line1: str(ad.line1, 160), line2: str(ad.line2, 160), postcode: str(ad.postcode, 20), city: str(ad.city, 80), state: str(ad.state, 80), country: str(ad.country, 80) } };
      if (!patch.name) return res.status(400).json({ error: 'Please enter your name.' });
      const c2 = await S.updateCustomer(cust.id, patch);
      return res.status(200).json(await bundle(c2));
    }
    if (a === 'invoice') {
      const inv = await S.invoice(str(H.query(req).id, 60));
      if (!inv || inv.customer_id !== cust.id) return res.status(404).json({ error: 'Invoice not found.' });
      const o = inv.order_id ? await S.order(inv.order_id) : null;
      return res.status(200).json({ invoice: inv, order: o, customer: cust, seller: seller(), demo: !S.LIVE });
    }
    return res.status(404).json({ error: 'Unknown action.' });
  } catch (e) {
    console.error('account', a, e && e.message);
    return res.status(500).json({ error: 'Something went wrong. Please try again.' });
  }
};

function seller() {
  return {
    name: process.env.SELLER_NAME || 'Eleven Fashion Co., Ltd.',
    address: process.env.SELLER_ADDRESS || '71 Soi Suphaphong 1, Nongbon, Prawet, Bangkok 10250, Thailand',
    tax_id: process.env.SELLER_TAX_ID || '',
    email: 'info@elevenfashion.co.th',
    phone: '+66 (0)95 959 4633',
    bank: process.env.BANK_DETAILS || '',
  };
}
module.exports.seller = seller;
