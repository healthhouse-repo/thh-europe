#!/usr/bin/env python3
"""Merkpagina's voor thheurope.com.

Leest de catalogus (var PRODUCTS in index.html) en maakt per merk:
  - merken/<slug>/index.html   (Nederlands, vindbaar in Google)
  - brands/<slug>/index.html   (Engels)
  - merken/index.html en brands/index.html (overzicht)
  - merken/teksten.json        (zelfde teksten, voor de merkfilter in de catalogus)
  - sitemap.xml en robots.txt

Alle feiten komen uit de catalogus (aantal producten, categorieen, verpakkingen, smaken)
en van de site zelf (groothandelsprijzen vanaf 1 stuk, staffelkorting, gratis levering
vanaf EUR 1.000). Er staan geen claims over merken of producten in die niet uit die bronnen komen.

Opnieuw draaien na wijzigingen in het assortiment:  python3 scripts/merkpaginas.py
"""
import collections, html, json, os, re, unicodedata, datetime

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
SITE = "https://thheurope.com"
LOGO_BASE = "https://cdn.jsdelivr.net/gh/healthhouse-repo/thh-xxl-images@main/brand-logos/"
LOGO_ALIAS = {"huge supplements": "huge", "nutri+": "nutri", "lenny & larry's": "lenny-larrys",
              "5% nutrition rich piana": "5-nutrition", "doctor's best": "docters-best"}

E = html.escape


def slugify(n):
    k = str(n or "").lower().strip()
    k = unicodedata.normalize("NFD", k)
    k = "".join(c for c in k if unicodedata.category(c) != "Mn")
    k = re.sub(r"['’`]", "", k).replace("&", "")
    return re.sub(r"[^a-z0-9]+", "-", k).strip("-")


def logo_slug(n):
    k = str(n or "").lower().strip()
    return LOGO_ALIAS.get(k) or slugify(n)


# Logo's: eerst de logo's van de merkenpagina van thehealthhouse.nl (WordPress-media), dan de repo thh-xxl-images.
WP = "https://thehealthhouse.nl/wp-content/uploads/2026/11/logo-"
WP_LOGO = {
    "now foods": "now-foods.webp", "xxl nutrition": "xxl-nutrition.webp", "applied nutrition": "applied-nutrition.webp",
    "qnt": "qnt.svg", "ultimate nutrition": "ultimate-nutrition.png", "quamtrax": "quamtrax.webp",
    "5% nutrition rich piana": "5-nutrition.webp", "5% nutrition": "5-nutrition.webp", "adidas": "adidas.webp",
    "algomed": "algomed.webp", "allmax": "allmax.png", "allmax nutrition": "allmax.png",
    "arthur andrew medical": "arthur-andrew-medical.webp", "barebells": "barebells.webp", "cellucor": "cellucor.png",
    "dorian yates": "dorian-yates.webp", "four sigmatic": "four-sigmatic.webp", "green pasture": "green-pasture.webp",
    "grenade": "grenade-zwart.png", "laviesage": "laviesage.webp", "maxi nutrition": "maxi-nutrition.webp",
    "monster energy": "monster-energy.webp", "mutant": "mutant.webp", "myoblox": "myoblox.webp", "nocco": "nocco.png",
    "nordic naturals": "nordic-naturals.webp", "nutrend": "nutrend-zwart-v3.png", "nutrex": "nutrex.svg",
    "optimum nutrition": "optimum-nutrition-zwart.png", "pb2 foods": "pb2-bell-plantation.webp",
    "pb2 (bell plantation)": "pb2-bell-plantation.webp", "per4m": "per4m.webp", "pescience": "pescience.webp",
    "plantforce": "plantforce.webp", "prosupps": "prosupps.svg", "protein world": "protein-world.webp",
    "real roots": "real-roots.webp", "red bull": "red-bull.webp", "rule one": "rule-one.webp", "scitec": "scitec.webp",
    "silverback protein": "silverback-protein.webp", "stacker2 europe": "stacker2.png", "sunwarrior": "sunwarrior.webp",
    "the health house": "the-health-house-zwart.png", "trace minerals": "trace-minerals.webp",
    "trained by jp": "trained-by-jp.webp", "usn": "usn-zwart-v2.png", "vp laboratory": "vp-labs.webp",
    "vp labs": "vp-labs.webp", "weider": "weider.webp", "wobenzym": "wobenzym.webp",
}
# Nieuw gevonden logo's (november), op term-slug van de merknaam.
WP_SLUG = {
    "cnp": "cnp.png", "cnp-professional": "cnp.png", "ghost": "ghost.png", "himalaya": "himalaya.svg",
    "more": "more.svg", "more-nutrition": "more.svg", "state": "state.png", "state-drinks": "state.png",
    "nutry-nuts": "nutry-nuts.png", "country-life": "country-life.webp", "dy-nutrition": "dorian-yates.webp",
    "prime": "prime.webp", "huel": "huel.svg", "nuchu": "nuchu.png", "hayabusa": "hayabusa.webp", "venum": "venum.webp", "safe-jaws": "safe-jaws.png", "ufc": "ufc.webp",
}
XXL_EXTRA = {"alkavitae": "alka", "enfusion nutrition": "enfusion"}
# Bestanden in thh-xxl-images die geen echt logo zijn (letter-placeholder, leeg, kapot of te klein icoon).
XXL_BAD = {"10x-nutrition", "cellucor", "dorian-yates", "efx-sports", "enfusion", "mars-protein", "now-foods",
           "nutrend", "per4m", "prime", "quamtrax", "sunwarrior", "the-health-house", "weider", "xxl-nutrition",
           "redcon1", "rule-one", "natrol", "truvia", "5-nutrition", "allmax", "applied-nutrition", "grenade",
           "mutant", "nutrex", "pb2-bell-plantation", "stacker2", "usn"}
XXL_FILES = None


def logo_url(n):
    """URL van het merklogo, of None als er (nog) geen logo is."""
    global XXL_FILES
    k = clean_brand(n).lower()
    if k in WP_LOGO:
        return WP + WP_LOGO[k]
    if slugify(k) in WP_SLUG:
        return WP + WP_SLUG[slugify(k)]
    if XXL_FILES is None:
        p = os.environ.get("XXL_LOGOS", "")
        XXL_FILES = set(os.listdir(p)) if p and os.path.isdir(p) else set()
    for slug in (XXL_EXTRA.get(k), LOGO_ALIAS.get(k), slugify(k)):
        if slug and slug not in XXL_BAD and slug + ".png" in XXL_FILES:
            return LOGO_BASE + slug + ".png"
    return None


