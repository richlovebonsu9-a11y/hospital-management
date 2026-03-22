-- FIX ADMISSIONS SCHEMA V2: Ensure all columns for proper bed management
-- This script handles the transition from 'ward' (text) to 'ward_id' (UUID) and adds metadata.

DO $$ 
BEGIN
    -- 1. Ensure ward_id exists as a UUID referencing wards
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name='admissions' AND column_name='ward_id') THEN
        ALTER TABLE admissions ADD COLUMN ward_id UUID REFERENCES wards(id);
    END IF;

    -- 2. Ensure assigned_by exists
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name='admissions' AND column_name='assigned_by') THEN
        ALTER TABLE admissions ADD COLUMN assigned_by UUID REFERENCES profiles(id);
    END IF;

    -- 3. Ensure bed_number is present and nullable
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name='admissions' AND column_name='bed_number') THEN
        ALTER TABLE admissions ADD COLUMN bed_number TEXT;
    ELSE
        ALTER TABLE admissions ALTER COLUMN bed_number DROP NOT NULL;
    END IF;

    -- 4. Handle legacy 'ward' (text) column if it exists
    -- Migration: If 'ward_id' is null and 'ward' (text) exists, try to map it back to a ward ID if ward_name matches
    IF EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name='admissions' AND column_name='ward') THEN
        -- IMPORTANT: Make the old 'ward' column nullable so it doesn't block inserts that only use 'ward_id'
        ALTER TABLE admissions ALTER COLUMN ward DROP NOT NULL;

        UPDATE admissions a
        SET ward_id = w.id
        FROM wards w
        WHERE a.ward = w.ward_name AND a.ward_id IS NULL;
        
        -- Optional: ALTER TABLE admissions DROP COLUMN ward; 
    END IF;
END $$;

-- Enable RLS and add policies
ALTER TABLE admissions ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS "Admins can manage admissions" ON admissions;
CREATE POLICY "Admins can manage admissions" ON admissions FOR ALL 
USING (auth.jwt() -> 'user_metadata' ->> 'role' = 'admin');

DROP POLICY IF EXISTS "Staff can view active admissions" ON admissions;
CREATE POLICY "Staff can view active admissions" ON admissions FOR SELECT
USING (auth.jwt() -> 'user_metadata' ->> 'role' IN ('doctor', 'nurse', 'pharmacist', 'admin'));

DROP POLICY IF EXISTS "Patients can view their own admissions" ON admissions;
CREATE POLICY "Patients can view their own admissions" ON admissions FOR SELECT
USING (auth.uid() = patient_id);
