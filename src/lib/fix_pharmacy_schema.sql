-- Phase 42: Pharmacy Schema Repair & Backfill

-- 1. Ensure all missing columns exist in prescriptions
ALTER TABLE prescriptions ADD COLUMN IF NOT EXISTS patient_id UUID REFERENCES profiles(id);
ALTER TABLE prescriptions ADD COLUMN IF NOT EXISTS drug_id UUID REFERENCES drug_inventory(id);
ALTER TABLE prescriptions ADD COLUMN IF NOT EXISTS is_ordered BOOLEAN DEFAULT FALSE;
ALTER TABLE prescriptions ADD COLUMN IF NOT EXISTS quantity_requested INTEGER DEFAULT 1;
ALTER TABLE prescriptions ADD COLUMN IF NOT EXISTS dispensed_at TIMESTAMPTZ;
ALTER TABLE prescriptions ADD COLUMN IF NOT EXISTS dispensed_by UUID REFERENCES profiles(id);
ALTER TABLE prescriptions ADD COLUMN IF NOT EXISTS batch_number TEXT;
ALTER TABLE prescriptions ADD COLUMN IF NOT EXISTS dispense_notes TEXT;

-- 2. Backfill patient_id for existing prescriptions using consultation links
UPDATE prescriptions p
SET patient_id = c.patient_id
FROM consultations c
WHERE p.consultation_id = c.id
AND p.patient_id IS NULL;

-- 3. Verify RLS (Service key is used anyway, but good practice)
ALTER TABLE prescriptions ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS "Pharmacists can view all prescriptions" ON prescriptions;
CREATE POLICY "Pharmacists can view all prescriptions" ON prescriptions FOR SELECT 
USING (auth.jwt() -> 'user_metadata' ->> 'role' = 'pharmacist');

-- 4. Ensure recommend_admission exists in consultations
ALTER TABLE consultations ADD COLUMN IF NOT EXISTS recommend_admission TEXT DEFAULT 'no';
