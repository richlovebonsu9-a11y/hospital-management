-- FIX: Add patient_id to prescriptions for direct, resilient EMR fetching
ALTER TABLE prescriptions ADD COLUMN IF NOT EXISTS patient_id UUID REFERENCES profiles(id);

-- Optional: Migrate existing records if consultation_id is present
UPDATE prescriptions p
SET patient_id = c.patient_id
FROM consultations c
WHERE p.consultation_id = c.id AND p.patient_id IS NULL;
