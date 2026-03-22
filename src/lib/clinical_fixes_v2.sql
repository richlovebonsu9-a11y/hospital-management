-- Clinical Fixes V2: Prescription Quantity & Bed Management
-- 1. Add quantity to prescriptions
ALTER TABLE prescriptions ADD COLUMN IF NOT EXISTS quantity INTEGER DEFAULT 1;

-- 2. Enhance Admissions
-- Ensure ward_id exists and links to wards
DO $$ 
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name='admissions' AND column_name='ward_id') THEN
        ALTER TABLE admissions ADD COLUMN ward_id UUID REFERENCES wards(id);
    END IF;
END $$;

ALTER TABLE admissions ADD COLUMN IF NOT EXISTS assigned_by UUID REFERENCES profiles(id);
ALTER TABLE admissions ADD COLUMN IF NOT EXISTS bed_number TEXT; -- Ensure it's there

-- 3. Ensure Wards has required columns (already in upgrade_admission.sql but being safe)
-- total_beds, occupied_beds, admission_fee
DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name='wards' AND column_name='occupied_beds') THEN
        ALTER TABLE wards ADD COLUMN occupied_beds INTEGER DEFAULT 0;
    END IF;
END $$;

-- 4. Set RLS for better access
ALTER TABLE admissions ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS "Anyone authenticated can view admissions" ON admissions;
CREATE POLICY "Anyone authenticated can view admissions" ON admissions FOR SELECT USING (true);
DROP POLICY IF EXISTS "Staff can manage admissions" ON admissions;
CREATE POLICY "Staff can manage admissions" ON admissions FOR ALL TO authenticated USING (auth.jwt() -> 'user_metadata' ->> 'role' IN ('admin', 'doctor', 'nurse'));
