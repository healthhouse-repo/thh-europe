// Payment webhooks. Stripe: signature checked with STRIPE_WEBHOOK_SECRET. Mollie: payment re-fetched from the Mollie API.
const crypto = require('crypto');
const S = require('../lib/store');
const H = require('../lib/http');

async function raw(req) { const chunks = []; for await (const c of req) chunks.push(c); return Buffer.concat(chunks); }

async function markPaid(invoiceId, method, ref) {
  const inv = await S.invoice(invoiceId);
  if (!inv || inv.status === 'paid') return;
  await S.updateInvoice(inv.id, { status: 'paid', paid_at: new Date().toISOString(), payment_method: method, payment_ref: ref });
  if (inv.order_id) await S.addEvent(inv.order_id, (await S.order(inv.order_id) || {}).status || 'design', `Invoice ${inv.number} paid (${method})`);
  if (process.env.QUOTE_TO) await H.mail(process.env.QUOTE_TO.split(','), `Invoice ${inv.number} paid`, H.mailLayout(`Invoice ${inv.number} paid`, `<p>${H.esc(inv.currency)} ${Number(inv.total).toFixed(2)} via ${H.esc(method)}.</p>`));
}

module.exports = async (req, res) => {
  const p = H.query(req).p;
  try {
    if (p === 'stripe') {
      const secret = process.env.STRIPE_WEBHOOK_SECRET;
      const buf = await raw(req);
      const sig = String(req.headers['stripe-signature'] || '');
      const t = (sig.match(/t=(\d+)/) || [])[1];
      const v1s = [...sig.matchAll(/v1=([0-9a-f]+)/g)].map((m) => m[1]);
      if (!secret || !t) return res.status(400).end();
      const want = crypto.createHmac('sha256', secret).update(`${t}.${buf.toString('utf8')}`).digest('hex');
      const good = v1s.some((v) => v.length === want.length && crypto.timingSafeEqual(Buffer.from(v), Buffer.from(want)));
      if (!good || Math.abs(Date.now() / 1000 - Number(t)) > 600) return res.status(400).end();
      const ev = JSON.parse(buf.toString('utf8'));
      if (ev.type === 'checkout.session.completed' || ev.type === 'checkout.session.async_payment_succeeded') {
        const sess = ev.data.object;
        if (sess.payment_status === 'paid' && sess.metadata && sess.metadata.invoice_id) {
          await markPaid(sess.metadata.invoice_id, 'Stripe' + (sess.payment_method_types ? ' (' + sess.payment_method_types.join('/') + ')' : ''), sess.payment_intent || sess.id);
        }
      }
      return res.status(200).json({ received: true });
    }
    if (p === 'mollie') {
      if (!process.env.MOLLIE_API_KEY) return res.status(400).end();
      const buf = (await raw(req)).toString('utf8');
      const id = new URLSearchParams(buf).get('id') || (() => { try { return JSON.parse(buf).id; } catch (e) { return ''; } })();
      if (!/^tr_\w+$/.test(id || '')) return res.status(200).end();
      const r = await fetch(`https://api.mollie.com/v2/payments/${id}`, { headers: { Authorization: `Bearer ${process.env.MOLLIE_API_KEY}` } });
      const pay = await r.json();
      if (pay.status === 'paid' && pay.metadata && pay.metadata.invoice_id) await markPaid(pay.metadata.invoice_id, 'Mollie (' + (pay.method || '') + ')', pay.id);
      return res.status(200).end();
    }
    return res.status(404).end();
  } catch (e) {
    console.error('webhook', p, e && e.message);
    return res.status(500).end();
  }
};
module.exports.config = { api: { bodyParser: false } };
