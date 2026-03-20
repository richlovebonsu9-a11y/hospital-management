-- GGHMS Database Schema (Supabase / PostgreSQL)

-- 1. Profiles (Extends Supabase Auth users)
CREATE TABLE profiles (
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
CREATE POLICY "Public profiles can be viewed by authenticated users." ON profiles FOR SELECT USING (auth.role() = 'authenticated');
CREATE POLICY "Users can edit their own profile." ON profiles FOR UPDATE USING (auth.uid() = id);

-- 2. Guardians relationship
CREATE TABLE guardians (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    patient_id UUID REFERENCES profiles(id) ON DELETE CASCADE,
    guardian_id UUID REFERENCES profiles(id) ON DELETE CASCADE,
    relationship TEXT,
    is_primary BOOLEAN DEFAULT true,
    created_at TIMESTAMPTZ DEFAULT NOW()
);

-- 3. Appointments
CREATE TABLE appointments (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    patient_id UUID REFERENCES profiles(id),
    doctor_id UUID REFERENCES profiles(id),
    appointment_date TIMESTAMPTZ NOT NULL,
    status TEXT DEFAULT 'scheduled' CHECK (status IN ('scheduled', 'confirmed', 'cancelled', 'completed')),
    reason TEXT,
    created_at TIMESTAMPTZ DEFAULT NOW()
);

-- 4. Emergencies
CREATE TABLE emergencies (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    reporter_id UUID REFERENCES profiles(id),
    symptoms TEXT,
    severity TEXT CHECK (severity IN ('low', 'medium', 'high', 'critical')),
    ghana_post_gps TEXT NOT NULL,
    status TEXT DEFAULT 'pending' CHECK (status IN ('pending', 'dispatched', 'on_site', 'resolved')),
    created_at TIMESTAMPTZ DEFAULT NOW()
);

-- 5. Admissions
CREATE TABLE admissions (
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
CREATE TABLE consultations (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    patient_id UUID REFERENCES profiles(id),
    doctor_id UUID REFERENCES profiles(id),
    notes TEXT,
    vitals JSONB,
    created_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE TABLE prescriptions (
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
CREATE TABLE pharmacy_inventory (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    item_name TEXT NOT NULL,
    stock_quantity INTEGER DEFAULT 0,
    expiry_date DATE,
    unit_price DECIMAL(10, 2),
    created_at TIMESTAMPTZ DEFAULT NOW()
);

-- 8. Invoices & Payments
CREATE TABLE invoices (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    patient_id UUID REFERENCES profiles(id),
    total_amount DECIMAL(10, 2) NOT NULL,
    status TEXT DEFAULT 'unpaid' CHECK (status IN ('unpaid', 'paid', 'partially_paid')),
    nhis_note TEXT,
    created_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE TABLE invoice_items (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    invoice_id UUID REFERENCES invoices(id) ON DELETE CASCADE,
    description TEXT NOT NULL,
    quantity INTEGER DEFAULT 1,
    unit_price DECIMAL(10, 2) NOT NULL,
    amount DECIMAL(10, 2) NOT NULL
);
