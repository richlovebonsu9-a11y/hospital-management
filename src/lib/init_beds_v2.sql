-- INITIALIZE BEDS TABLE & POPULATE DATA
-- This script creates a structured 'beds' table to replace raw text entry.

-- 1. Create beds table
CREATE TABLE IF NOT EXISTS beds (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    ward_id UUID REFERENCES wards(id) ON DELETE CASCADE,
    bed_number TEXT NOT NULL,
    status TEXT DEFAULT 'available' CHECK (status IN ('available', 'occupied', 'maintenance')),
    last_occupied_at TIMESTAMPTZ,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    UNIQUE(ward_id, bed_number)
);

-- 2. Populate beds for existing wards using generate_series
-- ICU
INSERT INTO beds (ward_id, bed_number)
SELECT id, 'ICU-' || lpad(s.n::text, 2, '0')
FROM wards, generate_series(1, 20) AS s(n)
WHERE ward_name = 'ICU'
ON CONFLICT DO NOTHING;

-- Maternity
INSERT INTO beds (ward_id, bed_number)
SELECT id, 'MAT-' || lpad(s.n::text, 2, '0')
FROM wards, generate_series(1, 40) AS s(n)
WHERE ward_name = 'Maternity'
ON CONFLICT DO NOTHING;

-- General Ward
INSERT INTO beds (ward_id, bed_number)
SELECT id, 'GEN-' || lpad(s.n::text, 3, '0')
FROM wards, generate_series(1, 100) AS s(n)
WHERE ward_name = 'General Ward'
ON CONFLICT DO NOTHING;

-- Pediatric Ward
INSERT INTO beds (ward_id, bed_number)
SELECT id, 'PED-' || lpad(s.n::text, 2, '0')
FROM wards, generate_series(1, 30) AS s(n)
WHERE ward_name = 'Pediatric Ward'
ON CONFLICT DO NOTHING;

-- 3. Enable RLS
ALTER TABLE beds ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS "Beds are viewable by authenticated users" ON beds;
CREATE POLICY "Beds are viewable by authenticated users" ON beds FOR SELECT USING (true);
DROP POLICY IF EXISTS "Admins can manage beds" ON beds;
CREATE POLICY "Admins can manage beds" ON beds FOR ALL TO authenticated USING (auth.jwt() -> 'user_metadata' ->> 'role' = 'admin');
