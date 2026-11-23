#!/usr/bin/env python3
"""Builds the static pages in public/ from one shared layout (header, footer) and per-page content.
Run: python3 scripts/build.py"""
import html, os, re

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
PUB = os.path.join(ROOT, 'public')
SRC = os.path.join(ROOT, 'src')

U = 'https://images.unsplash.com/photo-'
def img(pid, w=2000):
    return f'{U}{pid}?auto=format&fit=crop&q=80&w={w}'

P = {  # photos (Unsplash licence, free to use)
    'tees': '1523381294911-8d3cead13475', 'sewing': '1673201229733-69d19c5c4a87', 'hangers': '1792333531346-11a224cbcc40',
    'factory': '1741176505800-caaa3a52631a', 'fabric': '1741176506261-73218298e4d8', 'thread': '1783070610671-1cc98dffba42',
    'workshop': '1768746350424-ee28a364dcf5', 'team': '1577894947058-cfdae4276bef', 'black': '1610502778270-c5c6f4c7d575',
    'model': '1571455786673-9d9d6c194f90', 'white': '1651761179569-4ba2aa054997',
}

FONTS = ('<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>'
         '<link href="https://fonts.googleapis.com/css2?family=Assistant:wght@300;400;600;700&family=Montserrat:wght@400;500;600;700;800'
         '&family=Open+Sans:wght@400;600;700&family=Oswald:wght@500;700&family=Bebas+Neue&family=Permanent+Marker&family=Playfair+Display:wght@700'
         '&family=Pacifico&family=Anton&family=Roboto+Slab:wght@700&display=swap" rel="stylesheet">')

NAV = [('/services', 'Services'), ('/about', 'About'), ('/process', 'Process'), ('/contact', 'Contact')]

def header(active, solid, quote_pill):
    links = ''.join(f'<a href="{h}"{" class=on" if h == active else ""}>{t}</a>' for h, t in NAV) + '<a href="/design" class="mob-only mbtn mfill">Design now</a><a href="/account" class="mob-only mbtn mline">My account</a>'
    acct = f'<a href="/account" class="acct{" on" if active == "/account" else ""}" aria-label="My account"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="12" cy="8" r="4"/><path d="M4 21c1.5-4 4.5-6 8-6s6.5 2 8 6"/></svg><span>My account</span></a>'
    cta = ('<a class="cta qcta" href="#basket">Quote <span id="navCount" class="pill">0</span></a>' if quote_pill
           else '<a class="cta" href="/design">Start designing</a>')
    return f'''<header class="hdr{' solid fixed' if solid else ''}" id="hdr"><div class="wrap">
  <a class="logo" href="/" aria-label="Eleven Fashion home">
    <img class="lw" src="https://www.elevenfashion.co.th/images/logo-white-retina.png" alt="Eleven Fashion" onerror="this.outerHTML='<b class=&quot;wm lw&quot;>ELEVEN<i>|</i>FASHION</b>'">
    <img class="ld" src="https://www.elevenfashion.co.th/images/scroll-logo-retina.png" alt="" onerror="this.outerHTML='<b class=&quot;wm ld&quot;>ELEVEN<i>|</i>FASHION</b>'">
  </a>
  <nav>{links}</nav>
  <div class="hacts">{acct}{cta}<button class="burger" type="button" aria-label="Menu" onclick="document.body.classList.toggle('menu')"><span></span><span></span></button></div>
</div></header>'''

FOOTER = '''<section class="callout"><div class="wrap">
  <h3>Let's get to know each other...</h3>
  <div class="cbtns"><a class="btn ghost" href="/about">We are Eleven Fashion</a><a class="btn yellow" href="/contact">Get in touch</a></div>
</div></section>
<footer class="ftr"><div class="wrap">
  <div><h4>About us</h4><p>Eleven Fashion is a one stop shop for fashion brands, teams and companies: design, fabric sourcing, manufacturing, merchandise and worldwide distribution from Bangkok.</p></div>
  <div><h4>Quick links</h4><p><a href="/design">Design your apparel</a><br><a href="/services">Services</a><br><a href="/process">How it works</a><br><a href="/account">My account</a></p></div>
  <div><h4>Contact us</h4><p>71 Soi Suphaphong 1, Nongbon, Prawet<br>Bangkok 10250, Thailand<br>+66 (0)95 959 4633<br><a href="mailto:info@elevenfashion.co.th">info@elevenfashion.co.th</a></p></div>
</div><div class="copy">© Eleven Fashion Co., Ltd. · Photos: Unsplash</div></footer>'''

