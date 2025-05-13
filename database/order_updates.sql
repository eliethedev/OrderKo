-- Add cancel_reason and cancelled_at columns to orders table
ALTER TABLE orders 
ADD COLUMN cancel_reason VARCHAR(255) NULL AFTER status,
ADD COLUMN cancelled_at DATETIME NULL AFTER cancel_reason;
