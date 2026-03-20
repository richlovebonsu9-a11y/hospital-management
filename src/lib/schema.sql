-- GGHMS Database Schema (Supabase / PostgreSQL)

-- 1. Profiles (Extends Supabase Auth users)
CREATE TABLE IF NOT EXISTS profiles (
    id UUID REFERENCES auth.users ON DELETE CASCADE PRIMARY KEY,
    name TEXT NOT NULL,
    role TEXT NOT NULL CHECK (role IN ('patient', 'guardian', 'doctor', 'nurse', 'pharmacist', 'technician', 'admin')),
    phone TEXT,
    dob DATE,
    gender TEXT,
    ghana_card TEXT,
    nhis_membership_number TEXT,
    ghana_post_gps TEXT NOT NULL,
    allergies TEXT,
    blood_group TEXT,
    chronic_conditions TEXT,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

-- RLS for profiles
ALTER TABLE profiles ENABLE ROW LEVEL SECURITY;

DO $$ 
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_policies WHERE policyname = 'Public profiles can be viewed by authenticated users.' AND tablename = 'profiles') THEN
        CREATE POLICY "Public profiles can be viewed by authenticated users." ON profiles FOR SELECT USING (auth.role() = 'authenticated');
    END IF;

    IF NOT EXISTS (SELECT 1 FROM pg_policies WHERE policyname = 'Users can edit their own profile.' AND tablename = 'profiles') THEN
        CREATE POLICY "Users can edit their own profile." ON profiles FOR UPDATE USING (auth.uid() = id);
    END IF;
END $$;

-- 2. Guardians relationship
CREATE TABLE IF NOT EXISTS guardians (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    patient_id UUID REFERENCES profiles(id) ON DELETE CASCADE,
    guardian_id UUID REFERENCES profiles(id) ON DELETE CASCADE,
    relationship TEXT,
    is_primary BOOLEAN DEFAULT true,
    status TEXT DEFAULT 'pending', -- pending, approved, rejected
    created_at TIMESTAMPTZ DEFAULT NOW()
);

-- RLS for Guardians
ALTER TABLE guardians ENABLE ROW LEVEL SECURITY;
CREATE POLICY "Admins can manage all guardian links" ON guardians FOR ALL TO authenticated USING (auth.jwt() -> 'user_metadata' ->> 'role' = 'admin');
CREATE POLICY "Users can view their own guardian links" ON guardians FOR SELECT TO authenticated USING (auth.uid() = patient_id OR auth.uid() = guardian_id);
CREATE POLICY "Guardians can create links" ON guardians FOR INSERT TO authenticated WITH CHECK (auth.uid() = guardian_id);
CREATE POLICY "Patients can update their links" ON guardians FOR UPDATE TO authenticated USING (auth.uid() = patient_id OR auth.uid() = guardian_id);

-- 3. Appointments
CREATE TABLE IF NOT EXISTS appointments (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    patient_id UUID REFERENCES profiles(id),
    doctor_id UUID REFERENCES profiles(id),
    appointment_date TIMESTAMPTZ NOT NULL,
    status TEXT DEFAULT 'scheduled' CHECK (status IN ('scheduled', 'confirmed', 'cancelled', 'completed')),
    reason TEXT,
    created_at TIMESTAMPTZ DEFAULT NOW()
);

-- 4. Emergencies
CREATE TABLE IF NOT EXISTS emergencies (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    reporter_id UUID REFERENCES profiles(id),
    symptoms TEXT,
    severity TEXT CHECK (severity IN ('low', 'medium', 'high', 'critical')),
    ghana_post_gps TEXT NOT NULL,
    status TEXT DEFAULT 'pending' CHECK (status IN ('pending', 'dispatched', 'on_site', 'resolved')),
    created_at TIMESTAMPTZ DEFAULT NOW()
);

-- 5. Admissions
CREATE TABLE IF NOT EXISTS admissions (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    patient_id UUID REFERENCES profiles(id),
    ward TEXT NOT NULL,
    bed_number TEXT NOT NULL,
    admission_date TIMESTAMPTZ DEFAULT NOW(),
    discharge_date TIMESTAMPTZ,
    status TEXT DEFAULT 'active' CHECK (status IN ('active', 'discharged')),
    created_at TIMESTAMPTZ DEFAULT NOW()
);

