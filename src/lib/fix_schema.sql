-- 1. Ensure the status column exists in the guardians table
DO $$ 
BEGIN 
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name='guardians' AND column_name='status') THEN
        ALTER TABLE guardians ADD COLUMN status TEXT DEFAULT 'pending';
    END IF;
END $$;

-- 2. Force a schema cache reload for PostgREST
NOTIFY pgrst, 'reload schema';

-- 3. Verify the columns
SELECT column_name, data_type 
FROM information_schema.columns 
WHERE table_name = 'guardians';
