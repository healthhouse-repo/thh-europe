// Shared product options for the quote form (front-end loads the same data from /api/options).
// These are request options, not promises: Eleven Fashion confirms what is possible in the quote.

// Fabric weight in gsm (grams per m²): presets, slider range and weight classes per product.
// Classes follow common industry ranges (e.g. T-shirt light ≤150, mid 150–180, heavy 180+; hoodie light <280, mid 280–350, heavy 350+).
const TEE_CLASSES = [[149, 'Lightweight', 'Thin and airy. Good for promo shirts, events and hot climates; budget friendly.'],
  [180, 'Midweight', 'The everyday standard: soft, not see-through, prints well. Most retail and team T-shirts.'],
  [239, 'Heavyweight', 'Thicker and more durable with a premium feel and structured fit. Popular for merch and streetwear.'],
  [999, 'Super heavyweight', 'Very thick, boxy streetwear quality. Holds its shape, warmer to wear.']];
const FLEECE_CLASSES = [[279, 'Lightweight', 'Thin French terry. Comfortable for warm climates, layering and sportswear.'],
  [350, 'Midweight', 'The classic all-season quality. Soft, warm enough for most of the year, great for print and embroidery.'],
  [429, 'Heavyweight', 'Thick, warm and structured. Premium merch and streetwear look; heavier to ship.'],
  [999, 'Super heavyweight', 'Very thick loopback or brushed fleece with a boxy, luxury feel. For cold climates and premium brands.']];
const SPORT_CLASSES = [[139, 'Lightweight', 'Very light and breathable, dries fast. Running, matches and hot climates.'],
  [180, 'Midweight', 'Balanced between breathability and durability. Training and teamwear.'],
  [999, 'Heavyweight', 'Sturdier fabric that lasts longer. Warm-ups and casual wear.']];
const POLO_CLASSES = [[199, 'Lightweight', 'Light piqué, cool to wear. Events and hot climates.'],
  [229, 'Midweight', 'Standard corporate and team polo quality.'],
  [999, 'Heavyweight', 'Thick, premium piqué that keeps its shape.']];
const BAG_CLASSES = [[179, 'Light canvas', 'Thin cotton for give-aways and events.'],
  [279, 'Medium canvas', 'Sturdy everyday tote bag.'],
  [999, 'Heavy canvas', 'Thick, premium canvas that stands up by itself.']];
const fab = (min, max, step, presets, classes, knits, def) => ({ min, max, step, presets, classes, knits, def });

