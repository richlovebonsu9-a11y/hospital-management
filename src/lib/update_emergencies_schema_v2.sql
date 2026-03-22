-- UPDATE EMERGENCIES SCHEMA V2: Support Assignment and Dispatch
-- Adds tracking for assigned staff and dispatch types (Rider, Team, Ambulance)

DO $$ 
BEGIN
    -- 1. Add assigned_to (Staff member handling the emergency)
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name='emergencies' AND column_name='assigned_to') THEN
        ALTER TABLE emergencies ADD COLUMN assigned_to UUID REFERENCES profiles(id);
    END IF;

    -- 2. Add dispatch_type
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name='emergencies' AND column_name='dispatch_type') THEN
        ALTER TABLE emergencies ADD COLUMN dispatch_type TEXT CHECK (dispatch_type IN ('rider', 'team', 'ambulance', 'none')) DEFAULT 'none';
    END IF;

    -- 3. Add dispatch_notes (Instructions for dispatch team)
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name='emergencies' AND column_name='dispatch_notes') THEN
        ALTER TABLE emergencies ADD COLUMN dispatch_notes TEXT;
    END IF;

    -- 4. Add medication_notes (Prescribed drugs for dispatch rider)
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name='emergencies' AND column_name='medication_notes') THEN
        ALTER TABLE emergencies ADD COLUMN medication_notes TEXT;
    END IF;

    -- 5. Add timestamps for response tracking
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name='emergencies' AND column_name='responded_at') THEN
        ALTER TABLE emergencies ADD COLUMN responded_at TIMESTAMPTZ;
    END IF;

    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name='emergencies' AND column_name='resolved_at') THEN
        ALTER TABLE emergencies ADD COLUMN resolved_at TIMESTAMPTZ;
    END IF;

    -- 6. Update Status constraint if necessary (Supabase handles this via CHECK)
    -- Ensure 'assigned' is an allowed status
    ALTER TABLE emergencies DROP CONSTRAINT IF EXISTS emergencies_status_check;
    ALTER TABLE emergencies ADD CONSTRAINT emergencies_status_check CHECK (status IN ('active', 'pending', 'assigned', 'dispatched', 'on_site', 'resolved'));

END $$;

-- Enable RLS and add policies
ALTER TABLE emergencies ENABLE ROW LEVEL SECURITY;

DROP POLICY IF EXISTS "Admins can manage emergencies" ON emergencies;
CREATE POLICY "Admins can manage emergencies" ON emergencies FOR ALL 
USING (auth.jwt() -> 'user_metadata' ->> 'role' = 'admin');

DROP POLICY IF EXISTS "Assigned staff can update emergency" ON emergencies;
CREATE POLICY "Assigned staff can update emergency" ON emergencies FOR UPDATE
USING (auth.uid() = assigned_to);

DROP POLICY IF EXISTS "Staff can view all active emergencies" ON emergencies;
CREATE POLICY "Staff can view all active emergencies" ON emergencies FOR SELECT
USING (auth.jwt() -> 'user_metadata' ->> 'role' IN ('doctor', 'nurse', 'pharmacist', 'admin'));
