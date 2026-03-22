-- Fix: Missing diagnosis column in consultations table
ALTER TABLE consultations ADD COLUMN IF NOT EXISTS diagnosis TEXT;

-- Reload PostgREST schema cache
NOTIFY pgrst, 'reload schema';
