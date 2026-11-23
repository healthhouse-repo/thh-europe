-- Eleven Fashion portal – database (Supabase / Postgres). Run once in the SQL editor of a new Supabase project.
-- The API uses the service role key server-side; RLS is on without policies, so nothing is readable with the public key.
create table if not exists customers (
  id uuid primary key default gen_random_uuid(),
  email text not null unique,
  name text default '', company text default '', phone text default '', country text default '',
  address jsonb default '{}'::jsonb, vat text default '',
  created_at timestamptz default now()
);
create table if not exists orders (
  id uuid primary key default gen_random_uuid(),
  number text not null unique,
  customer_id uuid not null references customers(id) on delete restrict,
  title text default '',
  status text not null default 'design' check (status in ('design','mockup','production','transit','delivered','cancelled')),
  quote_ref text default '', items jsonb default '[]'::jsonb, currency text default 'EUR',
  mockups jsonb default '[]'::jsonb, mockup_status text check (mockup_status in ('pending','approved','changes')), mockup_feedback text default '',
  carrier text default '', tracking_number text default '', tracking_url text default '', eta date,
  created_at timestamptz default now(), updated_at timestamptz default now()
);
create index if not exists orders_customer on orders(customer_id);
create table if not exists order_events (
  id uuid primary key default gen_random_uuid(),
  order_id uuid not null references orders(id) on delete cascade,
  status text not null, note text default '', created_at timestamptz default now()
);
create index if not exists order_events_order on order_events(order_id);
create table if not exists invoices (
  id uuid primary key default gen_random_uuid(),
  number text not null unique,
  order_id uuid references orders(id) on delete restrict,
  customer_id uuid not null references customers(id) on delete restrict,
  currency text not null default 'EUR', lines jsonb not null default '[]'::jsonb,
  subtotal numeric(12,2) not null default 0, tax_rate numeric(5,2) default 0, tax numeric(12,2) default 0, total numeric(12,2) not null default 0,
  status text not null default 'open' check (status in ('open','paid','void')),
  due_date date, notes text default '', issued_at timestamptz default now(), paid_at timestamptz,
  payment_method text default '', payment_ref text default ''
);
create index if not exists invoices_customer on invoices(customer_id);
create table if not exists quotes (
  reference text primary key, created_at timestamptz default now(), email text, data jsonb not null
);
create table if not exists login_codes (
  email text primary key, code_hash text not null, expires_at timestamptz not null, attempts int default 0
);
alter table customers enable row level security;
alter table orders enable row level security;
alter table order_events enable row level security;
alter table invoices enable row level security;
alter table quotes enable row level security;
alter table login_codes enable row level security;
