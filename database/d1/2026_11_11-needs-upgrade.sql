-- Bring an EXISTING nearfind D1 up to the schema the app now reads.
--
-- `deploy --mode 3 --yes` skips `dist/schema.sql` on a database that already
-- has tables, so the columns added by
-- database/migrations/2026_11_11_000000_add_location_and_detail_columns_to_needs.php
-- never reached production. This file applies exactly those changes, plus the
-- six example needs that used to live in config/needs.php.
--
-- Idempotent by construction: SQLite has no `ADD COLUMN IF NOT EXISTS`, so each
-- ALTER is written to fail harmlessly if the column is already there (wrangler
-- keeps going with the other statements). Re-running is safe; the seed deletes
-- its own titles first.

ALTER TABLE needs ADD COLUMN marker_label varchar;
ALTER TABLE needs ADD COLUMN description text;
ALTER TABLE needs ADD COLUMN area varchar;
ALTER TABLE needs ADD COLUMN latitude numeric;
ALTER TABLE needs ADD COLUMN longitude numeric;
ALTER TABLE needs ADD COLUMN distance_km numeric;
ALTER TABLE needs ADD COLUMN supporters integer NOT NULL DEFAULT 0;
ALTER TABLE needs ADD COLUMN opportunity tinyint(1) NOT NULL DEFAULT 0;

CREATE INDEX IF NOT EXISTS needs_category_index ON needs (category);
CREATE INDEX IF NOT EXISTS needs_created_at_index ON needs (created_at);

-- The examples need an owner. Mirrors NeedSeeder: the deployment account when
-- the row exists, otherwise a placeholder "Warga Sukajadi".
INSERT INTO profiles (email, name, username, created_at, updated_at)
SELECT 'warga@nearfind.local', 'Warga Sukajadi', 'warga_sukajadi', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
WHERE NOT EXISTS (SELECT 1 FROM profiles WHERE username = 'warga_sukajadi');

-- The example rows live in 2026_11_11-needs-seed.sql (SQLite caps a
-- compound SELECT, so each INSERT is its own statement).
