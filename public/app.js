/* Eleven Fashion – apparel designer and quote basket */
(function () {
  'use strict';
  var GM = window.GARMENTS;
  var $ = function (id) { return document.getElementById(id); };
  var esc = function (s) { return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); };
  var NS = 'http://www.w3.org/2000/svg';
  var VB = { x: 0, y: -20, w: 400, h: 480 };

  var COLOR_HEX = { 'Black': '#1c1c1c', 'White': '#ffffff', 'Off-white / cream': '#f1ead9', 'Heather grey': '#b9b9b9', 'Charcoal': '#3d3d3d', 'Navy': '#1d2a48', 'Royal blue': '#2348b8', 'Red': '#c4232b', 'Bordeaux': '#6b1d2a', 'Forest green': '#24452f', 'Olive': '#6b6b3a', 'Sand / beige': '#d8c6a3', 'Brown': '#6a4a32', 'Pink': '#f0b3c4' };
  var FONTS = [
    { n: 'Montserrat', w: 800 }, { n: 'Oswald', w: 700 }, { n: 'Bebas Neue', w: 400 }, { n: 'Anton', w: 400 }, { n: 'Open Sans', w: 700 },
    { n: 'Playfair Display', w: 700 }, { n: 'Roboto Slab', w: 700 }, { n: 'Permanent Marker', w: 400 }, { n: 'Pacifico', w: 400 },
    { n: 'Arial', w: 700 }, { n: 'Georgia', w: 700 }, { n: 'Courier New', w: 700 }];
  var TCOLORS = ['#000000', '#ffffff', '#f9bf3b', '#c4232b', '#1d2a48', '#2348b8', '#24452f', '#8a8a8a', '#c9a227', '#f0b3c4'];
  var ONE_SIZE = { cap: 1, bag: 1 };

  var OPT = null, cur = null, basket = [], selId = null, seq = 0, textFont = 'Montserrat', textColor = '#000000';

  function toast(msg) { var t = $('toast'); t.textContent = msg; t.hidden = false; clearTimeout(toast.t); toast.t = setTimeout(function () { t.hidden = true; }, 2600); }
  function prod(id) { return OPT.PRODUCTS.find(function (p) { return p.id === id; }) || OPT.PRODUCTS[0]; }
  function hexOf(c) { return c.name === 'Custom colour (Pantone)' ? c.hex : (COLOR_HEX[c.name] || '#ffffff'); }
  function sizesFor(id) { return ONE_SIZE[id] ? ['One size'] : OPT.SIZES; }
  function qty(s) { var t = 0; for (var k in s.sizes) t += (+s.sizes[k] || 0); return t; }
  function fresh(pid) {
    return { product: pid, product_other: '', weight: '', fit: '', material: '', color: { name: 'Black', hex: COLOR_HEX.Black }, pantone: '', moreColors: '',
      sizes: {}, deco: '', printColors: '', extras: [], notes: '', layers: { front: [], back: [], left: [], right: [] }, view: 'front', editing: null };
  }

  /* ------------------------------------------------------------ init */
  fetch('/api/options').then(function (r) { return r.json(); }).then(function (o) {
    OPT = o;
    $('prodGrid').innerHTML = o.PRODUCTS.map(function (p) {
      return '<button type="button" class="prod" data-p="' + p.id + '"><svg viewBox="0 -20 400 480">' + GM.draw(p.id, 'front', '#ffffff') + '</svg><b>' + esc(p.name) + '</b>' +
        (p.weights.length ? '<small>' + esc(p.weights[0]) + ' – ' + esc(p.weights[p.weights.length - 1]) + '</small>' : '<small>&nbsp;</small>') + '</button>';
    }).join('');
    $('pProduct').innerHTML = o.PRODUCTS.map(function (p) { return '<option value="' + p.id + '">' + esc(p.name) + '</option>'; }).join('');
    $('pMaterial').innerHTML = opts(o.MATERIALS);
    $('pDeco').innerHTML = opts(o.DECORATION);
    $('extras').innerHTML = o.EXTRAS.map(function (x) { return '<button type="button" class="chip" aria-pressed="false" data-x="' + esc(x) + '">' + esc(x) + '</button>'; }).join('');
    $('swatches').innerHTML = Object.keys(COLOR_HEX).map(function (n) { return '<button type="button" class="sw" title="' + n + '" aria-label="' + n + '" data-c="' + n + '" style="background:' + COLOR_HEX[n] + '"></button>'; }).join('') +
      '<button type="button" class="sw custom" title="Custom colour (Pantone)" aria-label="Custom colour" data-c="Custom colour (Pantone)"></button>';
    $('tFont').innerHTML = FONTS.map(function (f) { return '<option style="font-family:\'' + f.n + '\'">' + f.n + '</option>'; }).join('');
    $('tColors').innerHTML = TCOLORS.map(function (c) { return '<button type="button" class="tc" data-tc="' + c + '" style="background:' + c + '"></button>'; }).join('');
    var pre = new URLSearchParams(location.search).get('product');
    cur = fresh(pre && o.PRODUCTS.some(function (p) { return p.id === pre; }) ? pre : 'tshirt');
    loadForm(); render();
  });
  function opts(list, val) { return '<option value="">Choose…</option>' + list.map(function (v) { return '<option' + (v === val ? ' selected' : '') + '>' + esc(v) + '</option>'; }).join('') + '<option' + (val === 'Other / please advise' ? ' selected' : '') + '>Other / please advise</option>'; }

  /* ------------------------------------------------------------ form <-> state */
  function loadForm() {
    var p = prod(cur.product);
    $('pProduct').value = cur.product;
    $('dTitle').textContent = cur.product === 'other' ? 'product' : p.name;
    $('otherWrap').hidden = cur.product !== 'other'; $('pOther').value = cur.product_other;
    $('weightWrap').hidden = !p.weights.length; $('pWeight').innerHTML = opts(p.weights, cur.weight);
    $('fitWrap').hidden = !p.fits.length; $('pFit').innerHTML = opts(p.fits, cur.fit);
    $('pMaterial').value = cur.material; $('pDeco').value = cur.deco; $('pPrintColors').value = cur.printColors; $('pNotes').value = cur.notes;
    $('pMoreColors').value = cur.moreColors; $('pPantone').value = cur.pantone;
    document.querySelectorAll('#extras .chip').forEach(function (c) { c.setAttribute('aria-pressed', cur.extras.indexOf(c.dataset.x) >= 0); });
    document.querySelectorAll('.prod').forEach(function (b) { b.classList.toggle('on', b.dataset.p === cur.product); });
    renderSizes(); renderSwatch();
    $('addToQuote').textContent = cur.editing != null ? 'Update quote' : 'Add to quote';
  }
  function renderSwatch() {
    document.querySelectorAll('.sw').forEach(function (s) { s.classList.toggle('on', s.dataset.c === cur.color.name); });
    $('colName').textContent = '– ' + cur.color.name;
    $('pantoneWrap').hidden = cur.color.name !== 'Custom colour (Pantone)';
    if (cur.color.name === 'Custom colour (Pantone)') $('pPantoneHex').value = cur.color.hex;
  }
  function renderSizes() {
    $('sizeTable').innerHTML = sizesFor(cur.product).map(function (s) {
      return '<tr><td>' + s + '</td><td><div class="stepper"><button type="button" data-st="' + s + '" data-d="-1" aria-label="less">−</button><input type="number" min="0" inputmode="numeric" data-sz="' + s + '" value="' + (cur.sizes[s] || '') + '" placeholder="0"><button type="button" data-st="' + s + '" data-d="1" aria-label="more">+</button></div></td></tr>';
    }).join('');
    updQty();
  }
  function updQty() { var q = qty(cur); $('qtyTotal').textContent = q + ' pcs'; $('barQty').textContent = q + ' pcs'; }

  $('prodGrid').addEventListener('click', function (e) {
    var b = e.target.closest('.prod'); if (!b) return;
    switchProduct(b.dataset.p); $('designer').scrollIntoView({ behavior: 'smooth' });
  });
  function switchProduct(pid) {
    var keep = cur.layers, views = GM.views(pid);
    cur.product = pid; cur.weight = ''; cur.fit = '';
    if (ONE_SIZE[pid]) cur.sizes = {}; else delete cur.sizes['One size'];
    cur.layers = { front: [], back: [], left: [], right: [] };
    views.forEach(function (v) { cur.layers[v] = keep[v] || []; });
    if (views.indexOf(cur.view) < 0) cur.view = 'front';
    views.forEach(function (v) { cur.layers[v].forEach(function (l) { clamp(l, v); }); });
    selId = null; loadForm(); render();
  }
  $('pProduct').onchange = function () { switchProduct(this.value); };
  $('pOther').oninput = function () { cur.product_other = this.value; };
  $('pWeight').onchange = function () { cur.weight = this.value; };
  $('pFit').onchange = function () { cur.fit = this.value; };
  $('pMaterial').onchange = function () { cur.material = this.value; };
  $('pDeco').onchange = function () { cur.deco = this.value; };
  $('pPrintColors').onchange = function () { cur.printColors = this.value; };
  $('pNotes').oninput = function () { cur.notes = this.value; };
  $('pMoreColors').oninput = function () { cur.moreColors = this.value; };
  $('pPantone').oninput = function () { cur.pantone = this.value; };
  $('pPantoneHex').oninput = function () { cur.color.hex = this.value; render(); };
  $('extras').onclick = function (e) { var c = e.target.closest('.chip'); if (!c) return; var i = cur.extras.indexOf(c.dataset.x); if (i >= 0) cur.extras.splice(i, 1); else cur.extras.push(c.dataset.x); c.setAttribute('aria-pressed', i < 0); };
  $('swatches').onclick = function (e) { var s = e.target.closest('.sw'); if (!s) return; cur.color = { name: s.dataset.c, hex: s.dataset.c === 'Custom colour (Pantone)' ? $('pPantoneHex').value : COLOR_HEX[s.dataset.c] }; renderSwatch(); render(); };
  $('sizeTable').addEventListener('click', function (e) { var b = e.target.closest('[data-st]'); if (!b) return; var s = b.dataset.st; cur.sizes[s] = Math.max(0, (+cur.sizes[s] || 0) + (+b.dataset.d)); if (!cur.sizes[s]) delete cur.sizes[s]; renderSizes(); });
  $('sizeTable').addEventListener('input', function (e) { var i = e.target; if (!i.dataset.sz) return; var n = Math.max(0, Math.min(100000, parseInt(i.value, 10) || 0)); if (n) cur.sizes[i.dataset.sz] = n; else delete cur.sizes[i.dataset.sz]; updQty(); });

  // tabs
  function tab(name) { document.querySelectorAll('.tab').forEach(function (t) { t.classList.toggle('on', t.dataset.tab === name); }); document.querySelectorAll('.pane').forEach(function (p) { p.classList.toggle('on', p.dataset.pane === name); }); }
  document.querySelector('.tabs').onclick = function (e) { var t = e.target.closest('.tab'); if (t) tab(t.dataset.tab); };
  document.querySelectorAll('[data-go]').forEach(function (b) { b.onclick = function () { tab(b.dataset.go); }; });

  /* ------------------------------------------------------------ canvas rendering */
  function area(view) { return GM.area(cur.product, view || cur.view); }
  function layerMarkup(l) {
    var t = 'translate(' + l.x.toFixed(1) + ' ' + l.y.toFixed(1) + ') rotate(' + (l.rot || 0) + ')';
    var inner;
    if (l.type === 'img') inner = '<image href="' + l.src + '" x="' + (-l.w / 2) + '" y="' + (-l.h / 2) + '" width="' + l.w + '" height="' + l.h + '" preserveAspectRatio="xMidYMid meet"/>';
    else if (l.type === 'text') inner = '<text text-anchor="middle" dominant-baseline="central" font-family="' + esc(l.font) + '" font-weight="' + l.weight + '" font-size="' + l.fs + '" fill="' + l.color + '">' + esc(l.text) + '</text>';
    else inner = '<rect x="' + (-l.w / 2) + '" y="' + (-l.h / 2) + '" width="' + l.w + '" height="' + l.h + '" fill="rgba(249,191,59,.25)" stroke="#b58a1d" stroke-dasharray="4 3"/><text text-anchor="middle" dominant-baseline="central" font-family="Montserrat" font-size="9" font-weight="700" fill="#5a4310">' + esc(l.name.length > 22 ? l.name.slice(0, 20) + '…' : l.name) + '</text>';
    return '<g class="layer" data-id="' + l.id + '" transform="' + t + '">' + inner + '</g>';
  }
  function svgMarkup(view, withUi) {
    var a = area(view), cid = 'clip-' + view + (withUi ? 'm' : 't');
    var s = GM.draw(cur.product, view, hexOf(cur.color));
    s += '<defs><clipPath id="' + cid + '"><rect x="' + a.x + '" y="' + a.y + '" width="' + a.w + '" height="' + a.h + '"/></clipPath></defs>';
    if (withUi) s += '<rect class="area" x="' + a.x + '" y="' + a.y + '" width="' + a.w + '" height="' + a.h + '"/>';
    s += '<g clip-path="url(#' + cid + ')">' + cur.layers[view].map(layerMarkup).join('') + '</g>';
    if (withUi && selId) {
      var l = cur.layers[view].find(function (x) { return x.id === selId; });
      if (l) {
        var w = l.w, h = l.h, t = 'translate(' + l.x + ' ' + l.y + ') rotate(' + (l.rot || 0) + ')';
        s += '<g transform="' + t + '" id="selG"><rect class="sel-box" x="' + (-w / 2 - 3) + '" y="' + (-h / 2 - 3) + '" width="' + (w + 6) + '" height="' + (h + 6) + '"/>' +
          '<rect class="handle" data-h="rs" x="' + (w / 2 - 4) + '" y="' + (h / 2 - 4) + '" width="10" height="10"/>' +
          '<g class="delh" data-h="del" transform="translate(' + (w / 2 + 3) + ' ' + (-h / 2 - 3) + ')"><circle r="7"/><path d="M-3 -3 L3 3 M3 -3 L-3 3" stroke="#fff" stroke-width="1.6"/></g></g>';
      }
    }
    return s;
  }
  function render() {
    if (!cur) return;
    var svg = $('canvas');
    svg.innerHTML = svgMarkup(cur.view, true);
    // measure text layers (bbox in local units)
    var changed = false;
    cur.layers[cur.view].forEach(function (l) {
      if (l.type !== 'text') return;
      var g = svg.querySelector('.layer[data-id="' + l.id + '"] text'); if (!g) return;
      var b = g.getBBox(), ar = area();
      if (l.fit && b.width > ar.w * 0.92) { var k = (ar.w * 0.92) / b.width; l.fs *= k; b = { width: b.width * k, height: b.height * k }; }
      l.fit = false;
      if (Math.abs(b.width - l.w) > .5 || Math.abs(b.height - l.h) > .5) { l.w = b.width; l.h = b.height; changed = true; }
    });
    if (changed) svg.innerHTML = svgMarkup(cur.view, true);
    var views = GM.views(cur.product);
    $('views').innerHTML = views.map(function (v) {
      return '<button type="button" class="view' + (v === cur.view ? ' on' : '') + '" data-v="' + v + '"><svg viewBox="0 -20 400 480">' + svgMarkup(v, false) + '</svg>' + GM.VIEW_NAMES[v] + (cur.layers[v].length ? '<span class="dot"></span>' : '') + '</button>';
    }).join('');
    $('curView').textContent = GM.VIEW_NAMES[cur.view];
    $('stageHint').hidden = views.some(function (v) { return cur.layers[v].length; });
    var sel = selLayer(); $('selbar').hidden = !sel;
    if (sel) { var a = area(); $('selSize').value = Math.round(sel.w / a.w * 100); $('selRot').value = sel.rot || 0; }
  }
  $('views').onclick = function (e) { var b = e.target.closest('.view'); if (!b) return; cur.view = b.dataset.v; selId = null; render(); };
  function selLayer() { return selId ? cur.layers[cur.view].find(function (x) { return x.id === selId; }) : null; }
  function clamp(l, view) { var a = area(view); l.x = Math.max(a.x, Math.min(a.x + a.w, l.x)); l.y = Math.max(a.y, Math.min(a.y + a.h, l.y)); }

  /* ------------------------------------------------------------ pointer interaction */
  var svgEl = $('canvas'), drag = null;
  function pt(e) { var p = svgEl.createSVGPoint(); p.x = e.clientX; p.y = e.clientY; return p.matrixTransform(svgEl.getScreenCTM().inverse()); }
  svgEl.addEventListener('pointerdown', function (e) {
    var h = e.target.closest('[data-h]'), lg = e.target.closest('.layer');
    if (h && h.dataset.h === 'del') { removeSel(); return; }
    if (h && h.dataset.h === 'rs') {
      var l = selLayer(), p = pt(e);
      drag = { mode: 'rs', l: l, d0: Math.hypot(p.x - l.x, p.y - l.y), w0: l.w, h0: l.h, fs0: l.fs };
    } else if (lg) {
      selId = +lg.dataset.id; render();
      var l2 = selLayer(), p2 = pt(e); drag = { mode: 'mv', l: l2, dx: p2.x - l2.x, dy: p2.y - l2.y };
    } else { if (selId) { selId = null; render(); } return; }
    svgEl.setPointerCapture(e.pointerId); e.preventDefault();
  });
  svgEl.addEventListener('pointermove', function (e) {
    if (!drag) return; var p = pt(e), l = drag.l;
    if (drag.mode === 'mv') { l.x = p.x - drag.dx; l.y = p.y - drag.dy; clamp(l); }
    else { var f = Math.max(.15, Math.min(6, Math.hypot(p.x - l.x, p.y - l.y) / drag.d0)); var a = area(); f = Math.min(f, (a.w * 1.2) / drag.w0); l.w = drag.w0 * f; l.h = drag.h0 * f; if (l.type === 'text') l.fs = drag.fs0 * f; }
    var g = svgEl.querySelector('.layer[data-id="' + l.id + '"]');
    if (drag.mode === 'mv' && g) { var t = 'translate(' + l.x.toFixed(1) + ' ' + l.y.toFixed(1) + ') rotate(' + (l.rot || 0) + ')'; g.setAttribute('transform', t); var sg = $('selG'); if (sg) sg.setAttribute('transform', t); }
    else render();
  });
  function endDrag() { if (drag) { drag = null; render(); } }
  svgEl.addEventListener('pointerup', endDrag); svgEl.addEventListener('pointercancel', endDrag);
  document.addEventListener('keydown', function (e) { if ((e.key === 'Delete' || e.key === 'Backspace') && selId && !/INPUT|TEXTAREA|SELECT/.test(document.activeElement.tagName)) { removeSel(); e.preventDefault(); } });
  function removeSel() { cur.layers[cur.view] = cur.layers[cur.view].filter(function (x) { return x.id !== selId; }); selId = null; render(); }
  $('selDel').onclick = removeSel;
  $('selCenter').onclick = function () { var l = selLayer(), a = area(); if (!l) return; l.x = a.x + a.w / 2; render(); };
  $('selFront').onclick = function () { var arr = cur.layers[cur.view], l = selLayer(); if (!l) return; arr.splice(arr.indexOf(l), 1); arr.push(l); render(); };
  $('selSize').oninput = function () { var l = selLayer(), a = area(); if (!l) return; var nw = a.w * this.value / 100, f = nw / l.w; l.w = nw; l.h *= f; if (l.type === 'text') l.fs *= f; render(); };
  $('selRot').oninput = function () { var l = selLayer(); if (!l) return; l.rot = +this.value; render(); };

  /* ------------------------------------------------------------ logo upload */
  var drop = $('drop'), fileIn = $('file');
  drop.onclick = function () { fileIn.click(); };
  drop.onkeydown = function (e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); fileIn.click(); } };
  ['dragenter', 'dragover'].forEach(function (ev) { drop.addEventListener(ev, function (e) { e.preventDefault(); drop.classList.add('over'); }); });
  ['dragleave', 'drop'].forEach(function (ev) { drop.addEventListener(ev, function (e) { e.preventDefault(); drop.classList.remove('over'); }); });
  drop.addEventListener('drop', function (e) { if (e.dataTransfer.files[0]) addFile(e.dataTransfer.files[0]); });
  fileIn.onchange = function () { if (fileIn.files[0]) addFile(fileIn.files[0]); fileIn.value = ''; };

  function addFile(f) {
    if (f.size > 4 * 1024 * 1024) { toast('File is larger than 4 MB. Add a link under "Your quote" instead.'); return; }
    var a = area(), l = { id: ++seq, type: 'file', name: f.name, x: a.x + a.w / 2, y: a.y + a.h * 0.38, w: a.w * 0.6, h: a.w * 0.3, rot: 0, url: '', status: 'uploading' };
    var raster = /^image\/(png|jpe?g|webp)$/.test(f.type), isSvg = f.type === 'image/svg+xml' || /\.svg$/i.test(f.name);
    var rd = new FileReader();
    rd.onload = function () {
      if (raster || isSvg) {
        var img = new Image();
        img.onload = function () {
          var src = rd.result;
          if (raster && $('rmWhite').checked) src = knockWhite(img) || src;
          var ar = (img.naturalHeight || 1) / (img.naturalWidth || 1), w = a.w * 0.6, h = w * ar;
          if (h > a.h * 0.6) { h = a.h * 0.6; w = h / ar; }
          l.type = 'img'; l.src = src; l.w = w; l.h = h; render();
        };
        img.src = rd.result;
      }
    };
    rd.readAsDataURL(f);
    cur.layers[cur.view].push(l); selId = l.id; render();
    // original file to the server for Eleven
    fetch('/api/upload', { method: 'POST', headers: { 'Content-Type': f.type || 'application/octet-stream', 'X-Filename': f.name }, body: f })
      .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
      .then(function (x) { if (x.ok) { l.url = x.j.url; l.status = 'ok'; } else { l.status = 'err'; toast(x.j.error || 'Upload failed'); } })
      .catch(function () { l.status = 'err'; toast('Upload failed – please try again'); });
  }
  function knockWhite(img) {
    try {
      var c = document.createElement('canvas'), m = Math.min(1, 1600 / Math.max(img.naturalWidth, img.naturalHeight));
      c.width = Math.round(img.naturalWidth * m); c.height = Math.round(img.naturalHeight * m);
      var x = c.getContext('2d'); x.drawImage(img, 0, 0, c.width, c.height);
      var d = x.getImageData(0, 0, c.width, c.height), p = d.data, hit = 0;
      for (var i = 0; i < p.length; i += 4) { if (p[i] > 238 && p[i + 1] > 238 && p[i + 2] > 238) { p[i + 3] = 0; hit++; } }
      if (!hit) return null;
      x.putImageData(d, 0, 0); return c.toDataURL('image/png');
    } catch (e) { return null; }
  }

  /* ------------------------------------------------------------ text */
  $('tColors').onclick = function (e) { var b = e.target.closest('.tc'); if (!b) return; textColor = b.dataset.tc; markTc(); var l = selLayer(); if (l && l.type === 'text') { l.color = textColor; render(); } };
  function markTc() { document.querySelectorAll('.tc').forEach(function (b) { b.classList.toggle('on', b.dataset.tc === textColor); }); }
  $('tFont').onchange = function () { textFont = this.value; var l = selLayer(); if (l && l.type === 'text') { var f = FONTS.find(function (x) { return x.n === textFont; }); l.font = f.n; l.weight = f.w; loadFont(f).then(render); } };
  function loadFont(f) { try { return document.fonts.load(f.w + ' 40px "' + f.n + '"').catch(function () {}); } catch (e) { return Promise.resolve(); } }
  function addText() {
    var t = $('tText').value.trim(); if (!t) { $('tText').focus(); return; }
    var a = area(), f = FONTS.find(function (x) { return x.n === textFont; }) || FONTS[0];
    var auto = textColor; if (auto === '#000000' && GM.luminance(hexOf(cur.color)) < 0.35) auto = '#ffffff';
    var l = { id: ++seq, type: 'text', text: t, font: f.n, weight: f.w, color: auto, fs: Math.max(12, Math.min(34, a.w / Math.max(4, t.length) * 1.5)), x: a.x + a.w / 2, y: a.y + a.h * 0.7, w: 10, h: 10, rot: 0, fit: true };
    cur.layers[cur.view].push(l); selId = l.id; $('tText').value = '';
    loadFont(f).then(render);
  }
  $('tAdd').onclick = addText;
  $('tText').onkeydown = function (e) { if (e.key === 'Enter') { e.preventDefault(); addText(); } };

  /* ------------------------------------------------------------ mockups (PNG per decorated side) */
  function loadImg(src) { return new Promise(function (res, rej) { var i = new Image(); i.onload = function () { res(i); }; i.onerror = rej; i.src = src; }); }
  function mockup(view) {
    var S = 2, W = VB.w * S, H = VB.h * S, c = document.createElement('canvas'); c.width = W; c.height = H;
    var x = c.getContext('2d'); x.fillStyle = '#f4f4f4'; x.fillRect(0, 0, W, H);
    var g = '<svg xmlns="' + NS + '" viewBox="0 -20 400 480" width="' + W + '" height="' + H + '">' + GM.draw(cur.product, view, hexOf(cur.color)) + '</svg>';
    var url = URL.createObjectURL(new Blob([g], { type: 'image/svg+xml' }));
    var ls = cur.layers[view];
    return loadImg(url).then(function (gi) {
      x.drawImage(gi, 0, 0, W, H); URL.revokeObjectURL(url);
      return Promise.all(ls.map(function (l) { return l.type === 'img' ? loadImg(l.src) : l.type === 'text' ? loadFont({ n: l.font, w: l.weight }) : null; }));
    }).then(function (imgs) {
      var a = area(view);
      x.save(); x.beginPath(); x.rect((a.x - VB.x) * S, (a.y - VB.y) * S, a.w * S, a.h * S); x.clip();
      ls.forEach(function (l, i) {
        x.save(); x.translate((l.x - VB.x) * S, (l.y - VB.y) * S); x.rotate((l.rot || 0) * Math.PI / 180);
        if (l.type === 'img') x.drawImage(imgs[i], -l.w / 2 * S, -l.h / 2 * S, l.w * S, l.h * S);
        else if (l.type === 'text') { x.font = l.weight + ' ' + (l.fs * S) + 'px "' + l.font + '"'; x.textAlign = 'center'; x.textBaseline = 'middle'; x.fillStyle = l.color; x.fillText(l.text, 0, 0); }
        else { x.fillStyle = 'rgba(249,191,59,.3)'; x.fillRect(-l.w / 2 * S, -l.h / 2 * S, l.w * S, l.h * S); x.fillStyle = '#5a4310'; x.font = '700 ' + 9 * S + 'px Montserrat'; x.textAlign = 'center'; x.textBaseline = 'middle'; x.fillText(l.name, 0, 0); }
        x.restore();
      });
      x.restore();
      return c;
    });
  }
  function uploadCanvas(c, name) {
    return new Promise(function (res) { c.toBlob(function (b) { res(b); }, 'image/png'); }).then(function (blob) {
      return fetch('/api/upload', { method: 'POST', headers: { 'Content-Type': 'image/png', 'X-Filename': name }, body: blob })
        .then(function (r) { return r.json(); }).then(function (j) { return j.url || ''; }).catch(function () { return ''; });
    });
  }

  /* ------------------------------------------------------------ basket */
  function summary(it) {
    var p = prod(it.product), sz = Object.keys(it.sizes).map(function (k) { return k + ' ' + it.sizes[k]; }).join(', ');
    var deco = GM.views(it.product).filter(function (v) { return it.layers[v].length; }).map(function (v) { return GM.VIEW_NAMES[v]; });
    return [p.id === 'other' ? (it.product_other || 'Other product') : p.name, it.weight, it.fit, it.color.name + (it.pantone ? ' (' + it.pantone + ')' : '')].filter(Boolean).join(' · ') +
      '<br>' + qty(it) + ' pcs' + (sz ? ' (' + esc(sz) + ')' : '') + (deco.length ? '<br>Design on: ' + deco.join(', ') : '<br>No design added') + (it.deco ? ' · ' + esc(it.deco) : '');
  }
  $('addToQuote').onclick = function () {
    var err = [];
    if (!qty(cur)) err.push('Enter the quantity per size (tab "Sizes").');
    if (cur.product === 'other' && !cur.product_other.trim()) err.push('Describe the product (tab "Product").');
    var uploading = GM.views(cur.product).some(function (v) { return cur.layers[v].some(function (l) { return l.status === 'uploading'; }); });
    if (uploading) err.push('Your logo is still uploading, please wait a moment.');
    var eb = $('dErr'); if (err.length) { eb.innerHTML = err.map(esc).join('<br>'); eb.hidden = false; if (!qty(cur)) tab('sizes'); return; }
    eb.hidden = true;
    var btn = this, wasEditing = cur.editing != null; btn.disabled = true; btn.textContent = 'Saving design…'; selId = null; render();
    var vs = GM.views(cur.product).filter(function (v, i) { return i === 0 || cur.layers[v].length; });
    var snap = JSON.parse(JSON.stringify(cur));
    Promise.all(vs.map(function (v) {
      return mockup(v).then(function (c) {
        var data = c.toDataURL('image/jpeg', .7);
        return uploadCanvas(c, 'mockup-' + cur.product + '-' + v + '.png').then(function (u) { return { view: v, url: u, data: data }; });
      });
    })).then(function (ms) {
      snap.mockups = ms;
      if (wasEditing) basket[cur.editing] = snap; else basket.push(snap);
      snap.editing = null;
      renderBasket();
      toast(wasEditing ? 'Quote updated' : 'Added to your quote');
      var pid = cur.product; cur = fresh(pid); loadForm(); render(); tab('product');
      $('basket').scrollIntoView({ behavior: 'smooth' });
    }).catch(function (e) { console.error(e); toast('Could not save the design, please try again.'); })
      .then(function () { btn.disabled = false; btn.textContent = cur.editing != null ? 'Update quote' : 'Add to quote'; });
  };
  function renderBasket() {
    $('basketEmpty').hidden = basket.length > 0; $('qf').hidden = !basket.length; $('navCount').textContent = basket.length;
    $('basketList').innerHTML = basket.map(function (it, i) {
      return '<div class="bitem"><div class="thumbs">' + it.mockups.slice(0, 1).map(function (m) { return '<img src="' + m.data + '" alt="">'; }).join('') + '</div>' +
        '<div><h4>' + (i + 1) + '. ' + esc(it.product === 'other' ? (it.product_other || 'Other') : prod(it.product).name) + '</h4><p>' + summary(it) + '</p>' +
        (it.mockups.some(function (m) { return !m.url; }) ? '<p style="color:#b3261e">Design preview could not be saved online yet.</p>' : '') + '</div>' +
        '<div class="acts"><button type="button" class="btn ghost" data-edit="' + i + '">Edit</button><button type="button" class="mini danger" data-del="' + i + '">Remove</button></div></div>';
    }).join('');
    var tot = basket.reduce(function (a, it) { return a + qty(it); }, 0);
    $('sendInfo').textContent = basket.length + ' product' + (basket.length > 1 ? 's' : '') + ' · ' + tot + ' pcs';
  }
  $('basketList').onclick = function (e) {
    var d = e.target.closest('[data-del]'), ed = e.target.closest('[data-edit]');
    if (d) { basket.splice(+d.dataset.del, 1); renderBasket(); }
    if (ed) { var i = +ed.dataset.edit; cur = JSON.parse(JSON.stringify(basket[i])); delete cur.mockups; cur.editing = i; selId = null; loadForm(); render(); $('designer').scrollIntoView({ behavior: 'smooth' }); }
  };

  /* ------------------------------------------------------------ submit */
  $('qf').addEventListener('submit', function (e) {
    e.preventDefault();
    var v = function (id) { return $(id).value.trim(); }, err = [];
    if (!basket.length) err.push('Add at least one product to your quote.');
    if (!v('name')) err.push('Enter your name.');
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v('email'))) err.push('Enter a valid e-mail address.');
    if (!v('country')) err.push('Enter your country.');
    if (v('artwork_link') && !/^https:\/\//i.test(v('artwork_link'))) err.push('The artwork link must start with https://');
    if (!$('consent').checked) err.push('Please tick the box so we may contact you.');
    var eb = $('qErr'); if (err.length) { eb.innerHTML = err.map(esc).join('<br>'); eb.hidden = false; return; }
    eb.hidden = true;
    var logos = [];
    var items = basket.map(function (it) {
      var design = [];
      GM.views(it.product).forEach(function (vw) {
        it.layers[vw].forEach(function (l) {
          design.push({ view: GM.VIEW_NAMES[vw], type: l.type === 'text' ? 'text' : 'logo', text: l.text || '', font: l.font || '', color: l.color || '', logo_name: l.name || '', logo_url: l.url || '' });
          if (l.url && !logos.some(function (x) { return x.url === l.url; })) logos.push({ url: l.url, name: l.name });
        });
      });
      return { product: it.product, product_other: it.product_other, weight: it.weight, fit: it.fit, material: it.material, colors: [it.color.name], pantone: it.pantone,
        more_colors: it.moreColors, sizes: it.sizes, decoration: it.deco ? [it.deco] : [], positions: design.map(function (d) { return d.view; }).filter(function (x, i, a) { return a.indexOf(x) === i; }),
        print_colors: it.printColors, extras: it.extras, notes: it.notes, design: design, mockups: it.mockups.filter(function (m) { return m.url; }).map(function (m) { return { view: GM.VIEW_NAMES[m.view], url: m.url }; }) };
    });
    var payload = { items: items, logos: logos, artwork_link: v('artwork_link'), deadline: v('deadline'), delivery_country: v('delivery_country'), budget: v('budget'),
      name: v('name'), company: v('company'), email: v('email'), phone: v('phone'), country: v('country'), customer_type: v('customer_type'), website_url: v('website_url'), message: v('message'), website: $('website').value, consent: $('consent').checked };
    var b = $('send'); b.disabled = true; b.textContent = 'Sending…';
    fetch('/api/quote', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) })
      .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
      .then(function (x) {
        if (x.ok) { $('qf').hidden = true; $('basketList').hidden = true; $('done').hidden = false; $('ref').textContent = x.j.reference; basket = []; $('navCount').textContent = 0; $('basket').scrollIntoView({ behavior: 'smooth' }); }
        else { eb.textContent = x.j.error || 'Something went wrong.'; eb.hidden = false; }
      }).catch(function () { eb.textContent = 'No connection. Please try again.'; eb.hidden = false; })
      .then(function () { b.disabled = false; b.textContent = 'Send quote request'; });
  });
  markTc();
})();
