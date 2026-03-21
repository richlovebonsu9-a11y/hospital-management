-- Migration: Add Department and Assignment fields
-- 1. Add department to profiles if missing
DO $$ 
BEGIN 
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name='profiles' AND column_name='department') THEN
        ALTER TABLE profiles ADD COLUMN department TEXT;
    END IF;
END $$;

-- 2. Add department and assigned_to to appointments
DO $$ 
BEGIN 
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name='appointments' AND column_name='department') THEN
        ALTER TABLE appointments ADD COLUMN department TEXT;
    END IF;

    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name='appointments' AND column_name='assigned_to') THEN
        ALTER TABLE appointments ADD COLUMN assigned_to UUID REFERENCES profiles(id);
    END IF;
END $$;

-- 3. Reload schema
NOTIFY pgrst, 'reload schema';