def clean_brand(n):
    """Merknaam voor in lopende tekst: zonder (R), (TM)."""
    return re.sub(r"\s*[®™©]\s*", " ", n).strip()


# Categorie: (nl enkelvoud/kort, nl in zin, en, gebruiksmoment nl, gebruiksmoment en)
CAT = {
    "Vitamins": ("vitamines en mineralen", "vitamines en supplementen", "vitamins and supplements",
                 "dagelijkse aanvulling op de voeding", "a daily addition to the diet"),
    "Protein": ("eiwit", "eiwitpoeders", "protein powders",
                "dagelijkse eiwitinname en herstel na de training", "daily protein intake and post-workout recovery"),
    "Amino Acids": ("aminozuren", "aminozuren", "amino acids",
                    "voor, tijdens en na de training", "before, during and after training"),
    "Pre-Workout": ("pre-workout", "pre-workouts", "pre-workouts",
                    "energie en focus vóór de training", "energy and focus before training"),
    "Weight Loss": ("afslanken", "afslankproducten", "weight-loss products",
                    "klanten die werken aan hun gewicht", "customers working on their weight"),
    "Accessories": ("accessoires", "accessoires", "accessories",
                    "shakers en accessoires bij de kassa", "shakers and accessories at the till"),
    "Health": ("gezondheid", "gezondheidsproducten", "health products",
               "algemene gezondheid en welzijn", "general health and wellbeing"),
    "Creatine": ("creatine", "creatine", "creatine",
                 "kracht- en fitnesssporters", "strength and fitness athletes"),
    "Bars & Snacks": ("repen en snacks", "eiwitrepen en snacks", "protein bars and snacks",
                      "een snelle snack onderweg of na het sporten", "a quick snack on the go or after training"),
    "Test Boosters": ("testosteron boosters", "testosteron boosters", "test boosters",
                      "mannelijke krachtsporters", "male strength athletes"),
    "Energy & Hydration": ("energie en hydratatie", "energie- en sportdranken", "energy and sports drinks",
                           "energie en vocht tijdens het sporten", "energy and hydration during exercise"),
    "Other": ("overig", "overige producten", "other products", "", ""),
}


def join_nl(xs):
    xs = [x for x in xs if x]
    return xs[0] if len(xs) == 1 else ", ".join(xs[:-1]) + " en " + xs[-1] if xs else ""


def join_en(xs):
    xs = [x for x in xs if x]
    return xs[0] if len(xs) == 1 else ", ".join(xs[:-1]) + " and " + xs[-1] if xs else ""


API = "https://thehealthhouse.nl/wp-json/thhe/v1/catalog/products"
# Merken die bewust niet op de site worden uitgelicht.
UITGESLOTEN = {"jennah's organics", "jennah organics", "jennah"}


def load_products():
    """Live catalogus (zelfde bron als de site); valt terug op PRODUCTS in index.html."""
    try:
        import urllib.request
        req = urllib.request.Request(API, data=b'{"v":""}', headers={"Content-Type": "application/json", "User-Agent": "thheurope-merkpaginas/1.0"})
        d = json.load(urllib.request.urlopen(req, timeout=60))
        if d.get("products"):
            return d["products"]
    except Exception as e:  # noqa
        print("live catalogus niet bereikbaar, val terug op index.html:", e)
    s = open(os.path.join(ROOT, "index.html"), encoding="utf-8").read()
    m = re.search(r"var PRODUCTS = (\[.*?\]);\n", s, re.S)
    return json.loads(m.group(1))


def brand_data(name, prods):
    cats = collections.Counter(p.get("category") or "Other" for p in prods)
    cats = [c for c, _ in cats.most_common() if c in CAT]
    units = collections.Counter((p.get("unit") or "").strip() for p in prods if (p.get("unit") or "").strip())
    flavors = sum(len(p.get("flavors") or []) for p in prods)
    # voorbeeldproducten: eerst uit de grootste categorieen, met foto
    seen, examples = set(), []
    for c in cats:
        for p in prods:
            if p.get("category") == c and p["slug"] not in seen and p.get("img"):
                examples.append(p); seen.add(p["slug"]); break
    for p in prods:
        if len(examples) >= 6: break
        if p["slug"] not in seen and p.get("img"):
            examples.append(p); seen.add(p["slug"])
    return {"name": name, "clean": clean_brand(name), "logo": logo_url(name), "slug": slugify(name), "n": len(prods), "cats": cats,
            "units": [u for u, _ in units.most_common(4)], "flavors": flavors, "examples": examples[:6],
            "prods": prods}


def pname(p):
    """Productnaam zonder merk ervoor, zoals in de catalogus."""
    return p.get("name") or p["slug"]


def img_url(p):
    u = p.get("img") or ""
    return u if u.startswith("http") else SITE + "/" + u.lstrip("/")


