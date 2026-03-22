-- Phase 40: Ward & Admission Management

-- 1. Create Wards table
CREATE TABLE IF NOT EXISTS wards (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    ward_name TEXT NOT NULL UNIQUE,
    total_beds INTEGER NOT NULL,
    occupied_beds INTEGER DEFAULT 0,
    admission_fee DECIMAL(10, 2) DEFAULT 0.00,
    created_at TIMESTAMPTZ DEFAULT NOW()
);

-- 2. Initialize Wards
INSERT INTO wards (ward_name, total_beds, occupied_beds, admission_fee)
VALUES 
('ICU', 20, 18, 500.00),
('Maternity', 40, 32, 200.00),
('General Ward', 100, 45, 100.00),
('Pediatric Ward', 30, 12, 150.00)
ON CONFLICT (ward_name) DO NOTHING;

-- 3. Update consultations to support admission flag
ALTER TABLE consultations ADD COLUMN IF NOT EXISTS recommend_admission TEXT DEFAULT 'no';

-- 4. Update notifications to support links
ALTER TABLE notifications ADD COLUMN IF NOT EXISTS type TEXT DEFAULT 'general';
ALTER TABLE notifications ADD COLUMN IF NOT EXISTS related_id UUID; -- e.g. consultation_id

-- 4. Ensure admissions table exists (it was in schema.sql but let's be sure)
CREATE TABLE IF NOT EXISTS admissions (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    patient_id UUID REFERENCES profiles(id),
    ward_id UUID REFERENCES wards(id),
    bed_number TEXT,
    admission_date TIMESTAMPTZ DEFAULT NOW(),
    discharge_date TIMESTAMPTZ,
    status TEXT DEFAULT 'active' CHECK (status IN ('active', 'discharged')),
    created_at TIMESTAMPTZ DEFAULT NOW()
);

-- Enable RLS
ALTER TABLE wards ENABLE ROW LEVEL SECURITY;
CREATE POLICY "Wards are viewable by all authenticated users" ON wards FOR SELECT USING (true);
CREATE POLICY "Admins can manage wards" ON wards FOR ALL TO authenticated USING (auth.jwt() -> 'user_metadata' ->> 'role' = 'admin');
