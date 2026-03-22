-- Phase 39: Pharmacy & Inventory Enhancements

-- 1. Upgrade drug_inventory with pricing and categories
ALTER TABLE drug_inventory 
ADD COLUMN IF NOT EXISTS unit_price DECIMAL(10, 2) DEFAULT 0.00,
ADD COLUMN IF NOT EXISTS category TEXT DEFAULT 'General',
ADD COLUMN IF NOT EXISTS reorder_level INTEGER DEFAULT 10;

-- 2. Enhance prescriptions with fulfillment tracking
ALTER TABLE prescriptions 
ADD COLUMN IF NOT EXISTS patient_id UUID REFERENCES profiles(id),
ADD COLUMN IF NOT EXISTS dispensed_at TIMESTAMPTZ,
ADD COLUMN IF NOT EXISTS dispensed_by UUID REFERENCES profiles(id),
ADD COLUMN IF NOT EXISTS drug_id UUID REFERENCES drug_inventory(id),
ADD COLUMN IF NOT EXISTS batch_number TEXT,
ADD COLUMN IF NOT EXISTS dispense_notes TEXT,
ADD COLUMN IF NOT EXISTS quantity_requested INTEGER DEFAULT 1;

-- 3. Enable RLS and Policies (Assuming profiles and other tables are already set)
-- Ensure pharmacists can manage inventory
-- (Previous policies might exist, but we ensure access)
CREATE POLICY "Pharmacists can manage drug_inventory" ON drug_inventory FOR ALL 
USING (auth.jwt() -> 'user_metadata' ->> 'role' = 'pharmacist');