def texts(b):
    """Geeft per taal: titel, metaomschrijving, intro (1 alinea) en body-secties."""
    nm, n, cats = b["clean"], b["n"], b["cats"]
    cat_nl = join_nl([CAT[c][1] for c in cats[:4]])
    cat_en = join_en([CAT[c][2] for c in cats[:4]])
    ex_nl = join_nl([pname(p) for p in b["examples"][:3]])
    ex_en = join_en([pname(p) for p in b["examples"][:3]])
    prod_nl = "1 product" if n == 1 else f"{n} producten"
    prod_en = "1 product" if n == 1 else f"{n} products"
    moments = [c for c in cats if CAT[c][3]][:5]
    units = b["units"]

    nl = {
        "title": f"{nm} groothandel | {nm} zakelijk inkopen | THH Europe",
        "meta": f"{nm} zakelijk inkopen voor je winkel, sportschool of webshop. {prod_nl} van {nm} tegen groothandelsprijzen, al vanaf 1 stuk. Gratis levering vanaf € 1.000.",
        "h1": f"{nm} groothandel",
        "intro": f"Op zoek naar {nm} voor je winkel, sportschool, webshop of fitnessconcept? Bij THH Europe koop je {nm} eenvoudig zakelijk in, tegen groothandelsprijzen en al vanaf 1 stuk. "
                 + (f"Het assortiment bestaat uit {cat_nl}." if cat_nl else ""),
        "sections": [],
        "faq": [],
    }
    s = nl["sections"]
    s.append((f"Wat is {nm}?",
              [f"{nm} is een merk binnen {cat_nl if cat_nl else 'sportvoeding en supplementen'}. "
               f"In de catalogus van THH Europe staan op dit moment {prod_nl} van {nm}"
               + (f", waaronder {ex_nl}." if ex_nl else ".")
               + (f" Samen goed voor {b['flavors']} smaken en varianten." if b["flavors"] > n else "")], None))
    s.append((f"{nm} zakelijk inkopen",
              [f"Wil je {nm} inkopen voor wederverkoop? Dan is THH Europe je groothandelspartner in de Benelux. "
               f"Je bestelt vanuit onze eigen voorraad en betaalt groothandelsprijzen vanaf 1 stuk."],
              ["groothandelsprijzen al vanaf 1 stuk",
               "extra staffelkorting bij grotere aantallen",
               "gratis levering vanaf € 1.000 excl. btw",
               "levering vanuit eigen voorraad",
               "een vaste accountmanager voor je bestellingen en vragen"]))
    if moments:
        s.append((f"Waarom {nm} in je assortiment?",
                  [f"{nm} past in een assortiment voor sporters, fitnessklanten en actieve consumenten. De producten sluiten aan bij:"],
                  [CAT[c][3] for c in moments]))
    s.append((f"{nm} groothandel voor winkels, gyms en webshops",
              [f"THH Europe levert {nm} aan zakelijke klanten die hun assortiment willen uitbreiden"
               + (f" met {cat_nl}" if cat_nl else "") + "."
               + (f" In de catalogus vind je onder meer verpakkingen van {join_nl(units)}." if units else ""),
               f"{nm} is daarmee interessant voor onder andere:"],
              ["supplementenwinkels", "sportscholen en fitnessclubs", "webshops", "health stores",
               "personal training studio's", "sportclubs en sportkantines"]))
    s.append((f"{nm} bestellen bij THH Europe",
              [f"Log in of meld je aan als zakelijke klant om je exacte prijzen te zien en {nm} te bestellen. "
               f"Je kunt producten ook op je offertelijst zetten; dan ontvang je een prijsopgave op maat."], None))
    nl["faq"] = [
        (f"Wat is {nm}?", f"{nm} is een merk binnen {cat_nl if cat_nl else 'sportvoeding en supplementen'}. Bij THH Europe staan {prod_nl} van {nm} in de catalogus."),
        (f"Kan ik {nm} zakelijk inkopen?", f"Ja. {nm} is zakelijk verkrijgbaar via THH Europe voor winkels, sportscholen, webshops en andere wederverkopers."),
        (f"Welke {nm} producten zijn verkrijgbaar?", f"In de catalogus staan {prod_nl} van {nm}" + (f", waaronder {ex_nl}." if ex_nl else ".")),
        ("Vanaf hoeveel stuks gelden de groothandelsprijzen?", "Groothandelsprijzen gelden al vanaf 1 stuk. Bij grotere aantallen krijg je extra staffelkorting."),
        ("Wat kost de verzending?", "Vanaf € 1.000 excl. btw leveren we gratis. Je exacte prijzen en verzendkosten zie je na het inloggen."),
    ]

    en = {
        "title": f"{nm} wholesale | Buy {nm} for your business | THH Europe",
        "meta": f"Buy {nm} wholesale for your shop, gym or webshop. {prod_en} from {nm} at wholesale prices, from just 1 unit. Free delivery from € 1,000.",
        "h1": f"{nm} wholesale",
        "intro": f"Looking for {nm} for your shop, gym, webshop or fitness concept? At THH Europe you buy {nm} for your business at wholesale prices, from just 1 unit. "
                 + (f"The range covers {cat_en}." if cat_en else ""),
        "sections": [],
        "faq": [],
    }
    s = en["sections"]
    s.append((f"What is {nm}?",
              [f"{nm} is a brand in {cat_en if cat_en else 'sports nutrition and supplements'}. "
               f"The THH Europe catalogue currently lists {prod_en} from {nm}"
               + (f", including {ex_en}." if ex_en else ".")
               + (f" Together they come in {b['flavors']} flavours and variants." if b["flavors"] > n else "")], None))
    s.append((f"Buy {nm} wholesale",
              [f"Want to stock {nm} for resale? THH Europe is your wholesale partner in the Benelux. "
               f"You order from our own stock and pay wholesale prices from just 1 unit."],
              ["wholesale prices from 1 unit",
               "extra volume discount on larger quantities",
               "free delivery from € 1,000 excl. VAT",
               "shipped from our own stock",
               "a dedicated account manager for your orders and questions"]))
    if moments:
        s.append((f"Why add {nm} to your range?",
                  [f"{nm} fits a range for athletes, fitness customers and active consumers. The products cover:"],
                  [CAT[c][4] for c in moments]))
    s.append((f"{nm} wholesale for shops, gyms and webshops",
              [f"THH Europe supplies {nm} to businesses that want to expand their range"
               + (f" with {cat_en}" if cat_en else "") + "."
               + (f" The catalogue includes pack sizes such as {join_en(units)}." if units else ""),
               f"{nm} is a good fit for:"],
              ["supplement stores", "gyms and fitness clubs", "webshops", "health stores",
               "personal training studios", "sports clubs and canteens"]))
    s.append((f"Order {nm} from THH Europe",
              [f"Log in or register as a business customer to see your exact prices and order {nm}. "
               f"You can also add products to your quote list and receive a tailored offer."], None))
    en["faq"] = [
        (f"What is {nm}?", f"{nm} is a brand in {cat_en if cat_en else 'sports nutrition and supplements'}. THH Europe lists {prod_en} from {nm}."),
        (f"Can I buy {nm} for my business?", f"Yes. {nm} is available through THH Europe for shops, gyms, webshops and other resellers."),
        (f"Which {nm} products are available?", f"The catalogue lists {prod_en} from {nm}" + (f", including {ex_en}." if ex_en else ".")),
        ("From how many units do wholesale prices apply?", "Wholesale prices apply from 1 unit, with extra volume discount on larger quantities."),
        ("What does shipping cost?", "Delivery is free from € 1,000 excl. VAT. Your exact prices and shipping costs are shown after logging in."),
    ]
    nl["faq_h"], en["faq_h"] = "Veelgestelde vragen", "Frequently asked questions"
    out = {"nl": nl, "en": en}
    for lang in ("fr", "de", "bs"):
        out[lang] = texts_x(b, lang)
    return out