SCROLL = '<script>(function(){var h=document.getElementById("hdr");if(h.classList.contains("fixed"))return;function f(){h.classList.toggle("solid",scrollY>30)}addEventListener("scroll",f,{passive:true});f()})();</script>'

def page(path, title, desc, body, active='', solid=False, quote_pill=False, scripts='', extra_head='', footer=True, body_class=''):
    doc = f'''<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>{html.escape(title)}</title>
<meta name="description" content="{html.escape(desc)}">
<link rel="icon" href="https://www.elevenfashion.co.th/favicon.ico">
{FONTS}
<link rel="stylesheet" href="/app.css?v=9">
<link rel="stylesheet" href="/site.css?v=9">
{extra_head}
</head>
<body class="{body_class}">
{header(active, solid, quote_pill)}
{body}
{FOOTER if footer else ''}
<div class="toast" id="toast" hidden></div>
{scripts}
{SCROLL}
</body>
</html>
'''
    with open(os.path.join(PUB, path), 'w') as f:
        f.write(doc)

def phero(photo, label, title, text, short=True):
    return f'''<section class="hero{' short' if short else ''}" style="--img:url('{img(P[photo], 2400)}')"><div class="wrap">
  <div class="lbl light">{label}</div>
  <h1>{title}</h1>
  <p>{text}</p>
</div></section>'''

STAGES = [
    ('Design', 'You design your product online or send us your idea, artwork and quantities. We check everything and send you a quote.'),
    ('Mockup approval', 'We prepare a digital mockup with exact placement, colours and sizes. You approve it in your account or ask for changes.'),
    ('In progress', 'After approval and payment we source the fabric, cut, sew, print or embroider and check the quality.'),
    ('In transit', 'Your order is packed and shipped worldwide. You find the carrier and tracking number in your account.'),
    ('Delivered', 'Your products arrive. Reorders are simple: the design and specs stay in your account.'),
]
def stages_html():
    return '<ol class="stages">' + ''.join(f'<li><span>{i + 1:02d}</span><b>{t}</b><p>{d}</p></li>' for i, (t, d) in enumerate(STAGES)) + '</ol>'

PAY = '''<div class="paygrid">
  <div><b>Cards &amp; wallets</b><span>Visa, Mastercard, American Express, JCB, UnionPay, Apple Pay and Google Pay, from every country.</span></div>
  <div><b>Thailand &amp; Asia</b><span>PromptPay (Thai QR), TrueMoney, LINE Pay, Alipay and WeChat Pay.</span></div>
  <div><b>Europe</b><span>iDEAL for the Netherlands, Bancontact for Belgium and SEPA bank transfer.</span></div>
  <div><b>Bank transfer</b><span>Pay by international bank transfer with the details on your invoice.</span></div>
</div>'''


GSM_GUIDE = [
    ('T-shirt / long sleeve', '90–150 gsm', '150–180 gsm', '180–240 gsm', '240+ gsm'),
    ('Polo shirt', '160–200 gsm', '200–230 gsm', '230+ gsm', ''),
    ('Hoodie / sweater / joggers', '220–280 gsm', '280–350 gsm', '350–430 gsm', '430+ gsm'),
    ('Sports / dry-fit', '100–140 gsm', '140–180 gsm', '180+ gsm', ''),
    ('Tote bag (canvas)', '140–180 gsm', '180–280 gsm', '280+ gsm', ''),
]
def gsm_html():
    rows = ''.join(f'<tr><td>{a}</td><td>{b}</td><td>{c}</td><td>{d}</td><td>{e or "–"}</td></tr>' for a, b, c, d, e in GSM_GUIDE)
    return f'''<div class="tablewrap"><table class="itable gsmt"><thead><tr><th>Product</th><th>Lightweight</th><th>Midweight</th><th>Heavyweight</th><th>Super heavy</th></tr></thead><tbody>{rows}</tbody></table></div>
<div class="values" style="margin-top:28px">
  <div><b>What is gsm?</b><p>Grams per square metre of fabric. The higher the number, the thicker and heavier the fabric.</p></div>
  <div><b>Lightweight</b><p>Cool and breathable. Good for hot climates, events, promo and sportswear. Lower price and shipping cost.</p></div>
  <div><b>Midweight</b><p>The all-round standard: soft, not see-through and great for printing. Example: a 300 gsm hoodie.</p></div>
  <div><b>Heavyweight</b><p>Thick, durable and structured with a premium, boxy look. Popular for merch and streetwear.</p></div>
</div>
<p class="intro-p" style="margin-top:24px">The fabric type matters too: French terry is looped on the inside and breathable, brushed fleece is soft and warmer, loopback is a heavy terry for a premium feel. In the designer you choose the exact weight and fabric type, or ask us to advise.</p>'''

