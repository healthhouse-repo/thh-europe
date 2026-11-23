// Shared product options for the quote form (front-end loads the same data from /api/options).
// These are request options, not promises: Eleven Fashion confirms what is possible in the quote.

const PRODUCTS = [
  { id: 'tshirt', name: 'T-shirt', weights: ['90 gsm', '140 gsm', '160 gsm', '180 gsm', '200 gsm', '220 gsm (heavyweight)'], fits: ['Regular', 'Slim', 'Oversized'] },
  { id: 'longsleeve', name: 'Long sleeve', weights: ['160 gsm', '180 gsm', '220 gsm'], fits: ['Regular', 'Oversized'] },
  { id: 'tank', name: 'Tank top', weights: ['120 gsm', '160 gsm', '180 gsm'], fits: ['Regular', 'Racerback'] },
  { id: 'polo', name: 'Polo shirt', weights: ['180 gsm', '200 gsm', '220 gsm'], fits: ['Regular', 'Slim'] },
  { id: 'hoodie', name: 'Hoodie', weights: ['280 gsm', '320 gsm', '380 gsm (heavyweight)'], fits: ['Regular', 'Oversized', 'Zip hoodie'] },
  { id: 'sweater', name: 'Sweater / crewneck', weights: ['280 gsm', '320 gsm', '380 gsm'], fits: ['Regular', 'Oversized'] },
  { id: 'joggers', name: 'Joggers / sweatpants', weights: ['280 gsm', '320 gsm'], fits: ['Regular', 'Tapered'] },
  { id: 'shorts', name: 'Shorts', weights: ['Lightweight', 'Fleece'], fits: ['Training shorts', 'Fight shorts', 'Sweat shorts'] },
  { id: 'performance', name: 'Performance / dry-fit shirt', weights: ['Lightweight', 'Midweight'], fits: ['Regular', 'Slim', 'Compression'] },
  { id: 'jacket', name: 'Jacket / tracksuit', weights: ['Lightweight', 'Midweight'], fits: ['Regular'] },
  { id: 'cap', name: 'Cap / headwear', weights: [], fits: ['Baseball cap', 'Trucker cap', 'Beanie', 'Bucket hat'] },
  { id: 'bag', name: 'Tote bag / gym bag', weights: [], fits: ['Tote bag', 'Drawstring bag', 'Duffel bag'] },
  { id: 'other', name: 'Other (describe)', weights: [], fits: [] },
];

const MATERIALS = ['100% cotton', 'Organic cotton', 'Cotton / polyester blend', '100% polyester (dry-fit)', 'Cotton / elastane', 'Not sure, please advise'];
const COLORS = ['Black', 'White', 'Off-white / cream', 'Heather grey', 'Charcoal', 'Navy', 'Royal blue', 'Red', 'Bordeaux', 'Forest green', 'Olive', 'Sand / beige', 'Brown', 'Pink', 'Custom colour (Pantone)'];
const SIZES = ['XS', 'S', 'M', 'L', 'XL', 'XXL', '3XL'];
const DECORATION = ['Screen print', 'Embroidery', 'DTF / digital transfer', 'Heat transfer', 'Woven label / patch', 'Sublimation (all-over)', 'No decoration (blank)', 'Not sure, please advise'];
const POSITIONS = ['Front chest left', 'Front chest centre', 'Full front', 'Full back', 'Upper back (neck)', 'Left sleeve', 'Right sleeve', 'Inside neck label'];
const EXTRAS = ['Custom neck label', 'Custom hang tag', 'Individual polybag', 'Size sticker', 'Pre-production sample'];

module.exports = { PRODUCTS, MATERIALS, COLORS, SIZES, DECORATION, POSITIONS, EXTRAS };
