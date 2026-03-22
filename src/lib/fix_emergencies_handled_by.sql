-- FIX EMERGENCIES: Add handled_by column and refine policies
-- This column tracks who exactly dispatched or resolved the emergency

DO $$ 
BEGIN
    -- 1. Add handled_by (UUID referencing profiles)
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name='emergencies' AND column_name='handled_by') THEN
        ALTER TABLE emergencies ADD COLUMN handled_by UUID REFERENCES profiles(id);
    END IF;

    -- 2. Refine Policies
    -- Ensure "Staff can view all active emergencies" includes 'admin'
    DROP POLICY IF EXISTS "Staff can view all active emergencies" ON emergencies;
    CREATE POLICY "Staff can view all active emergencies" ON emergencies FOR SELECT
    USING (
        auth.jwt() -> 'user_metadata' ->> 'role' IN ('doctor', 'nurse', 'pharmacist', 'admin', 'technician') OR
        auth.uid() = reporter_id
    );

    -- Ensure "Admins can manage emergencies" is definitely FOR ALL
    DROP POLICY IF EXISTS "Admins can manage emergencies" ON emergencies;
    CREATE POLICY "Admins can manage emergencies" ON emergencies FOR ALL 
    USING (auth.jwt() -> 'user_metadata' ->> 'role' = 'admin');

    -- Ensure "Assigned staff can update emergency" for non-admins
    DROP POLICY IF EXISTS "Assigned staff can update emergency" ON emergencies;
    CREATE POLICY "Assigned staff can update emergency" ON emergencies FOR UPDATE
    USING (auth.uid() = assigned_to);

END $$;
