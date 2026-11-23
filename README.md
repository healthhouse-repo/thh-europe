# Eleven Fashion – quote portal

Public showroom and quote request form for Eleven Fashion Co., Ltd. (Bangkok).
Customers choose products (fabric weight, fit, material, colours, sizes, quantity, decoration, extras),
upload their logo and send a quote request. The Eleven team sees requests at `/admin`.

Vercel project: `eleven-fashion-quote`. Static files in `public/`, serverless functions in `api/`.

Environment variables (set in Vercel, never in this repo):
- `BLOB_READ_WRITE_TOKEN` – Vercel Blob store for logos and quote requests (required)
- `ADMIN_PASSWORD` – password for `/admin` (required for the admin list)
- `RESEND_API_KEY`, `QUOTE_TO`, `QUOTE_FROM` – optional e-mail notification of new requests

Product options live in `lib/catalog.js`. No prices are shown; Eleven confirms options and price in the quote.
