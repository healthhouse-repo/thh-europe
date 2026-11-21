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
    cl = cat_link(p.get("category") or "Other")
    catcell = (f'<a href="{cl}">' + E(cat[:1].upper() + cat[1:]) + '</a>') if cl else E(cat[:1].upper() + cat[1:])
    rows = [("Merk", f'<a href="/merken/{b["slug"]}/">{E(nm)}</a>'), ("Categorie", catcell)]
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


# ---------------------------------------------------------------- categoriepagina's
CAT_SLUG = {
    "Vitamins": "vitamines-supplementen", "Protein": "eiwitpoeders", "Amino Acids": "aminozuren",
    "Pre-Workout": "pre-workout", "Weight Loss": "afslanken", "Accessories": "accessoires",
    "Health": "gezondheid", "Creatine": "creatine", "Bars & Snacks": "eiwitrepen-snacks",
    "Test Boosters": "testosteron-boosters", "Energy & Hydration": "energie-sportdranken",
}
CAT_TITLE = {
    "Vitamins": "Vitamines en supplementen", "Protein": "Eiwitpoeders", "Amino Acids": "Aminozuren",
    "Pre-Workout": "Pre-workouts", "Weight Loss": "Afslankproducten", "Accessories": "Accessoires",
    "Health": "Gezondheidsproducten", "Creatine": "Creatine", "Bars & Snacks": "Eiwitrepen en snacks",
    "Test Boosters": "Testosteron boosters", "Energy & Hydration": "Energie- en sportdranken",
}


def cat_link(cat_key):
    s = CAT_SLUG.get(cat_key)
    return f"/categorie/{s}/" if s else None


def join_nl(xs):
    xs = [x for x in xs if x]
    if not xs:
        return ""
    return xs[0] if len(xs) == 1 else ", ".join(xs[:-1]) + " en " + xs[-1]


