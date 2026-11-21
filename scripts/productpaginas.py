#!/usr/bin/env python3
"""Productpagina's voor thheurope.com (Nederlands, vindbaar in Google).

Wordt aangeroepen vanuit scripts/merkpaginas.py, zodat merk- en productpagina's altijd
uit dezelfde catalogus komen en in dezelfde sitemap staan. Per product:
  producten/<slug>/index.html

Inhoud komt uitsluitend uit de catalogus (naam, merk, categorie, verpakking, smaken, foto's
en de productomschrijving uit WooCommerce) en uit de vaste voorwaarden van de site.
Er staan geen prijzen op deze pagina's: die ziet een klant pas na inloggen.
"""
import html, json, re

E = html.escape
SITE = "https://thheurope.com"
DIR = "producten"

CAT_NL = {
    "Vitamins": "vitamines en supplementen", "Protein": "eiwitpoeders", "Amino Acids": "aminozuren",
    "Pre-Workout": "pre-workouts", "Weight Loss": "afslankproducten", "Accessories": "accessoires",
    "Health": "gezondheidsproducten", "Creatine": "creatine", "Bars & Snacks": "eiwitrepen en snacks",
    "Test Boosters": "testosteron boosters", "Energy & Hydration": "energie- en sportdranken", "Other": "overige producten",
}

EXTRA_CSS = """
.pd{display:grid;grid-template-columns:minmax(0,420px) 1fr;gap:34px;margin:18px 0 8px;align-items:start}
.pd .ph{border:1px solid var(--line);border-radius:10px;background:#fff;padding:18px;display:flex;align-items:center;justify-content:center;aspect-ratio:1/1}
.pd .ph img{max-width:100%;max-height:100%;object-fit:contain}
.th{display:flex;gap:8px;flex-wrap:wrap;margin-top:10px}.th img{width:64px;height:64px;object-fit:contain;border:1px solid var(--line);border-radius:6px;background:#fff;padding:4px}
.facts{border-collapse:collapse;margin:14px 0;font-size:14px;width:100%;max-width:520px}.facts th,.facts td{text-align:left;padding:7px 10px;border-bottom:1px solid var(--line);vertical-align:top}.facts th{width:150px;color:var(--ink)}
.brandl{font-size:13px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;text-decoration:none}
.desc p{margin:0 0 10px}
.pd h1{font-size:clamp(24px,3vw,34px);line-height:1.1}
@media(max-width:760px){.pd{grid-template-columns:1fr}}
"""


def strip_html(s):
    s = re.sub(r"<br\s*/?>|</p>|</li>|</h\d>", "\n", s or "", flags=re.I)
    s = re.sub(r"<[^>]+>", "", s)
    s = html.unescape(s).replace("\xa0", " ")
    lines = [re.sub(r"\s+", " ", l).strip() for l in s.split("\n")]
    return [l for l in lines if l]


def useful_desc(p):
    """Alleen een echte omschrijving overnemen; de korte standaardzinnen voegen niets toe."""
    lines = strip_html(p.get("desc"))
    txt = " ".join(lines)
    if len(txt) < 300:
        return []
    # geen prijzen, kortingscodes of verwijzingen naar de consumentenwinkel overnemen
    bad = re.compile(r"€|\beuro\b|kortingscode|thehealthhouse\.nl|in onze winkel|bezorgd|verzendkosten", re.I)
    return [l for l in lines if not bad.search(l)][:12]


def full_name(p, brand_clean):
    n = p.get("name") or p["slug"]
    return n if n.lower().startswith(brand_clean.lower()) else f"{brand_clean} {n}"


def build(brands, img_url, css, write):
    """brands: lijst uit merkpaginas.brand_data (met 'prods', 'clean', 'slug'). Geeft de url's terug."""
    urls, seen = [], set()
    for b in brands:
        nm = b["clean"]
        for p in b["prods"]:
            slug = p["slug"]
            if slug in seen:
                continue
            seen.add(slug)
            urls.append(page(p, b, nm, img_url, css, write))
    return urls