SERVICES = [
    ('Fashion design', 'sewing', 'From a rough idea to a finished tech pack. We help with design, fit, size charts and samples so your product is ready for production.'),
    ('Fabric sourcing', 'fabric', 'Cotton, organic cotton, blends, fleece and performance fabrics in the weight and colour you need, including custom Pantone dyeing.'),
    ('Manufacturing', 'factory', 'Cutting, sewing and finishing in Bangkok with quality checks at every step, for small runs and larger series.'),
    ('Printing & embroidery', 'thread', 'Screen print, embroidery, DTF, heat transfer, sublimation and woven labels or patches.'),
    ('Teamwear', 'model', 'Kits, training wear and fan merchandise for gyms, clubs, teams and events, with names and numbers if needed.'),
    ('Merchandise', 'white', 'T-shirts, hoodies, caps and bags with your logo for companies, artists and brands.'),
    ('Private label', 'black', 'Your own clothing brand: custom neck labels, hang tags, polybags and packaging with your branding.'),
    ('Distribution', 'hangers', 'Packing per size or per person and shipping to any country in the world, with tracking.'),
]

PRODUCT_PHOTOS = {  # Unsplash
    'tshirt': '1574180566232-aaad1b5b8450', 'longsleeve': '1671438118257-cefa6c86fd50', 'tank': '1686972213857-18ae00d74921',
    'polo': '1625910513399-c9fcba54338c', 'hoodie': '1556821840-3a63f95609a7', 'sweater': '1554568218-ffd1e72a2151',
    'joggers': '1602573991155-21f0143bb45c', 'shorts': '1640943136566-3edeb13e3d3b', 'performance': '1596723524688-176682618fd2',
    'jacket': '1643622782660-30dedcd8d75a', 'cap': '1588850561407-ed78c282e89b', 'bag': '1578237493287-8d4d2b03591a',
}
def collection():
    import json
    photos = json.dumps({k: f'{U}{v}?auto=format&fit=crop&w=640&h=800&q=75' for k, v in PRODUCT_PHOTOS.items()})
    return ('<div class="coll-wrap"><button class="cnav prev" type="button" aria-label="Previous" onclick="document.getElementById(\'coll\').scrollBy({left:-600,behavior:\'smooth\'})">‹</button>'
            '<div class="coll" id="coll"></div>'
            '<button class="cnav next" type="button" aria-label="Next" onclick="document.getElementById(\'coll\').scrollBy({left:600,behavior:\'smooth\'})">›</button></div>'
            '<script>(function(){var PH=' + photos + ';fetch("/api/options").then(function(r){return r.json()}).then(function(o){document.getElementById("coll").innerHTML=o.PRODUCTS.filter(function(p){return p.id!=="other"}).map(function(p){'
            'return \'<a class="citem" href="/design?product=\'+p.id+\'"><div class="cimg">\'+(PH[p.id]?\'<img src="\'+PH[p.id]+\'" alt="\'+p.name+\'" loading="lazy">\':GARMENTS.draw(p.id,"front","#ffffff"))+\'<span class="cgo">Design yours</span></div><b>\'+p.name+\'</b><span>\'+(p.fabric?p.fabric.min+"–"+p.fabric.max+" gsm":"Custom")+\'</span></a>\'}).join("")})})();</script>')