const PRODUCTS = [
  { id: 'tshirt', name: 'T-shirt', fabric: fab(90, 300, 10, [90, 120, 140, 160, 180, 200, 220, 240, 260, 280], TEE_CLASSES, ['Single jersey', 'Combed cotton jersey', 'Slub jersey', 'Interlock', 'Pique']), fits: ['Regular', 'Slim', 'Oversized', 'Boxy'] },
  { id: 'longsleeve', name: 'Long sleeve', fabric: fab(140, 300, 10, [140, 160, 180, 200, 220, 240, 260], TEE_CLASSES, ['Single jersey', 'Combed cotton jersey', 'Waffle knit', 'Interlock']), fits: ['Regular', 'Oversized'] },
  { id: 'tank', name: 'Tank top', fabric: fab(110, 220, 10, [120, 140, 160, 180, 200], TEE_CLASSES, ['Single jersey', 'Rib knit', 'Mesh']), fits: ['Regular', 'Racerback', 'Muscle tank'] },
  { id: 'polo', name: 'Polo shirt', fabric: fab(160, 280, 10, [180, 200, 220, 240, 260], POLO_CLASSES, ['Pique', 'Jersey', 'Dry-fit pique']), fits: ['Regular', 'Slim'] },
  { id: 'hoodie', name: 'Hoodie', fabric: fab(220, 550, 10, [240, 280, 300, 320, 350, 380, 400, 450, 500], FLEECE_CLASSES, ['French terry (looped inside)', 'Brushed fleece (soft inside)', 'Loopback (heavy terry)']), fits: ['Regular', 'Oversized', 'Boxy', 'Zip hoodie'] },
  { id: 'sweater', name: 'Sweater / crewneck', fabric: fab(220, 550, 10, [240, 280, 300, 320, 350, 380, 400, 450], FLEECE_CLASSES, ['French terry (looped inside)', 'Brushed fleece (soft inside)', 'Loopback (heavy terry)']), fits: ['Regular', 'Oversized', 'Boxy'] },
  { id: 'joggers', name: 'Joggers / sweatpants', fabric: fab(220, 500, 10, [240, 280, 300, 320, 350, 400], FLEECE_CLASSES, ['French terry (looped inside)', 'Brushed fleece (soft inside)', 'Loopback (heavy terry)']), fits: ['Regular', 'Tapered', 'Wide leg'] },
  { id: 'shorts', name: 'Shorts', fabric: fab(100, 400, 10, [120, 140, 160, 240, 280, 320], SPORT_CLASSES, ['Polyester (training)', 'Polyester mesh', 'Stretch woven (fight shorts)', 'French terry (sweat shorts)', 'Brushed fleece (sweat shorts)']), fits: ['Training shorts', 'Fight shorts', 'Sweat shorts'] },
  { id: 'performance', name: 'Performance / dry-fit shirt', fabric: fab(100, 220, 10, [110, 130, 140, 160, 180, 200], SPORT_CLASSES, ['Interlock', 'Mesh', 'Bird-eye', 'Recycled polyester']), fits: ['Regular', 'Slim', 'Compression'] },
  { id: 'jacket', name: 'Jacket / tracksuit', fabric: fab(150, 450, 10, [180, 220, 260, 300, 350], SPORT_CLASSES, ['Tricot (tracksuit)', 'Softshell', 'Nylon / windbreaker', 'Padded']), fits: ['Regular'] },
  { id: 'cap', name: 'Cap / headwear', fabric: null, fits: ['Baseball cap', 'Trucker cap', 'Beanie', 'Bucket hat'] },
  { id: 'bag', name: 'Tote bag / gym bag', fabric: fab(140, 400, 10, [140, 180, 220, 280, 340], BAG_CLASSES, ['Cotton canvas', 'Recycled cotton', 'Polyester', 'Jute']), fits: ['Tote bag', 'Drawstring bag', 'Duffel bag'] },
  { id: 'other', name: 'Other (describe)', fabric: null, fits: [] },
];
const DEF = { tshirt: 180, longsleeve: 200, tank: 160, polo: 220, hoodie: 300, sweater: 300, joggers: 300, shorts: 140, performance: 140, jacket: 260, bag: 220 };
PRODUCTS.forEach((p) => { if (p.fabric) p.fabric.def = DEF[p.id]; p.weights = p.fabric ? p.fabric.presets.map((g) => g + ' gsm') : []; });

const MATERIALS = ['100% cotton', 'Organic cotton', 'Cotton / polyester blend', '100% polyester (dry-fit)', 'Cotton / elastane', 'Not sure, please advise'];
const COLORS = ['Black', 'White', 'Off-white / cream', 'Heather grey', 'Charcoal', 'Navy', 'Royal blue', 'Red', 'Bordeaux', 'Forest green', 'Olive', 'Sand / beige', 'Brown', 'Pink', 'Custom colour (Pantone)'];
const SIZES = ['XS', 'S', 'M', 'L', 'XL', 'XXL', '3XL'];
const DECORATION = ['Screen print', 'Embroidery', 'DTF / digital transfer', 'Heat transfer', 'Woven label / patch', 'Sublimation (all-over)', 'No decoration (blank)', 'Not sure, please advise'];
const POSITIONS = ['Front chest left', 'Front chest centre', 'Full front', 'Full back', 'Upper back (neck)', 'Left sleeve', 'Right sleeve', 'Inside neck label'];
const EXTRAS = ['Custom neck label', 'Custom hang tag', 'Individual polybag', 'Size sticker', 'Pre-production sample'];

module.exports = { PRODUCTS, MATERIALS, COLORS, SIZES, DECORATION, POSITIONS, EXTRAS };
