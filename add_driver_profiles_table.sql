-- Add the driver_profiles table to existing database

CREATE TABLE driver_profiles (
  id int(11) NOT NULL,
  driver_id int(11) NOT NULL,
  license_number varchar(50) DEFAULT NULL,
  vehicle_type varchar(50) DEFAULT NULL,
  vehicle_plate varchar(20) DEFAULT NULL,
  status enum('active','inactive','suspended') DEFAULT 'active',
  created_at timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Add indexes
ALTER TABLE driver_profiles
  ADD PRIMARY KEY (id),
  ADD KEY driver_id (driver_id);

-- Add auto increment
ALTER TABLE driver_profiles
  MODIFY id int(11) NOT NULL AUTO_INCREMENT;

-- Add foreign key constraint
ALTER TABLE driver_profiles
  ADD CONSTRAINT driver_profiles_ibfk_1 FOREIGN KEY (driver_id) REFERENCES users (id) ON DELETE CASCADE;
