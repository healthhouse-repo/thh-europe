# Eleven Fashion – design, quote and order portal

Static pages in `public/` (built from `scripts/build.py` + `src/`) and serverless API routes in `api/` on Vercel.

Pages: `/` home · `/design` designer + quote request · `/services` · `/about` · `/process` · `/contact` · `/account` customer area · `/invoice` printable invoice · `/admin` Eleven team.

Customer area: login with a one-time e-mail code, orders with tracker Design → Mockup approval → In progress → In transit → Delivered, mockup approval / change requests, tracking, invoices (view / print to PDF) and online payment.

## Without configuration
Runs as a demo on sample data (clearly labelled). Quote requests need Blob or the database.

## Environment variables (Vercel → Settings → Environment Variables)
| Variable | Purpose |
|---|---|
| `SUPABASE_URL`, `SUPABASE_SERVICE_ROLE_KEY` | Database (run `db/schema.sql` once). Switches the portal from demo to live. |
| `SESSION_SECRET` | Long random string to sign login cookies (required live). |
| `ADMIN_PASSWORD` | Password for `/admin`. |
| `BLOB_READ_WRITE_TOKEN` | Vercel Blob for logo, mockup and artwork uploads. |
| `RESEND_API_KEY`, `QUOTE_FROM`, `QUOTE_TO` | E-mail: login codes, notifications to customers, alerts to the team. |
| `SITE_URL` | Public URL, e.g. https://design.elevenfashion.co.th |
| `STRIPE_SECRET_KEY`, `STRIPE_WEBHOOK_SECRET` | Stripe Checkout (cards, Apple/Google Pay, PromptPay, TrueMoney, LINE Pay, Alipay, WeChat Pay). Webhook: `/api/webhook?p=stripe`, events `checkout.session.completed` and `checkout.session.async_payment_succeeded`. |
| `MOLLIE_API_KEY` | Optional, iDEAL / Bancontact / SEPA. Needs an EU-registered business. Webhook is set per payment automatically. |
| `BANK_DETAILS` | Bank transfer details shown on invoices (use `\n` for new lines). |
| `SELLER_NAME`, `SELLER_ADDRESS`, `SELLER_TAX_ID` | Seller details on invoices. |
