// Printable invoice. Customers load it via their session; the Eleven team via the admin session (?admin=1).
(function () {
  var esc = function (s) { return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); };
  var q = new URLSearchParams(location.search), id = q.get('id') || '';
  var url = q.get('admin') ? '/api/admin?a=invoice-view&id=' : '/api/account?a=invoice&id=';
  function money(cur, n) { try { return new Intl.NumberFormat('en-GB', { style: 'currency', currency: cur }).format(n); } catch (e) { return cur + ' ' + Number(n).toFixed(2); } }
  function d(s) { return s ? new Date(s).toLocaleDateString('en-GB', { day: 'numeric', month: 'long', year: 'numeric' }) : ''; }
  fetch(url + encodeURIComponent(id), { credentials: 'same-origin' }).then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); }).then(function (x) {
    var el = document.getElementById('inv');
    if (!x.ok) { el.innerHTML = '<p>' + esc(x.j.error || 'Invoice not found.') + ' <a href="/account">Log in</a></p>'; return; }
    var i = x.j.invoice, c = x.j.customer || {}, s = x.j.seller, o = x.j.order, a = c.address || {};
    document.title = 'Invoice ' + i.number + ' | Eleven Fashion';
    var st = i.status === 'paid' ? '<span class="stamp paid">Paid ' + d(i.paid_at) + '</span>' : i.status === 'void' ? '<span class="stamp void">Cancelled</span>' : '';
    el.innerHTML =
      (x.j.demo || i.demo ? '<div class="demo-banner">Demo invoice – sample data, not a real invoice.</div>' : '') +
      '<div class="inv-head"><div><div class="inv-logo">ELEVEN<i>|</i>FASHION</div><p>' + esc(s.name) + '<br>' + esc(s.address) + '<br>' + esc(s.email) + ' · ' + esc(s.phone) + (s.tax_id ? '<br>Tax ID: ' + esc(s.tax_id) : '') + '</p></div>' +
      '<div class="inv-meta"><h1>Invoice</h1>' + st + '<table><tr><td>Invoice number</td><td><b>' + esc(i.number) + '</b></td></tr><tr><td>Invoice date</td><td>' + d(i.issued_at) + '</td></tr>' + (i.due_date ? '<tr><td>Due date</td><td>' + d(i.due_date) + '</td></tr>' : '') + (o ? '<tr><td>Order</td><td>' + esc(o.number) + '</td></tr>' : '') + '</table></div></div>' +
      '<div class="inv-to"><small>Bill to</small><p><b>' + esc(c.company || c.name) + '</b><br>' + (c.company ? esc(c.name) + '<br>' : '') + [a.line1, a.line2, [a.postcode, a.city].filter(Boolean).join(' '), a.state, a.country || c.country].filter(Boolean).map(esc).join('<br>') + (c.vat ? '<br>VAT / tax no.: ' + esc(c.vat) : '') + '<br>' + esc(c.email) + '</p></div>' +
      '<table class="inv-lines"><thead><tr><th>Description</th><th>Qty</th><th>Unit price</th><th>Amount</th></tr></thead><tbody>' +
      (i.lines || []).map(function (l) { return '<tr><td>' + esc(l.description) + '</td><td>' + esc(l.qty) + '</td><td>' + money(i.currency, l.price) + '</td><td>' + money(i.currency, l.qty * l.price) + '</td></tr>'; }).join('') +
      '</tbody><tfoot><tr><td colspan="3">Subtotal</td><td>' + money(i.currency, i.subtotal) + '</td></tr>' + (i.tax_rate ? '<tr><td colspan="3">VAT ' + esc(i.tax_rate) + '%</td><td>' + money(i.currency, i.tax) + '</td></tr>' : '') + '<tr class="tot"><td colspan="3">Total</td><td>' + money(i.currency, i.total) + '</td></tr></tfoot></table>' +
      (i.notes ? '<p class="inv-notes">' + esc(i.notes) + '</p>' : '') +
      '<div class="inv-pay">' + (i.status === 'paid' ? '<p>Paid' + (i.payment_method ? ' via ' + esc(i.payment_method) : '') + '. Thank you!</p>' :
        '<p><b>How to pay</b><br>Pay online in your Eleven Fashion account (card, wallets, PromptPay, iDEAL and more) or by bank transfer, quoting <b>' + esc(i.number) + '</b>.</p>' + (s.bank ? '<p>' + esc(s.bank).replace(/\n/g, '<br>') + '</p>' : '')) + '</div>';
  });
})();