# Extra talen: per categorie (in zin, gebruiksmoment)
CAT_X = {
    "fr": {"Vitamins": ("vitamines et compléments", "un complément quotidien à l'alimentation"),
           "Protein": ("protéines en poudre", "l'apport quotidien en protéines et la récupération après l'entraînement"),
           "Amino Acids": ("acides aminés", "avant, pendant et après l'entraînement"),
           "Pre-Workout": ("pré-workouts", "l'énergie et la concentration avant l'entraînement"),
           "Weight Loss": ("produits minceur", "les clients qui surveillent leur poids"),
           "Accessories": ("accessoires", "les shakers et accessoires en caisse"),
           "Health": ("produits santé", "la santé et le bien-être au quotidien"),
           "Creatine": ("créatine", "les sportifs de force et de fitness"),
           "Bars & Snacks": ("barres protéinées et snacks", "un en-cas rapide en déplacement ou après le sport"),
           "Test Boosters": ("boosters de testostérone", "les sportifs de force masculins"),
           "Energy & Hydration": ("boissons énergétiques et sportives", "l'énergie et l'hydratation pendant l'effort"),
           "Other": ("autres produits", "")},
    "de": {"Vitamins": ("Vitamine und Nahrungsergänzungsmittel", "die tägliche Ergänzung der Ernährung"),
           "Protein": ("Proteinpulver", "die tägliche Eiweißzufuhr und Regeneration nach dem Training"),
           "Amino Acids": ("Aminosäuren", "vor, während und nach dem Training"),
           "Pre-Workout": ("Pre-Workouts", "Energie und Fokus vor dem Training"),
           "Weight Loss": ("Abnehmprodukte", "Kunden, die an ihrem Gewicht arbeiten"),
           "Accessories": ("Zubehör", "Shaker und Zubehör an der Kasse"),
           "Health": ("Gesundheitsprodukte", "allgemeine Gesundheit und Wohlbefinden"),
           "Creatine": ("Kreatin", "Kraft- und Fitnesssportler"),
           "Bars & Snacks": ("Proteinriegel und Snacks", "einen schnellen Snack unterwegs oder nach dem Sport"),
           "Test Boosters": ("Testosteron-Booster", "männliche Kraftsportler"),
           "Energy & Hydration": ("Energie- und Sportgetränke", "Energie und Flüssigkeit beim Sport"),
           "Other": ("weitere Produkte", "")},
    "bs": {"Vitamins": ("vitamini i suplementi", "svakodnevni dodatak ishrani"),
           "Protein": ("proteinski prahovi", "svakodnevni unos proteina i oporavak nakon treninga"),
           "Amino Acids": ("aminokiseline", "prije, tokom i nakon treninga"),
           "Pre-Workout": ("pre-workout proizvodi", "energija i fokus prije treninga"),
           "Weight Loss": ("proizvodi za mršavljenje", "kupci koji rade na svojoj težini"),
           "Accessories": ("dodaci", "šejkeri i dodaci na kasi"),
           "Health": ("proizvodi za zdravlje", "opće zdravlje i dobrobit"),
           "Creatine": ("kreatin", "sportisti snage i fitnesa"),
           "Bars & Snacks": ("proteinske pločice i grickalice", "brza užina u pokretu ili nakon treninga"),
           "Test Boosters": ("testosteron boosteri", "muški sportisti snage"),
           "Energy & Hydration": ("energetska i sportska pića", "energija i hidratacija tokom sporta"),
           "Other": ("ostali proizvodi", "")},
}
AND = {"nl": " en ", "en": " and ", "fr": " et ", "de": " und ", "bs": " i "}


def join_x(xs, lang):
    xs = [x for x in xs if x]
    return xs[0] if len(xs) == 1 else ", ".join(xs[:-1]) + AND[lang] + xs[-1] if xs else ""


