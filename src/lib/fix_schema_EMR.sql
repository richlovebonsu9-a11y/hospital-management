-- Phase 21: Staff Attribution for Clinical Records
ALTER TABLE lab_requests ADD COLUMN IF NOT EXISTS completed_by UUID REFERENCES profiles(id);
ALTER TABLE vitals ADD COLUMN IF NOT EXISTS recorded_by UUID REFERENCES profiles(id); -- Already exists in schema but double checking
ALTER TABLE prescriptions ADD COLUMN IF NOT EXISTS dispensed_by UUID REFERENCES profiles(id);

-- Ensure all policies allow retrieval of staff names for authenticated users
-- (Usually covered by the public profiles policy)
