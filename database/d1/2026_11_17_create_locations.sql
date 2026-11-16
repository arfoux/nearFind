-- The D1 companion to database/migrations/2026_11_17_000000_create_locations_table.php.
-- Applied on deploy to an existing database; no example rows are inserted.
-- Idempotent: CREATE TABLE IF NOT EXISTS leaves an existing table untouched.

CREATE TABLE IF NOT EXISTS locations (
  id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
  name varchar NOT NULL,
  category varchar NOT NULL,
  address varchar,
  area varchar,
  latitude numeric NOT NULL,
  longitude numeric NOT NULL,
  source varchar NOT NULL DEFAULT 'warga',
  author_email varchar NOT NULL,
  created_at timestamp,
  updated_at timestamp
);

CREATE INDEX IF NOT EXISTS locations_name_index ON locations (name);
CREATE INDEX IF NOT EXISTS locations_lat_lng_index ON locations (latitude, longitude);