def texts_x(b, lang):
    """Franse, Duitse en Bosnische teksten, zelfde opbouw als nl/en."""
    nm, n, cats = b["clean"], b["n"], b["cats"]
    C = CAT_X[lang]
    cat = join_x([C[c][0] for c in cats[:4]], lang)
    ex = join_x([pname(p) for p in b["examples"][:3]], lang)
    moments = [C[c][1] for c in cats if C[c][1]][:5]
    units = join_x(b["units"], lang)
    fl = b["flavors"] if b["flavors"] > n else 0
    if lang == "fr":
        prod = "1 produit" if n == 1 else f"{n} produits"
        gen = cat or "la nutrition sportive et les compléments"
        t = {"title": f"{nm} grossiste | Acheter {nm} pour les professionnels | THH Europe",
             "meta": f"Achetez {nm} en gros pour votre magasin, salle de sport ou boutique en ligne. {prod} de {nm} aux prix de gros, dès 1 pièce. Livraison gratuite dès 1 000 €.",
             "h1": f"{nm} grossiste",
             "intro": f"Vous cherchez {nm} pour votre magasin, salle de sport, boutique en ligne ou concept fitness ? Chez THH Europe, vous achetez {nm} en tant que professionnel, aux prix de gros et dès 1 pièce. " + (f"La gamme couvre : {cat}." if cat else ""),
             "faq_h": "Questions fréquentes",
             "sections": [
                 (f"Qu'est-ce que {nm} ?", [f"{nm} est une marque de {gen}. Le catalogue de THH Europe propose actuellement {prod} de {nm}" + (f", dont {ex}." if ex else ".") + (f" Au total, {fl} saveurs et variantes." if fl else "")], None),
                 (f"Acheter {nm} en gros", [f"Vous souhaitez revendre {nm} ? THH Europe est votre grossiste dans le Benelux. Vous commandez depuis notre propre stock et payez les prix de gros dès 1 pièce."],
                  ["prix de gros dès 1 pièce", "remise supplémentaire sur les quantités", "livraison gratuite dès 1 000 € HT", "expédition depuis notre propre stock", "un gestionnaire de compte dédié pour vos commandes et questions"]),
             ] + ([(f"Pourquoi {nm} dans votre gamme ?", [f"{nm} convient à une gamme pour sportifs, clients fitness et consommateurs actifs. Les produits répondent à :"], moments)] if moments else []) + [
                 (f"{nm} en gros pour magasins, salles de sport et boutiques en ligne", [f"THH Europe livre {nm} aux professionnels qui veulent élargir leur gamme" + (f" en {cat}" if cat else "") + "." + (f" Le catalogue comprend notamment des formats de {units}." if units else ""), f"{nm} convient notamment pour :"],
                  ["magasins de compléments", "salles de sport et clubs de fitness", "boutiques en ligne", "magasins bio et santé", "studios de coaching personnel", "clubs sportifs et buvettes"]),
                 (f"Commander {nm} chez THH Europe", [f"Connectez-vous ou inscrivez-vous comme client professionnel pour voir vos prix exacts et commander {nm}. Vous pouvez aussi ajouter des produits à votre liste de devis pour recevoir une offre sur mesure."], None)],
             "faq": [(f"Qu'est-ce que {nm} ?", f"{nm} est une marque de {gen}. THH Europe propose {prod} de {nm}."),
                     (f"Puis-je acheter {nm} en tant que professionnel ?", f"Oui. {nm} est disponible via THH Europe pour les magasins, salles de sport, boutiques en ligne et autres revendeurs."),
                     (f"Quels produits {nm} sont disponibles ?", f"Le catalogue propose {prod} de {nm}" + (f", dont {ex}." if ex else ".")),
                     ("À partir de combien de pièces les prix de gros s'appliquent-ils ?", "Les prix de gros s'appliquent dès 1 pièce, avec une remise supplémentaire sur les quantités plus importantes."),
                     ("Combien coûte la livraison ?", "La livraison est gratuite dès 1 000 € HT. Vos prix exacts et frais de livraison s'affichent après connexion.")]}
    elif lang == "de":
        prod = "1 Produkt" if n == 1 else f"{n} Produkte"
        gen = cat or "Sporternährung und Nahrungsergänzung"
        t = {"title": f"{nm} Großhandel | {nm} für Händler einkaufen | THH Europe",
             "meta": f"{nm} im Großhandel für Ihr Geschäft, Fitnessstudio oder Ihren Onlineshop. {prod} von {nm} zu Großhandelspreisen, schon ab 1 Stück. Kostenlose Lieferung ab 1.000 €.",
             "h1": f"{nm} Großhandel",
             "intro": f"Sie suchen {nm} für Ihr Geschäft, Fitnessstudio, Ihren Onlineshop oder Ihr Fitnesskonzept? Bei THH Europe kaufen Sie {nm} als Geschäftskunde zu Großhandelspreisen ein, schon ab 1 Stück. " + (f"Das Sortiment umfasst {cat}." if cat else ""),
             "faq_h": "Häufige Fragen",
             "sections": [
                 (f"Was ist {nm}?", [f"{nm} ist eine Marke im Bereich {gen}. Im Katalog von THH Europe finden Sie derzeit {prod} von {nm}" + (f", darunter {ex}." if ex else ".") + (f" Insgesamt {fl} Geschmacksrichtungen und Varianten." if fl else "")], None),
                 (f"{nm} im Großhandel einkaufen", [f"Sie möchten {nm} weiterverkaufen? THH Europe ist Ihr Großhandelspartner im Benelux-Raum. Sie bestellen aus unserem eigenen Lager und zahlen Großhandelspreise ab 1 Stück."],
                  ["Großhandelspreise ab 1 Stück", "zusätzlicher Mengenrabatt bei größeren Mengen", "kostenlose Lieferung ab 1.000 € zzgl. MwSt.", "Lieferung aus eigenem Lager", "ein fester Ansprechpartner für Bestellungen und Fragen"]),
             ] + ([(f"Warum {nm} in Ihr Sortiment?", [f"{nm} passt in ein Sortiment für Sportler, Fitnesskunden und aktive Verbraucher. Die Produkte eignen sich für:"], moments)] if moments else []) + [
                 (f"{nm} Großhandel für Geschäfte, Fitnessstudios und Onlineshops", [f"THH Europe beliefert Geschäftskunden mit {nm}, die ihr Sortiment" + (f" um {cat}" if cat else "") + " erweitern möchten." + (f" Im Katalog finden Sie unter anderem Packungsgrößen von {units}." if units else ""), f"{nm} eignet sich unter anderem für:"],
                  ["Supplement-Shops", "Fitnessstudios und Sportclubs", "Onlineshops", "Reformhäuser und Health Stores", "Personal-Training-Studios", "Sportvereine und Vereinsheime"]),
                 (f"{nm} bei THH Europe bestellen", [f"Melden Sie sich an oder registrieren Sie sich als Geschäftskunde, um Ihre genauen Preise zu sehen und {nm} zu bestellen. Sie können Produkte auch auf Ihre Angebotsliste setzen und erhalten dann ein individuelles Angebot."], None)],
             "faq": [(f"Was ist {nm}?", f"{nm} ist eine Marke im Bereich {gen}. Bei THH Europe finden Sie {prod} von {nm}."),
                     (f"Kann ich {nm} als Händler einkaufen?", f"Ja. {nm} ist über THH Europe für Geschäfte, Fitnessstudios, Onlineshops und andere Wiederverkäufer erhältlich."),
                     (f"Welche {nm} Produkte sind erhältlich?", f"Im Katalog finden Sie {prod} von {nm}" + (f", darunter {ex}." if ex else ".")),
                     ("Ab wie vielen Stück gelten die Großhandelspreise?", "Großhandelspreise gelten schon ab 1 Stück. Bei größeren Mengen erhalten Sie zusätzlichen Mengenrabatt."),
                     ("Was kostet der Versand?", "Ab 1.000 € zzgl. MwSt. liefern wir kostenlos. Ihre genauen Preise und Versandkosten sehen Sie nach dem Anmelden.")]}
    else:  # bs
        prod = "1 proizvod" if n == 1 else (f"{n} proizvoda" if n % 10 in (2, 3, 4) and n % 100 not in (12, 13, 14) else f"{n} proizvoda")
        gen = cat or "sportska ishrana i suplementi"
        t = {"title": f"{nm} veleprodaja | {nm} za poslovne kupce | THH Europe",
             "meta": f"Kupite {nm} na veliko za svoju prodavnicu, teretanu ili web shop. {prod} brenda {nm} po veleprodajnim cijenama, već od 1 komada. Besplatna dostava od 1.000 €.",
             "h1": f"{nm} veleprodaja",
             "intro": f"Tražite {nm} za svoju prodavnicu, teretanu, web shop ili fitness koncept? U THH Europe kupujete {nm} kao poslovni kupac po veleprodajnim cijenama, već od 1 komada. " + (f"Asortiman obuhvata: {cat}." if cat else ""),
             "faq_h": "Česta pitanja",
             "sections": [
                 (f"Šta je {nm}?", [f"{nm} je brend u kategoriji: {gen}. U katalogu THH Europe trenutno se nalazi {prod} brenda {nm}" + (f", među kojima {ex}." if ex else ".") + (f" Ukupno {fl} okusa i varijanti." if fl else "")], None),
                 (f"{nm} na veliko", [f"Želite prodavati {nm}? THH Europe je vaš veleprodajni partner. Naručujete iz našeg vlastitog skladišta i plaćate veleprodajne cijene već od 1 komada."],
                  ["veleprodajne cijene već od 1 komada", "dodatni količinski popust za veće narudžbe", "besplatna dostava od 1.000 € bez PDV-a", "isporuka iz vlastitog skladišta", "stalni menadžer računa za narudžbe i pitanja"]),
             ] + ([(f"Zašto {nm} u vašem asortimanu?", [f"{nm} se uklapa u asortiman za sportiste, fitness kupce i aktivne potrošače. Proizvodi su namijenjeni za:"], moments)] if moments else []) + [
                 (f"{nm} veleprodaja za prodavnice, teretane i web shopove", [f"THH Europe isporučuje {nm} poslovnim kupcima koji žele proširiti svoj asortiman." + (f" U katalogu se nalaze, između ostalog, pakovanja od {units}." if units else ""), f"{nm} je zanimljiv za:"],
                  ["prodavnice suplemenata", "teretane i fitness klubove", "web shopove", "prodavnice zdrave hrane", "studije za personalni trening", "sportske klubove"]),
                 (f"Naručite {nm} kod THH Europe", [f"Prijavite se ili se registrujte kao poslovni kupac da biste vidjeli svoje tačne cijene i naručili {nm}. Proizvode možete dodati i na listu za ponudu i dobiti ponudu po mjeri."], None)],
             "faq": [(f"Šta je {nm}?", f"{nm} je brend u kategoriji: {gen}. THH Europe nudi {prod} brenda {nm}."),
                     (f"Mogu li kupiti {nm} kao poslovni kupac?", f"Da. {nm} je dostupan preko THH Europe za prodavnice, teretane, web shopove i druge preprodavce."),
                     (f"Koji {nm} proizvodi su dostupni?", f"U katalogu se nalazi {prod} brenda {nm}" + (f", među kojima {ex}." if ex else ".")),
                     ("Od koliko komada važe veleprodajne cijene?", "Veleprodajne cijene važe već od 1 komada, uz dodatni količinski popust za veće narudžbe."),
                     ("Koliko košta dostava?", "Dostava je besplatna od 1.000 € bez PDV-a. Tačne cijene i troškove dostave vidite nakon prijave.")]}
    return t


