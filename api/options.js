const catalog = require('../lib/catalog');

module.exports = (req, res) => {
  res.setHeader('Cache-Control', 'public, max-age=300');
  res.status(200).json(catalog);
};
