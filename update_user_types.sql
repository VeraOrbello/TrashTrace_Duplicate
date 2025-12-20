-- Update existing user_type values from barangay_driver to driver and barangay_driver_pending to driver_pending
UPDATE users SET user_type = 'driver' WHERE user_type = 'barangay_driver';
UPDATE users SET user_type = 'driver_pending' WHERE user_type = 'barangay_driver_pending';

-- Update the enum definition in the users table to remove barangay_driver and barangay_driver_pending
ALTER TABLE users MODIFY COLUMN user_type ENUM('user','driver_pending','driver','admin_pending','admin') DEFAULT 'user';