def build_categories(brands, img_url, css, write, usage):
    """usage: categorie -> gebruiksmoment (uit merkpaginas.CAT). Geeft de url's terug."""
    by_cat = {}
    for b in brands:
        for p in b["prods"]:
            k = p.get("category") or "Other"
            if k in CAT_SLUG:
                by_cat.setdefault(k, []).append((b, p))
    rel = lambda u: u[len(SITE):] if u.startswith(SITE + "/") else u
    urls, overview = [], []
    for k in sorted(by_cat, key=lambda c: -len(by_cat[c])):
        items = by_cat[k]
        title_nl = CAT_TITLE[k]
        low = title_nl[:1].lower() + title_nl[1:]
        slug = CAT_SLUG[k]
        url = f"{SITE}/categorie/{slug}/"
        per_brand = {}
        for b, p in items:
            per_brand.setdefault(b["slug"], [b, 0])[1] += 1
        bl = sorted(per_brand.values(), key=lambda x: (-x[1], x[0]["clean"].lower()))
        top = [x[0]["clean"] for x in bl[:5]]
        n, nb = len(items), len(bl)
        # voorbeeldproducten: om en om uit de grootste merken, met foto
        withimg = {x[0]["slug"]: [p for bb, p in items if bb is x[0] and p.get("img")] for x in bl}
        grid, r = [], 0
        while len(grid) < 48 and any(len(v) > r for v in withimg.values()):
            for x in bl:
                ps = withimg[x[0]["slug"]]
                if len(ps) > r and len(grid) < 48:
                    grid.append((x[0], ps[r]))
            r += 1
        cards = "".join(
            f'<a class="card" href="/{DIR}/{E(p["slug"])}/"><img src="{E(rel(img_url(p)))}" alt="{E(full_name(p, b["clean"]))}" loading="lazy">'
            f'<span>{E(full_name(p, b["clean"]))}</span><small>{E(p.get("unit") or "")}</small></a>' for b, p in grid)
        brands_html = "".join(f'<li><a href="/merken/{x[0]["slug"]}/">{E(x[0]["clean"])}</a> ({x[1]})</li>' for x in bl)
        use = usage.get(k) or ""
        title = f"{title_nl} groothandel | {low} zakelijk inkopen | THH Europe"
        if len(title) > 70:
            title = f"{title_nl} groothandel | THH Europe"
        meta = (f"{title_nl} zakelijk inkopen: {n} producten van {nb} merken, waaronder {join_nl(top[:3])}. "
                f"Groothandelsprijzen vanaf 1 stuk, gratis levering vanaf € 1.000.")
        intro = (f"Bij THH Europe koop je {low} zakelijk in voor je winkel, sportschool of webshop. "
                 f"In de catalogus staan {n} producten van {nb} merken, waaronder {join_nl(top)}. "
                 f"Je betaalt groothandelsprijzen, al vanaf 1 stuk.")
        faq = [
            (f"Kan ik {low} zakelijk inkopen?", f"Ja. Bij THH Europe koop je {low} in als bedrijf met een geldig KVK-nummer, al vanaf 1 stuk per artikel. Het minimale bestelbedrag is € 500 excl. btw."),
            (f"Welke merken {low} levert THH Europe?", f"Op dit moment {nb} merken, waaronder {join_nl(top)}."),
            ("Wat kost de levering?", "Bestellingen vanaf € 1.000 excl. btw leveren we gratis. Daaronder rekenen we € 9,95 verzendkosten."),
        ]
        others = "".join(f'<a href="/categorie/{CAT_SLUG[o]}/">{E(CAT_TITLE[o])}</a> · ' for o in sorted(by_cat) if o != k).rstrip(" · ")
        ld_faq = {"@context": "https://schema.org", "@type": "FAQPage", "mainEntity": [
            {"@type": "Question", "name": q, "acceptedAnswer": {"@type": "Answer", "text": a}} for q, a in faq]}
        ld_bc = {"@context": "https://schema.org", "@type": "BreadcrumbList", "itemListElement": [
            {"@type": "ListItem", "position": 1, "name": "THH Europe", "item": SITE + "/"},
            {"@type": "ListItem", "position": 2, "name": "Categorieën", "item": SITE + "/categorie/"},
            {"@type": "ListItem", "position": 3, "name": title_nl, "item": url}]}
        doc = f"""<!doctype html>
<html lang="nl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>{E(title)}</title>
<meta name="description" content="{E(meta)}">
<link rel="canonical" href="{url}">
<meta property="og:title" content="{E(title)}"><meta property="og:description" content="{E(meta)}"><meta property="og:url" content="{url}"><meta property="og:type" content="website">
<link rel="icon" href="/favicon.ico">
<style>{css}.cols{{columns:3 200px;padding-left:20px}}</style>
<script type="application/ld+json">{json.dumps(ld_faq, ensure_ascii=False)}</script>
<script type="application/ld+json">{json.dumps(ld_bc, ensure_ascii=False)}</script>
</head><body>
<header class="top"><div class="wrap"><a class="logo" href="/"><b>THHEU</b><span>THH EUROPE</span></a>
<nav><a href="/#catalogus">Catalogus</a><a href="/merken/">Merken</a><a href="/#word-klant">Word klant</a><a href="/#inloggen">Inloggen</a></nav></div></header>
<main class="wrap">
<div class="crumb"><a href="/">THH Europe</a> / <a href="/categorie/">Categorieën</a> / {E(title_nl)}</div>
<h1 style="margin-top:18px">{E(title_nl)} groothandel</h1>
<p>{E(intro)}</p>
<div class="btns"><a class="btn" href="/#catalogus?cat={E(k)}">Bekijk alle {n} producten</a><a class="btn sec" href="/#word-klant">Word zakelijk klant</a></div>
<div class="grid">{cards}</div>
<h2>{E(title_nl)} zakelijk inkopen</h2>
<p>Producten in deze categorie passen in een assortiment voor sporters en actieve klanten{(', bijvoorbeeld voor ' + E(use)) if use else ''}. Je bestelt bij THH Europe vanuit eigen voorraad.</p>
<ul><li>groothandelsprijzen al vanaf 1 stuk</li><li>extra staffelkorting bij grotere aantallen</li><li>gratis levering vanaf € 1.000 excl. btw</li><li>alleen voor bedrijven met een geldig KVK-nummer</li></ul>
<h2>Merken in {E(low)}</h2>
<ul class="cols">{brands_html}</ul>
<h2>Veelgestelde vragen</h2>
<div class="faq">{''.join(f'<details><summary>{E(q)}</summary><p>{E(a)}</p></details>' for q, a in faq)}</div>
<p style="font-size:13px;margin-top:30px">Andere categorieën: {others}</p>
</main>
<footer><div class="wrap">THH Europe B.V. · B2B groothandel in sportvoeding · <a href="mailto:info@thheurope.nl">info@thheurope.nl</a> · <a href="/">thheurope.com</a></div></footer>
</body></html>
"""
        write(f"categorie/{slug}/index.html", doc)
        urls.append(url)
        overview.append((slug, title_nl, n, nb))
    items = "".join(f'<a class="card" href="/categorie/{s}/"><b class="lgt">{E(t)}</b><small>{n} producten · {nb} merken</small></a>' for s, t, n, nb in overview)
    doc = f"""<!doctype html><html lang="nl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Categorieën groothandel sportvoeding | THH Europe</title><meta name="description" content="Alle categorieën sportvoeding en supplementen die je zakelijk inkoopt bij THH Europe: van eiwitpoeders en creatine tot vitamines en sportdranken."><link rel="canonical" href="{SITE}/categorie/">
<style>{css}</style></head><body>
<header class="top"><div class="wrap"><a class="logo" href="/"><b>THHEU</b><span>THH EUROPE</span></a><nav><a href="/#catalogus">Catalogus</a><a href="/merken/">Merken</a><a href="/#word-klant">Word klant</a><a href="/#inloggen">Inloggen</a></nav></div></header>
<main class="wrap"><div class="crumb"><a href="/">THH Europe</a> / Categorieën</div><h1 style="margin-top:18px">Categorieën</h1><p>Alle categorieën sportvoeding en supplementen die je zakelijk inkoopt bij THH Europe.</p>
<div class="grid">{items}</div></main>
<footer><div class="wrap">THH Europe B.V. · <a href="mailto:info@thheurope.nl">info@thheurope.nl</a></div></footer></body></html>"""
    write("categorie/index.html", doc)
    return [f"{SITE}/categorie/"] + urls


