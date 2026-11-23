// Customer account: login with e-mail code, orders with 5-step tracker, mockup approval, invoices and payment.
(function () {
  var $ = function (id) { return document.getElementById(id); };
  var esc = function (s) { return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); };
  var D = null, METHODS = null;
  function toast(m) { var t = $('toast'); t.textContent = m; t.hidden = false; clearTimeout(toast.t); toast.t = setTimeout(function () { t.hidden = true; }, 3200); }
  function api(a, body) {
    return fetch('/api/account?a=' + a, body ? { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body), credentials: 'same-origin' } : { credentials: 'same-origin' })
      .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, status: r.status, j: j }; }); });
  }
  function err(id, m) { var e = $(id); e.textContent = m || ''; e.hidden = !m; }
  function date(s) { if (!s) return ''; var d = new Date(s); return d.toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric' }); }
  function money(cur, n) { try { return new Intl.NumberFormat(undefined, { style: 'currency', currency: cur }).format(n); } catch (e) { return cur + ' ' + Number(n).toFixed(2); } }

  function load() {
    api('me').then(function (x) {
      if (x.status === 401) { showLogin(x.j.demo); return; }
      if (!x.ok) { showLogin(false); err('lErr', x.j.error); return; }
      D = x.j; showDash();
    });
  }
  function showLogin(demo) { $('login').hidden = false; $('dash').hidden = true; $('demoBox').hidden = !demo; }

  // ---- login ----
  var email = '';
  $('f1').onsubmit = function (e) {
    e.preventDefault(); err('lErr');
    email = $('lEmail').value.trim();
    api('start', { email: email }).then(function (x) {
      if (!x.ok) return err('lErr', x.j.error);
      if (x.j.demo) { $('demoBox').hidden = false; return err('lErr', x.j.message); }
      $('f1').hidden = true; $('f2').hidden = false; $('codeInfo').textContent = x.j.message; $('lCode').focus();
    });
  };
  $('f2').onsubmit = function (e) {
    e.preventDefault(); err('lErr');
    api('verify', { email: email, code: $('lCode').value }).then(function (x) { if (!x.ok) return err('lErr', x.j.error); load(); });
  };
  $('again').onclick = function () { $('f2').hidden = true; $('f1').hidden = false; err('lErr'); };
  $('demoBtn').onclick = function () { api('demo', {}).then(function (x) { if (!x.ok) return err('lErr', x.j.error); load(); }); };
  $('logout').onclick = function () { api('logout', {}).then(function () { D = null; showLogin(false); load(); }); };

  // ---- dashboard ----
  document.querySelector('.atabs').onclick = function (e) {
    var b = e.target.closest('.atab'); if (!b) return; tab(b.dataset.t);
  };
  function tab(t) {
    document.querySelectorAll('.atab').forEach(function (x) { x.classList.toggle('on', x.dataset.t === t); });
    document.querySelectorAll('.apane').forEach(function (x) { x.classList.toggle('on', x.dataset.p === t); });
  }
  function showDash() {
    $('login').hidden = true; $('dash').hidden = false;
    var c = D.customer;
    $('hello').textContent = 'Hello ' + (c.name || '').split(' ')[0];
    $('demoBanner').hidden = !D.demo;
    renderOrders(); renderInvoices(); fillProfile();
    var q = new URLSearchParams(location.search);
    if (q.get('paid')) { toast('Thank you! Your payment is being processed.'); tab('invoices'); history.replaceState(null, '', '/account'); }
    else if (q.get('invoice')) { tab('invoices'); }
  }

  function tracker(o) {
    if (o.status === 'cancelled') return '<div class="trk cancelled">Cancelled</div>';
    var i = D.stages.indexOf(o.status);
    return '<ol class="trk">' + D.stages.map(function (s, n) {
      return '<li class="' + (n < i ? 't-done' : n === i ? 't-cur' : '') + '"><i></i><span>' + esc(D.labels[s]) + '</span></li>';
    }).join('') + '</ol>';
  }
  function items(o) {
    return (o.items || []).map(function (it) {
      var sz = Object.keys(it.sizes || {}).map(function (k) { return k + ' ' + it.sizes[k]; }).join(' · ');
      return '<li><b>' + esc(it.product) + '</b> · ' + esc(it.quantity) + ' pcs' + (it.weight ? ' · ' + esc(it.weight) : '') + ((it.colors || []).length ? ' · ' + esc(it.colors.join(', ')) : '') + ((it.decoration || []).length ? ' · ' + esc(it.decoration.join(', ')) : '') + (sz ? '<br><small>' + esc(sz) + '</small>' : '') + '</li>';
    }).join('');
  }
  function mockupBlock(o) {
    if (!(o.mockups || []).length) return '';
    var imgs = '<div class="mks">' + o.mockups.map(function (m) { return '<a href="' + esc(m.url) + '" target="_blank" rel="noopener"><img src="' + esc(m.url) + '" alt=""><span>' + esc(m.label) + '</span></a>'; }).join('') + '</div>';
    if (o.mockup_status === 'pending') {
      return '<div class="mkbox pending"><h4>Your mockup is ready</h4><p>Please check placement, colours, sizes and spelling. Production starts after your approval.</p>' + imgs +
        '<textarea id="fb-' + o.id + '" placeholder="Comments or changes (required if you ask for changes)"></textarea>' +
        '<div class="hbtns"><button class="btn yellow" data-mk="approve" data-o="' + o.id + '">Approve mockup</button><button class="btn ghost" data-mk="changes" data-o="' + o.id + '">Request changes</button></div></div>';
    }
    var st = o.mockup_status === 'approved' ? 'Approved' : o.mockup_status === 'changes' ? 'Changes requested – we are working on a new version' : '';
    return '<div class="mkbox"><h4>Mockup' + (st ? ' · <span class="muted">' + st + '</span>' : '') + '</h4>' + imgs + '</div>';
  }
  function shipBlock(o) {
    if (!o.tracking_number && !o.carrier) return '';
    return '<div class="ship"><div><small>Carrier</small><b>' + esc(o.carrier || '–') + '</b></div><div><small>Tracking number</small><b>' + esc(o.tracking_number || '–') + '</b></div>' +
      (o.eta ? '<div><small>Expected</small><b>' + date(o.eta) + '</b></div>' : '') +
      (o.tracking_url ? '<a class="btn ghost" href="' + esc(o.tracking_url) + '" target="_blank" rel="noopener">Track shipment</a>' : '') + '</div>';
  }
  function renderOrders() {
    var os = D.orders || [];
    $('orders').innerHTML = os.length ? os.map(function (o) {
      var invs = (D.invoices || []).filter(function (i) { return i.order_id === o.id && i.status === 'open'; });
      return '<article class="ocard">' +
        '<div class="ohead"><div><small>' + esc(o.number) + ' · ' + date(o.created_at) + (o.quote_ref ? ' · Quote ' + esc(o.quote_ref) : '') + '</small><h3>' + esc(o.title || 'Order') + '</h3></div>' +
        '<span class="badge s-' + esc(o.status) + '">' + esc(D.labels[o.status] || o.status) + '</span></div>' +
        tracker(o) + mockupBlock(o) + shipBlock(o) +
        (invs.length ? '<div class="due">Open invoice: ' + invs.map(function (i) { return '<b>' + esc(i.number) + '</b> ' + money(i.currency, i.total) + ' <button class="linkbtn" data-pay="' + i.id + '">Pay now</button>'; }).join(' · ') + '</div>' : '') +
        '<details class="odet"><summary>Order details and history</summary><ul class="oitems">' + items(o) + '</ul><ul class="hist">' +
        (o.events || []).slice().reverse().map(function (e) { return '<li><small>' + date(e.created_at) + '</small> ' + esc(e.note) + '</li>'; }).join('') + '</ul></details>' +
        '</article>';
    }).join('') : '<div class="empty">You have no orders yet. <a href="/design">Design a product</a> and request a quote: it appears here right away.</div>';
  }
  $('orders').onclick = function (e) {
    var b = e.target.closest('[data-mk]');
    if (b) {
      var fb = $('fb-' + b.dataset.o).value.trim();
      if (b.dataset.mk === 'changes' && !fb) { toast('Please describe the changes you would like.'); return; }
      b.disabled = true;
      api('mockup', { order_id: b.dataset.o, decision: b.dataset.mk, feedback: fb }).then(function (x) {
        b.disabled = false; if (!x.ok) return toast(x.j.error);
        D = x.j; renderOrders(); toast(b.dataset.mk === 'approve' ? 'Thank you, your mockup is approved.' : 'Thank you, we will send you a new version.');
      });
      return;
    }
    var p = e.target.closest('[data-pay]'); if (p) openPay(p.dataset.pay);
  };

  function renderInvoices() {
    var is = D.invoices || [];
    $('invoices').innerHTML = is.length ? '<div class="tablewrap"><table class="itable"><thead><tr><th>Invoice</th><th>Date</th><th>Order</th><th>Amount</th><th>Status</th><th></th></tr></thead><tbody>' + is.map(function (i) {
      var o = (D.orders || []).find(function (x) { return x.id === i.order_id; });
      return '<tr><td><b>' + esc(i.number) + '</b></td><td>' + date(i.issued_at) + (i.status === 'open' && i.due_date ? '<br><small class="muted">due ' + date(i.due_date) + '</small>' : '') + '</td><td>' + esc(o ? o.number : '') + '</td><td>' + money(i.currency, i.total) + '</td><td><span class="badge i-' + esc(i.status) + '">' + (i.status === 'paid' ? 'Paid' : i.status === 'void' ? 'Cancelled' : 'Open') + '</span></td>' +
        '<td class="acts"><a class="linkbtn" href="/invoice?id=' + i.id + '" target="_blank">View / PDF</a>' + (i.status === 'open' ? '<button class="btn yellow sm" data-pay="' + i.id + '">Pay</button>' : '') + '</td></tr>';
    }).join('') + '</tbody></table></div>' : '<div class="empty">No invoices yet.</div>';
  }
  $('invoices').onclick = function (e) { var p = e.target.closest('[data-pay]'); if (p) openPay(p.dataset.pay); };

  // ---- payment ----
  function openPay(id) {
    var inv = (D.invoices || []).find(function (i) { return i.id === id; }); if (!inv) return;
    err('payErr');
    $('payTitle').textContent = inv.number + ' · ' + money(inv.currency, inv.total);
    var go = function (m) {
      if (D.demo) m = { stripe: true, mollie: true, bank: false };
      var o = D.demo ? ['<p class="muted">Demo: these are the payment options your customers will see. Online payment is switched off in the demo.</p>'] : [];
      if (m.stripe) o.push('<button class="paybtn" data-prov="stripe"><b>Card, wallet or local method</b><span>Visa, Mastercard, AMEX, JCB, UnionPay, Apple Pay, Google Pay, PromptPay' + (inv.currency === 'THB' ? '' : ' (THB only)') + ', TrueMoney, LINE Pay, Alipay, WeChat Pay</span></button>');
      if (m.mollie) o.push('<button class="paybtn" data-prov="mollie"><b>iDEAL, Bancontact or SEPA</b><span>For customers in the Netherlands, Belgium and the rest of Europe' + (inv.currency === 'EUR' ? '' : ' (EUR invoices only)') + '</span></button>');
      o.push('<div class="paybank"><b>Bank transfer</b><span>' + (m.bank ? esc(m.bank_details).replace(/\n/g, '<br>') + '<br>Reference: <b>' + esc(inv.number) + '</b>' : 'Bank details are on your invoice. Please use <b>' + esc(inv.number) + '</b> as reference.') + '</span></div>');
      if (!m.stripe && !m.mollie) o.unshift('<p class="muted">Online payment is not available yet.</p>');
      $('payOpts').innerHTML = o.join('');
      $('payOpts').dataset.inv = id;
      $('payModal').hidden = false;
    };
    if (METHODS) go(METHODS); else fetch('/api/pay?a=methods').then(function (r) { return r.json(); }).then(function (m) { METHODS = m; go(m); });
  }
  $('payModal').onclick = function (e) {
    if (e.target === this || e.target.closest('[data-close]')) { this.hidden = true; return; }
    var b = e.target.closest('[data-prov]'); if (!b) return;
    b.disabled = true; err('payErr');
    fetch('/api/pay?a=checkout', { method: 'POST', headers: { 'Content-Type': 'application/json' }, credentials: 'same-origin', body: JSON.stringify({ invoice_id: $('payOpts').dataset.inv, provider: b.dataset.prov }) })
      .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
      .then(function (x) { b.disabled = false; if (!x.ok) return err('payErr', x.j.error); location.href = x.j.url; });
  };

  // ---- profile ----
  function fillProfile() {
    var c = D.customer, a = c.address || {};
    $('pName').value = c.name || ''; $('pCompany').value = c.company || ''; $('pPhone').value = c.phone || ''; $('pVat').value = c.vat || '';
    $('aLine1').value = a.line1 || ''; $('aLine2').value = a.line2 || ''; $('aPost').value = a.postcode || ''; $('aCity').value = a.city || ''; $('aState').value = a.state || ''; $('aCountry').value = a.country || c.country || '';
    $('pEmail').textContent = c.email;
  }
  $('pf').onsubmit = function (e) {
    e.preventDefault();
    api('profile', { name: $('pName').value, company: $('pCompany').value, phone: $('pPhone').value, vat: $('pVat').value, country: $('aCountry').value,
      address: { line1: $('aLine1').value, line2: $('aLine2').value, postcode: $('aPost').value, city: $('aCity').value, state: $('aState').value, country: $('aCountry').value } })
      .then(function (x) { if (!x.ok) return toast(x.j.error); D = x.j; toast('Saved.'); });
  };

  load();
})();
