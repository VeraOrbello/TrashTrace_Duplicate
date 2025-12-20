-- Add admin_pending status to users table user_type enum
-- This script updates the user_type enum to include admin_pending status

ALTER TABLE users MODIFY COLUMN user_type ENUM('user','driver_pending','driver','admin_pending','admin') DEFAULT 'user';

-- Optional: Update any existing admin users to have proper status
-- Uncomment the following line if you want to set existing admins to admin_pending first
-- UPDATE users SET user_type = 'admin_pending' WHERE user_type = 'admin' AND created_at > DATE_SUB(NOW(), INTERVAL 1 DAY);
