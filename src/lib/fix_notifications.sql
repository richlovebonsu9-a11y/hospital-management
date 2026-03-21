-- Migration: Enable Notifications Visibility
-- 1. Ensure RLS is active
ALTER TABLE notifications ENABLE ROW LEVEL SECURITY;

-- 2. Policy: Users can see their own notifications
DO $$ 
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_policies WHERE policyname = 'Users can view their own notifications' AND tablename = 'notifications') THEN
        CREATE POLICY "Users can view their own notifications" ON notifications 
        FOR SELECT TO authenticated 
        USING (auth.uid() = user_id);
    END IF;

    -- Admins can manage all notifications (optional but good for debugging)
    IF NOT EXISTS (SELECT 1 FROM pg_policies WHERE policyname = 'Admins can manage all notifications' AND tablename = 'notifications') THEN
        CREATE POLICY "Admins can manage all notifications" ON notifications 
        FOR ALL TO authenticated 
        USING (auth.jwt() -> 'user_metadata' ->> 'role' = 'admin');
    END IF;
END $$;

-- 3. Reload schema cache
NOTIFY pgrst, 'reload schema';
