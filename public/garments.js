/* Garment drawings (flat sketches) for the live preview. viewBox 0 0 400 460.
   Each view: body = filled shapes in the garment colour, details = darker shapes / stitch lines, area = print area. */
(function () {
  var TEE = 'M150 40 C168 60 232 60 250 40 L312 62 L374 130 L332 172 L302 148 L302 418 Q302 426 294 426 L106 426 Q98 426 98 418 L98 148 L68 172 L26 130 L88 62 Z';
  var LONG = 'M150 40 C168 60 232 60 250 40 L312 62 C334 130 352 260 366 352 L326 362 L302 170 L302 418 Q302 426 294 426 L106 426 Q98 426 98 418 L98 170 L74 362 L34 352 C48 260 66 130 88 62 Z';
  var TANK = 'M150 40 C164 100 236 100 250 40 L282 40 C286 112 298 140 312 158 L312 418 Q312 426 304 426 L96 426 Q88 426 88 418 L88 158 C102 140 114 112 118 40 Z';
  var SLEEVE_S = 'M110 110 L290 110 L312 340 Q312 348 304 348 L96 348 Q88 348 88 340 Z';
  var SLEEVE_L = 'M140 30 L260 30 L284 420 Q284 428 276 428 L124 428 Q116 428 116 420 Z';
  var teeHems = '<path class="st" d="M34 122 L76 164 M366 122 L324 164 M98 410 L302 410"/>';
  var longHems = '<path class="st" d="M38 336 L76 345 M362 336 L324 345 M98 410 L302 410"/>';
  var ribHems = '<path class="st" d="M38 336 L76 345 M362 336 L324 345 M98 396 L302 396"/><path class="st" d="M36 344 L75 353 M364 344 L325 353"/>';
  var neckFront = '<path class="dk" d="M156 44 C172 66 228 66 244 44 C226 54 174 54 156 44 Z"/><path class="st" d="M150 40 C168 74 232 74 250 40"/>';
  var neckBack = '<path class="st" d="M150 40 C170 52 230 52 250 40"/>';
  var sleeveS = { body: SLEEVE_S, details: '<path class="st" d="M92 326 L308 326"/>', area: { x: 140, y: 150, w: 120, h: 150 } };
  var sleeveL = { body: SLEEVE_L, details: '<path class="st" d="M118 400 L282 400 M117 410 L283 410"/>', area: { x: 155, y: 80, w: 90, h: 290 } };

  var G = {
    tshirt: { front: { body: TEE, details: neckFront + teeHems, area: { x: 136, y: 92, w: 128, h: 210 } },
              back: { body: TEE, details: neckBack + teeHems, area: { x: 124, y: 70, w: 152, h: 260 } }, left: sleeveS, right: sleeveS },
    performance: { front: { body: TEE, details: neckFront + teeHems + '<path class="st" d="M150 40 L104 150 M250 40 L296 150"/>', area: { x: 136, y: 100, w: 128, h: 200 } },
              back: { body: TEE, details: neckBack + teeHems + '<path class="st" d="M150 40 L104 150 M250 40 L296 150"/>', area: { x: 124, y: 76, w: 152, h: 250 } }, left: sleeveS, right: sleeveS },
    longsleeve: { front: { body: LONG, details: neckFront + longHems, area: { x: 136, y: 92, w: 128, h: 210 } },
              back: { body: LONG, details: neckBack + longHems, area: { x: 124, y: 70, w: 152, h: 260 } }, left: sleeveL, right: sleeveL },
    tank: { front: { body: TANK, details: '<path class="st" d="M150 40 C164 108 236 108 250 40 M96 410 L304 410"/>', area: { x: 140, y: 120, w: 120, h: 190 } },
            back: { body: TANK, details: '<path class="st" d="M150 40 C168 74 232 74 250 40 M96 410 L304 410"/>', area: { x: 130, y: 90, w: 140, h: 230 } } },
    polo: { front: { body: TEE, details: teeHems + '<path class="dk" d="M150 40 L176 94 L200 62 Z M250 40 L224 94 L200 62 Z"/><path class="dk" d="M192 62 L208 62 L208 142 L192 142 Z"/><circle class="btn" cx="200" cy="82" r="3.5"/><circle class="btn" cx="200" cy="104" r="3.5"/><circle class="btn" cx="200" cy="126" r="3.5"/>', area: { x: 120, y: 150, w: 160, h: 190 } },
            back: { body: TEE, details: teeHems + '<path class="dk" d="M150 40 C170 28 230 28 250 40 C230 54 170 54 150 40 Z"/>', area: { x: 124, y: 76, w: 152, h: 250 } }, left: sleeveS, right: sleeveS },
    hoodie: { front: { body: LONG, details: ribHems + '<path class="gb" d="M150 40 C138 -8 262 -8 250 40 C236 70 164 70 150 40 Z"/><path class="dk" d="M168 42 C166 6 234 6 232 42 C220 60 180 60 168 42 Z"/><path class="st" d="M186 60 L182 122 M214 60 L218 122"/><path class="st" d="M132 296 L268 296 L290 384 L110 384 Z M132 296 L120 350 M268 296 L280 350"/>', area: { x: 132, y: 96, w: 136, h: 180 } },
              back: { body: LONG, details: ribHems + '<path class="gb" d="M150 40 C138 -14 262 -14 250 40 C232 58 168 58 150 40 Z"/><path class="st" d="M200 -4 L200 50"/>', area: { x: 124, y: 80, w: 152, h: 250 } }, left: sleeveL, right: sleeveL },
    sweater: { front: { body: LONG, details: ribHems + '<path class="dk" d="M156 44 C172 70 228 70 244 44 C226 54 174 54 156 44 Z"/><path class="st" d="M150 40 C168 78 232 78 250 40 M154 40 C170 70 230 70 246 40"/>', area: { x: 136, y: 96, w: 128, h: 210 } },
               back: { body: LONG, details: ribHems + neckBack, area: { x: 124, y: 70, w: 152, h: 260 } }, left: sleeveL, right: sleeveL },
    jacket: { front: { body: LONG, details: ribHems + '<path class="gb" d="M150 40 L150 20 C170 30 230 30 250 20 L250 40 C230 52 170 52 150 40 Z"/><path class="st" d="M200 46 L200 426"/><path class="st" d="M120 260 L160 260 M240 260 L280 260"/>', area: { x: 216, y: 92, w: 70, h: 90 } },
              back: { body: LONG, details: ribHems + '<path class="gb" d="M150 40 L150 20 C170 30 230 30 250 20 L250 40 C230 52 170 52 150 40 Z"/>', area: { x: 124, y: 70, w: 152, h: 260 } }, left: sleeveL, right: sleeveL },
    joggers: { front: { body: 'M110 30 L290 30 L300 70 L318 430 L236 436 L204 150 L196 150 L164 436 L82 430 L100 70 Z', details: '<path class="st" d="M110 54 L290 54 M90 410 L162 416 M238 416 L312 410"/><path class="st" d="M194 54 L190 96 M206 54 L210 96"/>', area: { x: 222, y: 90, w: 70, h: 130 } },
               back: { body: 'M110 30 L290 30 L300 70 L318 430 L236 436 L204 150 L196 150 L164 436 L82 430 L100 70 Z', details: '<path class="st" d="M110 54 L290 54 M90 410 L162 416 M238 416 L312 410"/>', area: { x: 120, y: 80, w: 160, h: 60 } } },
    shorts: { front: { body: 'M100 60 L300 60 L322 300 L216 312 L200 170 L184 312 L78 300 Z', details: '<path class="st" d="M100 84 L300 84 M194 84 L190 122 M206 84 L210 122"/>', area: { x: 226, y: 110, w: 70, h: 110 } },
              back: { body: 'M100 60 L300 60 L322 300 L216 312 L200 170 L184 312 L78 300 Z', details: '<path class="st" d="M100 84 L300 84"/>', area: { x: 130, y: 100, w: 140, h: 60 } } },
    cap: { front: { body: 'M90 262 C90 112 310 112 310 262 Z', details: '<path class="gb" d="M70 262 C120 300 280 300 330 262 C300 326 100 326 70 262 Z"/><circle class="dk" cx="200" cy="124" r="7"/><path class="st" d="M200 132 L200 260 M146 140 C128 180 124 220 126 262 M254 140 C272 180 276 220 274 262"/>', area: { x: 140, y: 168, w: 120, h: 80 } },
           back: { body: 'M90 262 C90 112 310 112 310 262 Z', details: '<circle class="dk" cx="200" cy="124" r="7"/><path class="dk" d="M164 262 C164 222 236 222 236 262 Z"/><path class="st" d="M200 132 L200 222"/>', area: { x: 150, y: 160, w: 100, h: 50 } } },
    bag: { front: { body: 'M90 160 L310 160 L318 430 L82 430 Z', details: '<path class="hd" d="M140 162 C140 54 180 54 180 162 M220 162 C220 54 260 54 260 162"/><path class="st" d="M92 176 L308 176"/>', area: { x: 116, y: 196, w: 168, h: 210 } },
           back: { body: 'M90 160 L310 160 L318 430 L82 430 Z', details: '<path class="hd" d="M140 162 C140 54 180 54 180 162 M220 162 C220 54 260 54 260 162"/><path class="st" d="M92 176 L308 176"/>', area: { x: 116, y: 196, w: 168, h: 210 } } },
  };
  G.other = G.tshirt;
  var VIEW_NAMES = { front: 'Front', back: 'Back', left: 'Left sleeve', right: 'Right sleeve' };

  function shade(hex, f) {
    var h = hex.replace('#', ''); if (h.length === 3) h = h.replace(/./g, '$&$&');
    var n = parseInt(h, 16), r = n >> 16, g = (n >> 8) & 255, b = n & 255;
    var t = f < 0 ? 0 : 255, p = Math.abs(f);
    return '#' + [r, g, b].map(function (c) { return ('0' + Math.round((t - c) * p + c).toString(16)).slice(-2); }).join('');
  }
  function luminance(hex) { var n = parseInt(hex.replace('#', ''), 16); return (0.299 * (n >> 16) + 0.587 * ((n >> 8) & 255) + 0.114 * (n & 255)) / 255; }

  // returns SVG markup (inner) for a garment view in the given colour
  function draw(id, view, color) {
    var g = (G[id] || G.tshirt)[view] || (G[id] || G.tshirt).front;
    var light = luminance(color) > 0.6;
    var line = light ? shade(color, -0.35) : shade(color, 0.28);
    var dark = shade(color, light ? -0.12 : -0.35);
    return '<g class="garment" style="--g:' + color + ';--l:' + line + ';--d:' + dark + '">' +
      '<style>.garment path,.garment circle{stroke:var(--l);stroke-width:2;stroke-linejoin:round}.garment .st{fill:none;stroke-dasharray:4 3;stroke-width:1.4}.garment .dk{fill:var(--d)}.garment .gb{fill:var(--g)}.garment .btn{fill:var(--d)}.garment .hd{fill:none;stroke:var(--d);stroke-width:12;stroke-linecap:round}</style>' +
      '<path d="' + g.body + '" fill="var(--g)"/>' + g.details + '</g>';
  }
  window.GARMENTS = { G: G, VIEW_NAMES: VIEW_NAMES, draw: draw, views: function (id) { return Object.keys(G[id] || G.tshirt); }, area: function (id, view) { var g = (G[id] || G.tshirt); return (g[view] || g.front).area; }, luminance: luminance, shade: shade };
})();