# ---------------------------------------------------------------- landingspagina
def build_landing(brands, img_url, css, write):
    """Landingspagina /groothandel-sportvoeding/. Alle feiten uit de catalogus en de voorwaarden op de site."""
    rel = lambda u: u[len(SITE):] if u.startswith(SITE + "/") else u
    allp = [(b, p) for b in brands for p in b["prods"]]
    n, nb = len(allp), len(brands)
    cats = {}
    for b, p in allp:
        k = p.get("category") or "Other"
        if k in CAT_SLUG:
            cats[k] = cats.get(k, 0) + 1
    cat_list = "".join(f'<li><a href="/categorie/{CAT_SLUG[k]}/">{E(CAT_TITLE[k])}</a> ({c} producten)</li>'
                       for k, c in sorted(cats.items(), key=lambda x: -x[1]))
    big = sorted(brands, key=lambda b: -b["n"])[:12]
    brand_cards = "".join(
        f'<a class="card" href="/merken/{b["slug"]}/">' + (f'<img class="lgi" src="{E(b["logo"])}" alt="{E(b["clean"])} logo" loading="lazy">' if b.get("logo") else f'<b class="lgt">{E(b["clean"])}</b>')
        + f'<span>{E(b["clean"])}</span><small>{b["n"]} producten</small></a>' for b in big)
    top5 = join_nl([b["clean"] for b in big[:5]])
    url = f"{SITE}/groothandel-sportvoeding/"
    title = "Groothandel sportvoeding en supplementen | THH Europe"
    meta = (f"Sportvoeding en supplementen zakelijk inkopen: {n} producten van {nb} merken, waaronder {top5}. "
            "Groothandelsprijzen vanaf 1 stuk, gratis levering vanaf € 1.000.")
    faq = [
        ("Wie kan er bij THH Europe inkopen?", "Bedrijven met een geldig KVK-nummer, zoals supplementenwinkels, sportscholen, webshops, retail en horeca. We leveren niet aan consumenten."),
        ("Is er een minimale afname?", "Er geldt geen minimale afname per artikel. Het minimale bestelbedrag is € 500 excl. btw."),
        ("Wat kost de verzending?", "Bestellingen vanaf € 1.000 excl. btw leveren we gratis. Daaronder rekenen we € 9,95 verzendkosten. Pallet- en afhaalleveringen gaan in overleg."),
        ("Hoe snel wordt er geleverd?", "Op werkdagen voor 15:00 besteld, gaat dezelfde dag de deur uit zolang de voorraad strekt. In Nederland en België ligt de bestelling binnen 1 tot 2 werkdagen bij je."),
        ("Hoe zie ik de groothandelsprijzen?", "Meld je aan als zakelijke klant. Zodra je account actief is, zie je na inloggen je eigen prijzen en de staffelkorting per artikel. Je kunt ook een offertelijst samenstellen en een prijsopgave aanvragen."),
        ("Krijg ik als EU-bedrijf een factuur zonder btw?", "EU-bedrijven buiten Nederland met een geldig btw-nummer krijgen een factuur met verlegde btw."),
    ]
    ld = [{"@context": "https://schema.org", "@type": "FAQPage", "mainEntity": [
        {"@type": "Question", "name": q, "acceptedAnswer": {"@type": "Answer", "text": a}} for q, a in faq]},
        {"@context": "https://schema.org", "@type": "BreadcrumbList", "itemListElement": [
            {"@type": "ListItem", "position": 1, "name": "THH Europe", "item": SITE + "/"},
            {"@type": "ListItem", "position": 2, "name": "Groothandel sportvoeding", "item": url}]}]
    doc = f"""<!doctype html>
<html lang="nl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>{E(title)}</title>
<meta name="description" content="{E(meta)}">
<link rel="canonical" href="{url}">
<meta property="og:title" content="{E(title)}"><meta property="og:description" content="{E(meta)}"><meta property="og:url" content="{url}"><meta property="og:type" content="website">
<link rel="icon" href="/favicon.ico">
<style>{css}.cols{{columns:2 260px;padding-left:20px}}.steps{{counter-reset:s;list-style:none;padding:0}}.steps li{{counter-increment:s;padding:8px 0 8px 40px;position:relative}}.steps li:before{{content:counter(s);position:absolute;left:0;top:6px;width:26px;height:26px;border-radius:50%;background:#1A1A1A;color:#fff;font-weight:700;font-size:13px;display:flex;align-items:center;justify-content:center}}</style>
{''.join('<script type="application/ld+json">' + json.dumps(x, ensure_ascii=False) + '</script>' for x in ld)}
</head><body>
<header class="top"><div class="wrap"><a class="logo" href="/"><b>THHEU</b><span>THH EUROPE</span></a>
<nav><a href="/#catalogus">Catalogus</a><a href="/merken/">Merken</a><a href="/#word-klant">Word klant</a><a href="/#inloggen">Inloggen</a></nav></div></header>
<main class="wrap">
<div class="crumb"><a href="/">THH Europe</a> / Groothandel sportvoeding</div>
<h1 style="margin-top:18px">Groothandel sportvoeding</h1>
<p>THH Europe is een B2B-groothandel in sportvoeding en supplementen. We leveren aan supplementenwinkels, sportscholen, webshops, retail en horeca. In de catalogus staan {n} producten van {nb} merken, waaronder {E(top5)}. Je koopt al vanaf 1 stuk in tegen groothandelsprijzen, met extra staffelkorting bij grotere aantallen.</p>
<div class="btns"><a class="btn" href="/#word-klant">Word zakelijk klant</a><a class="btn sec" href="/#catalogus">Bekijk de catalogus</a></div>
<div class="usp">Groothandelsprijzen vanaf 1 stuk · Staffelkorting · Gratis levering vanaf € 1.000 · Voor 15:00 besteld, dezelfde dag verzonden</div>
<h2>Wat je bij ons inkoopt</h2>
<p>Van eiwitpoeders en creatine tot vitamines, pre-workouts en sportdranken. Per categorie:</p>
<ul class="cols">{cat_list}</ul>
<h2>Onze grootste merken</h2>
<div class="grid">{brand_cards}</div>
<p><a href="/merken/">Alle {nb} merken bekijken &rarr;</a></p>
<h2>Voor wie</h2>
<ul><li>supplementenwinkels en health stores</li><li>sportscholen, fitnessclubs en personal training studio's</li><li>webshops in sportvoeding en gezondheid</li><li>retail en horeca, zoals sportkantines</li></ul>
<h2>Zo werkt zakelijk inkopen</h2>
<ol class="steps"><li>Meld je bedrijf aan met je KVK-nummer.</li><li>Zodra je account actief is, zie je na inloggen je eigen groothandelsprijzen.</li><li>Bestel direct, of stel een offertelijst samen en vraag een prijsopgave aan.</li><li>Op werkdagen voor 15:00 besteld, gaat dezelfde dag de deur uit zolang de voorraad strekt.</li></ol>
<h2>Veelgestelde vragen</h2>
<div class="faq">{''.join(f'<details><summary>{E(q)}</summary><p>{E(a)}</p></details>' for q, a in faq)}</div>
</main>
<footer><div class="wrap">THH Europe B.V. · B2B groothandel in sportvoeding · <a href="mailto:info@thheurope.nl">info@thheurope.nl</a> · <a href="/">thheurope.com</a></div></footer>
</body></html>
"""
    write("groothandel-sportvoeding/index.html", doc)
    return [url]