def body_html(t):
    out = []
    for h, paras, items in t["sections"]:
        out.append(f"<h2>{E(h)}</h2>")
        for p in paras:
            out.append(f"<p>{E(p)}</p>")
        if items:
            out.append("<ul>" + "".join(f"<li>{E(i)}</li>" for i in items) + "</ul>")
    out.append("<h2>" + (t.get("faq_h") or ("Veelgestelde vragen" if t["h1"].endswith("groothandel") else "Frequently asked questions")) + "</h2>")
    out.append('<div class="faq">' + "".join(
        f"<details><summary>{E(q)}</summary><p>{E(a)}</p></details>" for q, a in t["faq"]) + "</div>")
    return "\n".join(out)


CSS = """
@font-face{font-family:Pilat;src:url(/assets/fonts/Pilat-Regular.woff) format("woff");font-weight:400}
@font-face{font-family:Pilat;src:url(/assets/fonts/Pilat-Bold.woff) format("woff");font-weight:700}
@font-face{font-family:PilatExt;src:url(/assets/fonts/PilatExtended-Black.woff) format("woff");font-weight:900}
:root{--ink:#1F1F28;--txt:rgba(26,26,26,.78);--line:#E9E9EB;--soft:#f5f5f6;--head:#1B1B1B}
*{box-sizing:border-box}body{margin:0;font-family:Pilat,Arial,sans-serif;color:var(--txt);background:#fff;line-height:1.65}
a{color:var(--ink)}.wrap{max-width:1080px;margin:0 auto;padding:0 20px}
.top{background:var(--head);color:#fff}.top .wrap{display:flex;align-items:center;justify-content:space-between;height:64px}
.logo{display:flex;align-items:center;gap:10px;color:#fff;text-decoration:none}.logo b{background:#fff;color:#000;font-family:PilatExt,Arial;font-weight:900;font-size:13px;padding:3px 6px}
.logo span{font-family:PilatExt,Arial;font-weight:900;font-size:16px;letter-spacing:.02em}
.top nav a{color:#fff;text-decoration:none;font-size:13px;font-weight:700;letter-spacing:.06em;margin-left:22px;text-transform:uppercase}
.crumb{font-size:13px;margin:22px 0 0}.crumb a{text-decoration:none;opacity:.7}
.hero{display:flex;gap:28px;align-items:center;margin:18px 0 8px}
.hero .lg{flex:0 0 150px;height:110px;border:1px solid var(--line);border-radius:10px;display:flex;align-items:center;justify-content:center;background:#fff;padding:14px}
.hero .lg img{max-width:100%;max-height:100%;object-fit:contain}
h1{font-family:PilatExt,Arial;font-weight:900;color:var(--ink);font-size:clamp(28px,4vw,44px);line-height:1.05;margin:0 0 10px;text-transform:uppercase}
h2{font-family:PilatExt,Arial;font-weight:900;color:var(--ink);font-size:20px;margin:34px 0 8px;text-transform:uppercase}
.btns{display:flex;gap:12px;flex-wrap:wrap;margin:16px 0 6px}.btn{display:inline-block;background:#1A1A1A;color:#fff;text-decoration:none;font-weight:700;padding:12px 22px;border-radius:4px;font-size:14px}.btn.sec{background:#fff;color:#1A1A1A;border:1.5px solid #1A1A1A}
.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(165px,1fr));gap:14px;margin:18px 0}
.card{border:1px solid var(--line);border-radius:10px;padding:12px;text-decoration:none;color:var(--ink);display:flex;flex-direction:column;gap:8px;background:#fff}
.card img{width:100%;height:140px;object-fit:contain}.card img.lgi{height:70px}.lgt{font-family:PilatExt,Arial;font-weight:900;color:var(--ink);font-size:15px;text-align:center;line-height:1.2;display:flex;align-items:center;justify-content:center;min-height:70px}.card span{font-size:13px;font-weight:700;line-height:1.3}.card small{font-size:12px;opacity:.65}
ul{padding-left:20px}li{margin:3px 0}
.faq details{border-bottom:1px solid var(--line);padding:12px 0}.faq summary{cursor:pointer;font-weight:700;color:var(--ink)}
.usp{background:var(--soft);border-radius:10px;padding:14px 18px;font-size:14px;margin:24px 0}
footer{background:var(--head);color:#bbb;font-size:13px;margin-top:50px;padding:26px 0}footer a{color:#fff}
@media(max-width:640px){.hero{flex-direction:column;align-items:flex-start}.top nav{display:none}}
"""