def page(p, b, nm, img_url, css, write):
    slug = p["slug"]
    url = f"{SITE}/{DIR}/{slug}/"
    title_name = full_name(p, nm)
    unit = (p.get("unit") or "").strip()
    cat = CAT_NL.get(p.get("category") or "Other", "overige producten")
    flavors = [f for f in (p.get("flavors") or []) if f]
    desc = useful_desc(p)
    imgs = [img_url(p)] + [img_url({"img": g}) for g in (p.get("gallery") or []) if g][:5]
    imgs = list(dict.fromkeys(i for i in imgs if i and not i.endswith("/")))
    title = f"{title_name} groothandel | THH Europe"
    if len(title) > 70:
        title = f"{title_name} | THH Europe"
    meta = (f"{title_name} zakelijk inkopen bij THH Europe. Groothandelsprijzen vanaf 1 stuk, staffelkorting "
            f"en gratis levering vanaf € 1.000. Voor winkels, sportscholen en webshops.")
    intro = (f"{title_name} is een product van {nm} in de categorie {cat}"
             + (f", verpakt per {unit}" if unit else "") + ". "
             + (f"Verkrijgbaar in {len(flavors)} smaken of varianten. " if len(flavors) > 1 else "")
             + f"Bij THH Europe koop je {nm} zakelijk in voor wederverkoop, tegen groothandelsprijzen en al vanaf 1 stuk.")
    rows = [("Merk", f'<a href="/merken/{b["slug"]}/">{E(nm)}</a>'), ("Categorie", E(cat[:1].upper() + cat[1:]))]
    if unit:
        rows.append(("Verpakking", E(unit)))
    if flavors:
        rows.append(("Smaken / varianten", E(", ".join(flavors[:30]) + (" …" if len(flavors) > 30 else ""))))
    rows.append(("Levering", "Vanuit eigen voorraad, gratis vanaf € 1.000 excl. btw"))
    facts = "".join(f"<tr><th>{k}</th><td>{v}</td></tr>" for k, v in rows)
    rel = lambda u: u[len(SITE):] if u.startswith(SITE + "/") else u
    others = [q for q in b["prods"] if q["slug"] != slug and q.get("img")][:6]
    cards = "".join(
        f'<a class="card" href="/{DIR}/{E(q["slug"])}/"><img src="{E(rel(img_url(q)))}" alt="{E(full_name(q, nm))}" loading="lazy">'
        f'<span>{E(q.get("name") or q["slug"])}</span><small>{E(q.get("unit") or "")}</small></a>' for q in others)
    product_ld = {"@context": "https://schema.org", "@type": "Product", "name": title_name, "url": url,
                  "brand": {"@type": "Brand", "name": nm}, "category": cat, "image": imgs[:3]}
    if desc:
        product_ld["description"] = " ".join(desc)[:500]
    bc_ld = {"@context": "https://schema.org", "@type": "BreadcrumbList", "itemListElement": [
        {"@type": "ListItem", "position": 1, "name": "THH Europe", "item": SITE + "/"},
        {"@type": "ListItem", "position": 2, "name": "Merken", "item": SITE + "/merken/"},
        {"@type": "ListItem", "position": 3, "name": nm, "item": f"{SITE}/merken/{b['slug']}/"},
        {"@type": "ListItem", "position": 4, "name": title_name, "item": url}]}
    rel = lambda u: u[len(SITE):] if u.startswith(SITE + "/") else u
    thumbs = "".join(f'<img src="{E(rel(i))}" alt="{E(title_name)}" loading="lazy">' for i in imgs[1:5])
    desc_html = ("<h2>Productinformatie</h2><div class=\"desc\">" + "".join(f"<p>{E(l)}</p>" for l in desc) + "</div>") if desc else ""
    doc = f"""<!doctype html>
<html lang="nl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>{E(title)}</title>
<meta name="description" content="{E(meta)}">
<link rel="canonical" href="{url}">
<meta property="og:title" content="{E(title)}"><meta property="og:description" content="{E(meta)}"><meta property="og:url" content="{url}"><meta property="og:type" content="product">
{('<meta property="og:image" content="' + E(imgs[0]) + '">') if imgs else ''}
<link rel="icon" href="/favicon.ico">
<style>{css}{EXTRA_CSS}</style>
<script type="application/ld+json">{json.dumps(product_ld, ensure_ascii=False)}</script>
<script type="application/ld+json">{json.dumps(bc_ld, ensure_ascii=False)}</script>
</head><body>
<header class="top"><div class="wrap"><a class="logo" href="/"><b>THHEU</b><span>THH EUROPE</span></a>
<nav><a href="/#catalogus">Catalogus</a><a href="/merken/">Merken</a><a href="/#word-klant">Word klant</a><a href="/#inloggen">Inloggen</a></nav></div></header>
<main class="wrap">
<div class="crumb"><a href="/">THH Europe</a> / <a href="/merken/">Merken</a> / <a href="/merken/{b['slug']}/">{E(nm)}</a> / {E(p.get('name') or slug)}</div>
<div class="pd"><div><div class="ph">{('<img src="' + E(rel(imgs[0])) + '" alt="' + E(title_name) + '">') if imgs else ''}</div>{('<div class="th">' + thumbs + '</div>') if thumbs else ''}</div>
<div><a class="brandl" href="/merken/{b['slug']}/">{E(nm)}</a><h1>{E(title_name)}</h1><p>{E(intro)}</p>
<table class="facts">{facts}</table>
<div class="btns"><a class="btn" href="/#product?id={E(slug)}">Prijs en bestellen</a><a class="btn sec" href="/#word-klant">Word zakelijk klant</a></div>
<p style="font-size:13px">Je exacte groothandelsprijs zie je na inloggen. Nog geen account? Meld je aan als zakelijke klant of zet het product op je offertelijst.</p></div></div>
{desc_html}
<h2>{E(nm)} zakelijk inkopen</h2>
<ul><li>groothandelsprijzen al vanaf 1 stuk</li><li>extra staffelkorting bij grotere aantallen</li><li>gratis levering vanaf € 1.000 excl. btw</li><li>levering vanuit eigen voorraad</li><li>alleen voor bedrijven met een geldig KVK-nummer</li></ul>
{('<h2>Meer van ' + E(nm) + '</h2><div class="grid">' + cards + '</div><p><a href="/merken/' + b['slug'] + '/">Alles van ' + E(nm) + ' bekijken &rarr;</a></p>') if cards else ''}
</main>
<footer><div class="wrap">THH Europe B.V. · B2B groothandel in sportvoeding · <a href="mailto:info@thheurope.nl">info@thheurope.nl</a> · <a href="/">thheurope.com</a></div></footer>
</body></html>
"""
    write(f"{DIR}/{slug}/index.html", doc)
    return url