CLIENTS = [  # typeset names, not the clients' own logo artwork (swap in official logo files with permission)
    ('The Health House', 'c1'), ('Team Champ', 'c2'), ('Hard Rock Cafe', 'c3'), ('Male Lions', 'c4'),
    ('Jaspal', 'c5'), ('Gentlewoman', 'c6'), ("Mike's Gym", 'c7'), ('Fairtex', 'c8'),
]
def clients():
    one = ''.join(f'<li class="{c}">{html.escape(n)}</li>' for n, c in CLIENTS)
    return f'''<section class="clients" aria-label="Some of our clients"><div class="wrap"><div class="lbl">Trusted by</div></div>
  <div class="marquee"><ul>{one}</ul><ul aria-hidden="true">{one}</ul></div>
</section>'''

# ---------------- pages ----------------
def build():
    designer = open(os.path.join(SRC, 'designer.html')).read()

    # Home
    page('index.html', 'Eleven Fashion | Clothing manufacturer in Bangkok – design your apparel online',
         'Eleven Fashion is a clothing manufacturer and wholesaler in Bangkok. Design T-shirts, hoodies, teamwear and merchandise online, request a quote and follow your order.',
         f'''<section class="hero" style="--img:url('{img(P['tees'], 2600)}')"><div class="wrap">
  <h1>We are<br>Eleven Fashion</h1>
  <p>Welcome to our world. Design your apparel online and we make it in Bangkok, for customers all over the world.</p>
  <div class="hbtns"><a class="btn white" href="/design">Start designing</a><a class="btn outline" href="#about">Find out more</a></div>
</div></section>

<section class="about" id="about"><div class="wrap">
  <div class="lbl">About us</div>
  <div class="about-g">
    <h2 class="big">Designing<br>your brand</h2>
    <div>
      <p class="lead">Eleven Fashion is a clothing manufacturer and wholesaler in Bangkok. We help brands, teams and companies from the first sketch to the finished garment.</p>
      <p>Fashion design, fabric sourcing, manufacturing, printing and embroidery, packaging and distribution are handled under one roof. Design your product online, place your logo and request a quote. After that you follow every step in your own account.</p>
      <a class="more" href="/about">More about Eleven Fashion</a>
    </div>
  </div>
  <img class="wide" src="{img(P['fabric'], 2400)}" alt="Fabric being prepared for production" loading="lazy">
</div></section>

{clients()}

<section class="tiles"><div class="wrap">
  <a class="tile" href="/services" style="--img:url('{img(P['sewing'], 1600)}')"><span class="tl">From sketch<br>to shipment.</span><span class="tb">Our services</span></a>
  <a class="tile" href="/design" style="--img:url('{img(P['hangers'], 1600)}')"><span class="tl">Your design.<br>Our craft.</span><span class="tb">Start designing</span></a>
</div></section>

<section class="sec coll-sec"><div class="wrap">
  <div class="lbl">Design online</div>
  <div class="coll-head"><h2 class="big">Product collection</h2><a class="more" href="/design">Open the designer</a></div>
  {collection()}
</div></section>

<section class="services" style="--img:url('{img(P['factory'], 2400)}')"><div class="wrap">
  <h2 class="big light">Our services</h2>
  <div class="svc">
    {''.join(f'<div><b>{t}</b><span>{d.split(".")[0]}.</span></div>' for t, _, d in SERVICES[:6])}
  </div>
  <a class="btn outline" style="margin-top:36px" href="/services">All services</a>
</div></section>

<section class="sec"><div class="wrap">
  <div class="lbl">The process</div>
  <h2 class="big">Follow every step</h2>
  <p class="intro-p">Log in to your account to approve mockups, follow production and shipping, and view and pay your invoices.</p>
  {stages_html()}
</div></section>

<section class="sec paysec"><div class="wrap">
  <div class="lbl">Worldwide</div>
  <h2 class="big">Pay the way you like</h2>
  <p class="intro-p">We deliver all over the world, so you can pay with the method that is normal in your country.</p>
  {PAY}
</div></section>''', active='/', scripts='<script src="/garments.js"></script>')

    # Design
    page('design.html', 'Design your apparel | Eleven Fashion',
         'Choose a product, fabric, colour and sizes, add your logo or text and request a quote from Eleven Fashion in Bangkok.',
         '<div class="pagetop"><div class="wrap"><div class="lbl">Designer</div><h1 class="big">Design your apparel</h1><p class="intro-p">Pick a product, choose fabric, colour and sizes, put your logo or text on it and request a quote. No payment needed.</p></div></div>\n' + designer,
         active='/design', solid=True, quote_pill=True, scripts='<script src="/garments.js"></script>\n<script src="/app.js?v=9"></script>')

    # Services
    cards = ''.join(f'''<article class="scard"><div class="simg" style="--img:url('{img(P[ph], 1200)}')"></div><div class="stxt"><h3>{t}</h3><p>{d}</p></div></article>''' for t, ph, d in SERVICES)
    page('services.html', 'Services | Eleven Fashion', 'Fashion design, fabric sourcing, manufacturing, printing and embroidery, teamwear, merchandise, private label and worldwide distribution.',
         phero('factory', 'What we do', 'One stop shop', 'Everything you need to make your own clothing, under one roof in Bangkok.') + f'''
<section class="sec"><div class="wrap">
  <div class="lbl">Services</div>
  <h2 class="big">From idea to delivery</h2>
  <p class="intro-p">Work with us on one step or on the whole route. Tell us what you need and we put together a proposal that fits your quantities, budget and timing.</p>
  <div class="scards">{cards}</div>
</div></section>
<section class="sec band"><div class="wrap band-g">
  <div><div class="lbl">Who we work for</div><h2 class="big">Brands, teams &amp; companies</h2></div>
  <ul class="ticks"><li>Clothing brands and start-ups</li><li>Gyms, sports clubs and teams</li><li>Companies: merchandise and workwear</li><li>Events and festivals</li><li>Schools and associations</li><li>Shops and resellers</li></ul>
</div></section>''', active='/services')

    # About
    page('about.html', 'About Eleven Fashion | Clothing manufacturer in Bangkok', 'Eleven Fashion makes clothing for brands, teams and companies all over the world from Bangkok, Thailand.',
         phero('team', 'About us', 'We are Eleven Fashion', 'A clothing manufacturer and wholesaler in Bangkok, working for customers all over the world.') + f'''
<section class="about"><div class="wrap">
  <div class="about-g">
    <h2 class="big">Made in<br>Bangkok</h2>
    <div>
      <p class="lead">Eleven Fashion helps brands, teams and companies make their own clothing: from the first idea to the finished, packed product.</p>
      <p>We combine design, fabric sourcing, manufacturing, printing, embroidery and distribution. That means one contact person, one plan and one quality standard for your whole order.</p>
      <p>This online portal makes working with us simple. Design your product, request a quote and follow your order step by step: mockup, production, shipping and delivery. Your invoices and payments are in the same place.</p>
    </div>
  </div>
  <img class="wide" src="{img(P['workshop'], 2400)}" alt="Garment workshop" loading="lazy">
</div></section>
<section class="sec band"><div class="wrap">
  <div class="lbl">How we work</div>
  <h2 class="big">What you can expect</h2>
  <div class="values">
    <div><b>One stop shop</b><p>Design, sourcing, production and shipping with one team.</p></div>
    <div><b>Transparent</b><p>You see the status of your order, your mockups and invoices in your account.</p></div>
    <div><b>Flexible quantities</b><p>From a first collection to repeat orders, we look at what fits your plans.</p></div>
    <div><b>Worldwide</b><p>We ship to every country and accept international payment methods.</p></div>
  </div>
</div></section>''', active='/about')

    # Process
    how = open(os.path.join(SRC, 'how_old.html')).read()
    faq = how[how.index('<div class="faq">'):how.rindex('</div></section>')]
    faq = faq.replace('</div>\n', '''<details><summary>Can I follow my order?</summary><p>Yes. Log in to your account with your e-mail address. You see every step: design, mockup approval, production, shipping with tracking and delivery.</p></details>
    <details><summary>Which payment methods do you accept?</summary><p>Credit and debit cards, Apple Pay and Google Pay worldwide, PromptPay, TrueMoney, LINE Pay, Alipay and WeChat Pay in Asia, iDEAL, Bancontact and SEPA in Europe, and international bank transfer.</p></details>
    <details><summary>Do you ship to my country?</summary><p>We ship worldwide. Shipping costs and delivery time depend on the destination and the size of your order and are included in your quote.</p></details>
    <details><summary>When do I pay?</summary><p>The payment terms are in your quote and on your invoice. You pay online in your account or by bank transfer.</p></details>
  </div>\n''', 1)
    page('process.html', 'How it works | Eleven Fashion', 'Design, mockup approval, production, shipping and delivery: how ordering custom apparel from Eleven Fashion works.',
         phero('thread', 'The process', 'How it works', 'Five clear steps from your design to delivery. You follow all of them in your account.') + f'''
<section class="sec"><div class="wrap">
  <div class="lbl">Step by step</div>
  <h2 class="big">From design to delivery</h2>
  {stages_html()}
  <div class="hbtns" style="margin-top:34px"><a class="btn yellow" href="/design">Start designing</a><a class="btn ghost" href="/account">Log in to your account</a></div>
</div></section>
<section class="sec"><div class="wrap">
  <div class="lbl">Fabric guide</div>
  <h2 class="big">Fabric weight (gsm)</h2>
  <p class="intro-p">Choose how thick your fabric should be. These are common ranges per product.</p>
  {gsm_html()}
</div></section>
<section class="sec paysec"><div class="wrap">
  <div class="lbl">Payment</div>
  <h2 class="big">Pay the way you like</h2>
  {PAY}
</div></section>
<section class="sec"><div class="wrap">
  <div class="lbl">FAQ</div>
  <h2 class="big">Questions</h2>
  {faq}
</div></section>''', active='/process')

    # Contact
    page('contact.html', 'Contact | Eleven Fashion', 'Contact Eleven Fashion in Bangkok: address, phone, e-mail and opening hours.',
         phero('workshop', 'Get in touch', 'Contact', 'Questions about a product, a quote or your order? We are happy to help.') + '''
<section class="sec"><div class="wrap contact-g">
  <div>
    <div class="lbl">Eleven Fashion Co., Ltd.</div>
    <h2 class="big">Visit or call us</h2>
    <dl class="cinfo">
      <dt>Address</dt><dd>71 Soi Suphaphong 1, Nongbon, Prawet<br>Bangkok 10250, Thailand</dd>
      <dt>Phone</dt><dd><a href="tel:+66959594633">+66 (0)95 959 4633</a></dd>
      <dt>E-mail</dt><dd><a href="mailto:info@elevenfashion.co.th">info@elevenfashion.co.th</a></dd>
      <dt>Opening hours</dt><dd>10AM – 6PM (Bangkok time)</dd>
    </dl>
    <div class="hbtns"><a class="btn yellow" href="/design">Request a quote</a><a class="btn ghost" href="mailto:info@elevenfashion.co.th">Send an e-mail</a></div>
  </div>
  <div class="map"><iframe title="Map" loading="lazy" referrerpolicy="no-referrer-when-downgrade" src="https://maps.google.com/maps?q=71%20Soi%20Suphaphong%201%2C%20Nongbon%2C%20Prawet%2C%20Bangkok%2010250&z=15&output=embed"></iframe></div>
</div></section>''', active='/contact')

    # Account
    page('account.html', 'My account | Eleven Fashion', 'Log in to follow your orders, approve mockups and view and pay your invoices.',
         open(os.path.join(SRC, 'account.html')).read(), active='/account', solid=True,
         scripts='<script src="/account.js?v=9"></script>', extra_head='<meta name="robots" content="noindex">', body_class='acctpage')

    # Invoice (printable)
    page('invoice.html', 'Invoice | Eleven Fashion', 'Invoice', open(os.path.join(SRC, 'invoice.html')).read(), solid=True, footer=False,
         scripts='<script src="/invoice.js?v=3"></script>', extra_head='<meta name="robots" content="noindex">', body_class='invpage')

if __name__ == '__main__':
    build()
    print('built')