# Talen: map op de site, en vaste teksten
LANGS = ["nl", "en", "fr", "de", "bs"]
LDIR = {"nl": "merken", "en": "brands", "fr": "fr/marques", "de": "de/marken", "bs": "bs/brendovi"}
LNAME = {"nl": "Nederlands", "en": "English", "fr": "Français", "de": "Deutsch", "bs": "Bosanski"}
UI = {
    "nl": dict(prod1="product", cat="Catalogus", brands="Merken", join="Word klant", login="Inloggen", all="Bekijk alle {n} producten",
               biz="Word zakelijk klant", usp="Groothandelsprijzen vanaf 1 stuk · Staffelkorting · Gratis levering vanaf € 1.000 · Vaste accountmanager",
               foot="B2B groothandel in sportvoeding", prods="producten", idx_t="Merken groothandel sportvoeding | THH Europe", idx_h="Onze merken",
               idx_i="THH Europe levert {n} merken in sportvoeding en supplementen aan winkels, sportscholen en webshops in de Benelux. Kies een merk voor het assortiment en de voorwaarden."),
    "en": dict(prod1="product", cat="Catalogue", brands="Brands", join="Become a customer", login="Log in", all="View all {n} products",
               biz="Become a business customer", usp="Wholesale prices from 1 unit · Volume discount · Free delivery from € 1,000 · Dedicated account manager",
               foot="B2B sports nutrition wholesale", prods="products", idx_t="Sports nutrition brands wholesale | THH Europe", idx_h="Our brands",
               idx_i="THH Europe supplies {n} sports nutrition and supplement brands to shops, gyms and webshops across the Benelux. Choose a brand to see its range and terms."),
    "fr": dict(prod1="produit", cat="Catalogue", brands="Marques", join="Devenir client", login="Connexion", all="Voir les {n} produits",
               biz="Devenir client professionnel", usp="Prix de gros dès 1 pièce · Remise sur quantité · Livraison gratuite dès 1 000 € · Gestionnaire de compte dédié",
               foot="Grossiste B2B en nutrition sportive", prods="produits", idx_t="Marques de nutrition sportive en gros | THH Europe", idx_h="Nos marques",
               idx_i="THH Europe fournit {n} marques de nutrition sportive et de compléments aux magasins, salles de sport et boutiques en ligne du Benelux. Choisissez une marque pour voir sa gamme et les conditions."),
    "de": dict(prod1="Produkt", cat="Katalog", brands="Marken", join="Kunde werden", login="Anmelden", all="Alle {n} Produkte ansehen",
               biz="Geschäftskunde werden", usp="Großhandelspreise ab 1 Stück · Mengenrabatt · Kostenlose Lieferung ab 1.000 € · Fester Ansprechpartner",
               foot="B2B-Großhandel für Sporternährung", prods="Produkte", idx_t="Marken Sporternährung Großhandel | THH Europe", idx_h="Unsere Marken",
               idx_i="THH Europe liefert {n} Marken für Sporternährung und Nahrungsergänzung an Geschäfte, Fitnessstudios und Onlineshops im Benelux-Raum. Wählen Sie eine Marke für Sortiment und Konditionen."),
    "bs": dict(prod1="proizvod", cat="Katalog", brands="Brendovi", join="Postanite kupac", login="Prijava", all="Pogledajte svih {n} proizvoda",
               biz="Postanite poslovni kupac", usp="Veleprodajne cijene od 1 komada · Količinski popust · Besplatna dostava od 1.000 € · Stalni menadžer računa",
               foot="B2B veleprodaja sportske ishrane", prods="proizvoda", idx_t="Brendovi sportske ishrane na veliko | THH Europe", idx_h="Naši brendovi",
               idx_i="THH Europe isporučuje {n} brendova sportske ishrane i suplemenata prodavnicama, teretanama i web shopovima. Odaberite brend da vidite asortiman i uslove."),
}


def hreflangs(path_of):
    out = "".join(f'<link rel="alternate" hreflang="{l}" href="{path_of(l)}">' for l in LANGS)
    return out + f'<link rel="alternate" hreflang="x-default" href="{path_of("en")}">'


def lang_links(path_of, cur):
    return " · ".join(f'<a href="{path_of(l)}">{LNAME[l]}</a>' for l in LANGS if l != cur)


def page(b, lang, t):
    nm = b["clean"]
    U = UI[lang]
    url_of = lambda l: f"{SITE}/{LDIR[l]}/{b['slug']}/"
    url = url_of(lang)
    base = f"/{LDIR[lang]}/"
    cat_link = f"{SITE}/#catalogus?merk={E(b['name'])}"
    cards = "".join(
        f'<a class="card" href="{SITE}/#product?id={E(p["slug"])}"><img src="{E(img_url(p))}" alt="{E(pname(p) if pname(p).lower().startswith(nm.lower()) else nm + " " + pname(p))}" loading="lazy">'
        f'<span>{E(pname(p))}</span><small>{E(p.get("unit") or "")}</small></a>'
        for p in b["prods"][:12])
    faq_ld = {"@context": "https://schema.org", "@type": "FAQPage", "mainEntity": [
        {"@type": "Question", "name": q, "acceptedAnswer": {"@type": "Answer", "text": a}} for q, a in t["faq"]]}
    bc_ld = {"@context": "https://schema.org", "@type": "BreadcrumbList", "itemListElement": [
        {"@type": "ListItem", "position": 1, "name": "THH Europe", "item": SITE + "/"},
        {"@type": "ListItem", "position": 2, "name": U["brands"], "item": SITE + base},
        {"@type": "ListItem", "position": 3, "name": nm, "item": url}]}
    return f"""<!doctype html>
<html lang="{lang}"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>{E(t['title'])}</title>
<meta name="description" content="{E(t['meta'])}">
<link rel="canonical" href="{url}">
{hreflangs(url_of)}
<meta property="og:title" content="{E(t['title'])}"><meta property="og:description" content="{E(t['meta'])}"><meta property="og:url" content="{url}"><meta property="og:type" content="website">
{('<meta property="og:image" content="' + E(img_url(b["examples"][0])) + '">') if b["examples"] else ''}
<link rel="icon" href="/favicon.ico">
<style>{CSS}</style>
<script type="application/ld+json">{json.dumps(faq_ld, ensure_ascii=False)}</script>
<script type="application/ld+json">{json.dumps(bc_ld, ensure_ascii=False)}</script>
</head><body>
<header class="top"><div class="wrap"><a class="logo" href="/"><b>THHEU</b><span>THH EUROPE</span></a>
<nav><a href="/#catalogus">{U['cat']}</a><a href="{base}">{U['brands']}</a><a href="/#word-klant">{U['join']}</a><a href="/#inloggen">{U['login']}</a></nav></div></header>
<main class="wrap">
<div class="crumb"><a href="/">THH Europe</a> / <a href="{base}">{U['brands']}</a> / {E(nm)}</div>
<div class="hero"><div class="lg">{('<img src="' + E(b['logo']) + '" alt="' + E(nm) + ' logo">') if b.get('logo') else ('<span class="lgt">' + E(nm) + '</span>')}</div>
<div><h1>{E(t['h1'])}</h1><p>{E(t['intro'])}</p>
<div class="btns"><a class="btn" href="{cat_link}">{U['all'].format(n=b['n'])}</a><a class="btn sec" href="/#word-klant">{U['biz']}</a></div></div></div>
<div class="grid">{cards}</div>
{body_html(t)}
<div class="usp">{U['usp']}</div>
<p style="font-size:13px">{lang_links(url_of, lang)}</p>
</main>
<footer><div class="wrap">THH Europe · {U['foot']} · <a href="mailto:info@thheurope.nl">info@thheurope.nl</a> · <a href="/">thheurope.com</a></div></footer>
</body></html>
"""


