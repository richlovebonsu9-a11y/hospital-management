-- FIX PRESCRIPTIONS V3: Consolidate quantity columns and ensure robust fetching
-- 1. Standardize quantity column
DO $$ 
BEGIN
    -- If quantity_requested exists but quantity does not, rename it
    IF EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name='prescriptions' AND column_name='quantity_requested') 
       AND NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name='prescriptions' AND column_name='quantity') THEN
        ALTER TABLE prescriptions RENAME COLUMN quantity_requested TO quantity;
    
    -- If both exist, migrate data from quantity_requested to quantity if quantity is default (1)
    ELSIF EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name='prescriptions' AND column_name='quantity_requested') 
          AND EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name='prescriptions' AND column_name='quantity') THEN
        UPDATE prescriptions SET quantity = quantity_requested WHERE quantity = 1 AND quantity_requested != 1;
        -- ALTER TABLE prescriptions DROP COLUMN quantity_requested; -- Optional, keep for safety for now
    
    -- If neither exists, add quantity
    ELSIF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name='prescriptions' AND column_name='quantity') THEN
        ALTER TABLE prescriptions ADD COLUMN quantity INTEGER DEFAULT 1;
    END IF;
END $$;

-- 2. Ensure patient_id is present and populated
ALTER TABLE prescriptions ADD COLUMN IF NOT EXISTS patient_id UUID REFERENCES profiles(id);
UPDATE prescriptions p
SET patient_id = c.patient_id
FROM consultations c
WHERE p.consultation_id = c.id AND p.patient_id IS NULL;

-- 3. Explicitly allow pharmacists to view prescriptions via RLS (if not service_role)
ALTER TABLE prescriptions ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS "Pharmacists can view all prescriptions" ON prescriptions;
CREATE POLICY "Pharmacists can view all prescriptions" ON prescriptions FOR SELECT 
USING (
    (auth.jwt() -> 'user_metadata' ->> 'role' = 'pharmacist') OR 
    (auth.jwt() -> 'user_metadata' ->> 'role' = 'admin')
);

-- 4. Ensure drug_id is present (for future inventory tracking)
ALTER TABLE prescriptions ADD COLUMN IF NOT EXISTS drug_id UUID REFERENCES drug_inventory(id);
