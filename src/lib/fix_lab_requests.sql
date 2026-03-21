-- Add the requester_id to track who explicitly asked for a lab test
ALTER TABLE lab_requests ADD COLUMN IF NOT EXISTS requester_id UUID REFERENCES profiles(id);
