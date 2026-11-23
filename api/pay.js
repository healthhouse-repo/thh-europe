// Online payment of invoices. Stripe Checkout (cards, wallets, PromptPay, Alipay, WeChat Pay, …) and
// optionally Mollie (iDEAL, Bancontact, SEPA – needs an EU-registered business). Bank transfer is always shown when BANK_DETAILS is set.
const S = require('../lib/store');
const H = require('../lib/http');

function methods() {
  return {
    stripe: !!process.env.STRIPE_SECRET_KEY,
    mollie: !!process.env.MOLLIE_API_KEY,
    bank: !!process.env.BANK_DETAILS,
    bank_details: process.env.BANK_DETAILS || '',
  };
}
const origin = (req) => H.SITE() || `https://${req.headers['x-forwarded-host'] || req.headers.host}`;

module.exports = async (req, res) => {
  res.setHeader('Cache-Control', 'no-store');
  const a = H.query(req).a || '';
  if (a === 'methods') return res.status(200).json(methods());
  if (req.method !== 'POST' || a !== 'checkout') return res.status(405).json({ error: 'Method not allowed' });
  const s = H.customerSession(req);
  if (!s) return res.status(401).json({ error: 'Please log in.' });
  const b = await H.rawBody(req);
  try {
    const inv = await S.invoice(String(b.invoice_id || ''));
    if (!inv || inv.customer_id !== s.cid) return res.status(404).json({ error: 'Invoice not found.' });
    if (inv.status !== 'open') return res.status(400).json({ error: 'This invoice is already paid or cancelled.' });
    if (inv.demo || !S.LIVE) return res.status(400).json({ error: 'Demo invoice: online payment is switched off in the demo.' });
    const cust = await S.customerById(s.cid);
    const back = `${origin(req)}/account?invoice=${inv.id}`;
    const cents = Math.round(Number(inv.total) * 100);

    if (b.provider === 'mollie') {
      if (!process.env.MOLLIE_API_KEY) return res.status(400).json({ error: 'This payment method is not available.' });
      const r = await fetch('https://api.mollie.com/v2/payments', {
        method: 'POST',
        headers: { Authorization: `Bearer ${process.env.MOLLIE_API_KEY}`, 'Content-Type': 'application/json' },
        body: JSON.stringify({
          amount: { currency: inv.currency, value: (cents / 100).toFixed(2) },
          description: `Eleven Fashion invoice ${inv.number}`,
          redirectUrl: back + '&paid=1', webhookUrl: `${origin(req)}/api/webhook?p=mollie`,
          metadata: { invoice_id: inv.id },
        }),
      });
      const j = await r.json();
      if (!r.ok || !j._links || !j._links.checkout) { console.error('mollie', r.status, JSON.stringify(j).slice(0, 300)); return res.status(502).json({ error: 'Payment could not be started. Please try another method.' }); }
      return res.status(200).json({ url: j._links.checkout.href });
    }

    if (!process.env.STRIPE_SECRET_KEY) return res.status(400).json({ error: 'Online payment is not available yet. Please use bank transfer.' });
    const f = new URLSearchParams();
    f.set('mode', 'payment');
    f.set('client_reference_id', inv.id);
    f.set('customer_email', cust.email);
    f.set('metadata[invoice_id]', inv.id);
    f.set('payment_intent_data[metadata][invoice_id]', inv.id);
    f.set('payment_intent_data[description]', `Eleven Fashion invoice ${inv.number}`);
    f.set('line_items[0][quantity]', '1');
    f.set('line_items[0][price_data][currency]', inv.currency.toLowerCase());
    f.set('line_items[0][price_data][unit_amount]', String(cents));
    f.set('line_items[0][price_data][product_data][name]', `Invoice ${inv.number}`);
    f.set('success_url', back + '&paid=1');
    f.set('cancel_url', back);
    // No payment_method_types: Stripe shows every method switched on in the dashboard that fits the currency and the buyer's country.
    const r = await fetch('https://api.stripe.com/v1/checkout/sessions', {
      method: 'POST', headers: { Authorization: `Bearer ${process.env.STRIPE_SECRET_KEY}`, 'Content-Type': 'application/x-www-form-urlencoded' }, body: f,
    });
    const j = await r.json();
    if (!r.ok || !j.url) { console.error('stripe', r.status, j.error && j.error.message); return res.status(502).json({ error: 'Payment could not be started. Please try again.' }); }
    return res.status(200).json({ url: j.url });
  } catch (e) {
    console.error('pay', e && e.message);
    return res.status(500).json({ error: 'Something went wrong. Please try again.' });
  }
};
module.exports.methods = methods;