-- 6. Consultations & Prescriptions
CREATE TABLE IF NOT EXISTS consultations (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    patient_id UUID REFERENCES profiles(id),
    doctor_id UUID REFERENCES profiles(id),
    notes TEXT,
    vitals JSONB,
    created_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS prescriptions (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    consultation_id UUID REFERENCES consultations(id),
    medication_name TEXT NOT NULL,
    dosage TEXT NOT NULL,
    frequency TEXT,
    duration TEXT,
    status TEXT DEFAULT 'pending' CHECK (status IN ('pending', 'dispensed')),
    created_at TIMESTAMPTZ DEFAULT NOW()
);

-- 7. Pharmacy Inventory
CREATE TABLE IF NOT EXISTS pharmacy_inventory (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    item_name TEXT NOT NULL,
    stock_quantity INTEGER DEFAULT 0,
    expiry_date DATE,
    unit_price DECIMAL(10, 2),
    created_at TIMESTAMPTZ DEFAULT NOW()
);

-- 8. Invoices & Payments
CREATE TABLE IF NOT EXISTS invoices (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    patient_id UUID REFERENCES profiles(id),
    total_amount DECIMAL(10, 2) NOT NULL,
    status TEXT DEFAULT 'unpaid' CHECK (status IN ('unpaid', 'paid', 'partially_paid')),
    nhis_note TEXT,
    created_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS invoice_items (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    invoice_id UUID REFERENCES invoices(id) ON DELETE CASCADE,
    description TEXT NOT NULL,
    quantity INTEGER DEFAULT 1,
    unit_price DECIMAL(10, 2) NOT NULL,
    amount DECIMAL(10, 2) NOT NULL
);

-- 9. Vitals (Observed by Nurses/Doctors)
CREATE TABLE IF NOT EXISTS vitals (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    patient_id UUID REFERENCES profiles(id) ON DELETE CASCADE,
    recorded_by UUID REFERENCES profiles(id),
    temperature NUMERIC,
    blood_pressure TEXT,
    weight NUMERIC,
    pulse INTEGER,
    recorded_at TIMESTAMPTZ DEFAULT NOW()
);

-- 10. Lab & Radiology Requests
CREATE TABLE IF NOT EXISTS lab_requests (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    patient_id UUID REFERENCES profiles(id) ON DELETE CASCADE,
    doctor_id UUID REFERENCES profiles(id),
    test_type TEXT NOT NULL,
    test_name TEXT NOT NULL,
    result_text TEXT,
    status TEXT DEFAULT 'pending' CHECK (status IN ('pending', 'completed')),
    created_at TIMESTAMPTZ DEFAULT NOW()
);

-- 11. System Notifications
CREATE TABLE IF NOT EXISTS notifications (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID REFERENCES profiles(id) ON DELETE CASCADE,
    message TEXT NOT NULL,
    is_read BOOLEAN DEFAULT false,
    created_at TIMESTAMPTZ DEFAULT NOW()
);

-- 12. Security Audit Log
CREATE TABLE IF NOT EXISTS audit_log (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID REFERENCES profiles(id),
    action TEXT NOT NULL,
    details TEXT,
    created_at TIMESTAMPTZ DEFAULT NOW()
);

-- 13. Drug Inventory (Detailed)
CREATE TABLE IF NOT EXISTS drug_inventory (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    drug_name TEXT NOT NULL,
    stock_count INTEGER DEFAULT 0,
    expiry_date DATE,
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

-- Enable RLS for all new tables
ALTER TABLE vitals ENABLE ROW LEVEL SECURITY;
ALTER TABLE lab_requests ENABLE ROW LEVEL SECURITY;
ALTER TABLE notifications ENABLE ROW LEVEL SECURITY;
ALTER TABLE audit_log ENABLE ROW LEVEL SECURITY;
ALTER TABLE drug_inventory ENABLE ROW LEVEL SECURITY;