def index_page(brands, lang):
    U = UI[lang]
    base = f"/{LDIR[lang]}/"
    url_of = lambda l: f"{SITE}/{LDIR[l]}/"
    items = "".join(f'<a class="card" href="{base}{b["slug"]}/">{('<img class="lgi" src="' + E(b['logo']) + '" alt="' + E(b['clean']) + ' logo" loading="lazy">') if b.get('logo') else ('<b class="lgt">' + E(b['clean']) + '</b>')}<span>{E(b["clean"])}</span><small>{b["n"]} {U["prod1"] if b["n"] == 1 else U["prods"]}</small></a>' for b in brands)
    intro = U["idx_i"].format(n=len(brands))
    return f"""<!doctype html><html lang="{lang}"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>{U['idx_t']}</title><meta name="description" content="{E(intro)}"><link rel="canonical" href="{SITE}{base}">
{hreflangs(url_of)}
<style>{CSS}</style></head><body>
<header class="top"><div class="wrap"><a class="logo" href="/"><b>THHEU</b><span>THH EUROPE</span></a><nav><a href="/#catalogus">{U['cat']}</a><a href="/#word-klant">{U['join']}</a><a href="/#inloggen">{U['login']}</a></nav></div></header>
<main class="wrap"><div class="crumb"><a href="/">THH Europe</a> / {U['idx_h']}</div><h1 style="margin-top:18px">{U['idx_h']}</h1><p>{E(intro)}</p>
<div class="grid">{items}</div><p style="font-size:13px">{lang_links(url_of, lang)}</p></main>
<footer><div class="wrap">THH Europe · <a href="mailto:info@thheurope.nl">info@thheurope.nl</a></div></footer></body></html>"""


def main():
    prods = load_products()
    by = collections.defaultdict(list)
    for p in prods:
        b = (p.get("brand") or "").strip()
        if b and clean_brand(b).lower() not in UITGESLOTEN:
            by[b].append(p)
    # zelfde merk met en zonder (R)/(TM) samenvoegen onder de naam met de meeste producten
    groep = collections.defaultdict(list)
    for b in by:
        groep[clean_brand(b).lower()].append(b)
    for namen in groep.values():
        if len(namen) > 1:
            namen.sort(key=lambda x: -len(by[x]))
            for extra in namen[1:]:
                by[namen[0]].extend(by.pop(extra))
    brands = sorted((brand_data(n, ps) for n, ps in by.items()), key=lambda b: b["clean"].lower())
    seen = {}
    for b in brands:  # dubbele slugs voorkomen
        if b["slug"] in seen:
            b["slug"] += "-2"
        seen[b["slug"]] = 1
    teksten = {}
    urls = [SITE + "/"] + [f"{SITE}/{LDIR[l]}/" for l in LANGS]
    for b in brands:
        t = texts(b)
        for lang in LANGS:
            d = os.path.join(ROOT, LDIR[lang], b["slug"])
            os.makedirs(d, exist_ok=True)
            with open(os.path.join(d, "index.html"), "w", encoding="utf-8") as f:
                f.write(page(b, lang, t[lang]))
            urls.append(f"{SITE}/{LDIR[lang]}/{b['slug']}/")
        teksten[b["name"]] = {"slug": b["slug"], "logo": b.get("logo")}
        for lang in LANGS:
            teksten[b["name"]][lang] = {"intro": t[lang]["intro"], "body": body_html(t[lang])}
    for lang in LANGS:
        with open(os.path.join(ROOT, LDIR[lang], "index.html"), "w", encoding="utf-8") as f:
            f.write(index_page(brands, lang))
    # SPA: klein overzicht (naam -> slug, logo) en per taal per merk een los tekstbestand
    with open(os.path.join(ROOT, "merken", "teksten.json"), "w", encoding="utf-8") as f:
        json.dump({k: {"slug": v["slug"], "logo": v["logo"]} for k, v in teksten.items()}, f, ensure_ascii=False, separators=(",", ":"))
    for lang in LANGS:
        d = os.path.join(ROOT, "merken", "t", lang)
        os.makedirs(d, exist_ok=True)
        for v in teksten.values():
            with open(os.path.join(d, v["slug"] + ".json"), "w", encoding="utf-8") as f:
                json.dump(v[lang], f, ensure_ascii=False, separators=(",", ":"))
    today = datetime.date.today().isoformat()
    with open(os.path.join(ROOT, "sitemap.xml"), "w", encoding="utf-8") as f:
        f.write('<?xml version="1.0" encoding="UTF-8"?>\n<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">\n')
        f.write("".join(f"<url><loc>{u}</loc><lastmod>{today}</lastmod></url>\n" for u in urls))
        f.write("</urlset>\n")
    with open(os.path.join(ROOT, "robots.txt"), "w", encoding="utf-8") as f:
        f.write(f"User-agent: *\nDisallow: /beheer.html\nDisallow: /factuur.html\nSitemap: {SITE}/sitemap.xml\n")
    print(len(brands), "merken,", len(urls), "urls")


if __name__ == "__main__":
    main()
