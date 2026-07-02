-- Namma AutoParts Database Schema & Seed Data
-- Target Database: MySQL (InnoDB, utf8mb4)

CREATE DATABASE IF NOT EXISTS namma_autoparts DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE namma_autoparts;

-- 1. USERS TABLE
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    phone VARCHAR(20),
    password VARCHAR(255) NOT NULL,
    role ENUM('customer', 'admin', 'vendor') NOT NULL DEFAULT 'customer',
    loyalty_points INT DEFAULT 0,
    referral_code VARCHAR(50) UNIQUE,
    referred_by INT,
    is_b2b_approved TINYINT(1) DEFAULT 0,
    b2b_credit_limit DECIMAL(10,2) DEFAULT 0.00,
    b2b_credit_used DECIMAL(10,2) DEFAULT 0.00,
    login_attempts INT DEFAULT 0,
    lockout_until DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (referred_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. VEHICLES MASTER TABLE (For fitment finder)
CREATE TABLE IF NOT EXISTS vehicles_master (
    id INT AUTO_INCREMENT PRIMARY KEY,
    make VARCHAR(50) NOT NULL,
    model VARCHAR(50) NOT NULL,
    year_from INT NOT NULL,
    year_to INT NOT NULL,
    engine_type VARCHAR(100) NOT NULL,
    fuel_type ENUM('Petrol', 'Diesel', 'CNG', 'Electric', 'Hybrid') NOT NULL,
    UNIQUE KEY uq_vehicle (make, model, year_from, year_to, engine_type, fuel_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. CATEGORIES TABLE (Hierarchical)
CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE,
    parent_id INT DEFAULT NULL,
    FOREIGN KEY (parent_id) REFERENCES categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. VENDORS TABLE
CREATE TABLE IF NOT EXISTS vendors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    shop_name VARCHAR(150) NOT NULL,
    owner_name VARCHAR(100) NOT NULL,
    gst_number VARCHAR(15) NOT NULL,
    location VARCHAR(255) NOT NULL,
    commission_rate DECIMAL(5,2) DEFAULT 10.00,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    whatsapp_number VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. WAREHOUSES TABLE
CREATE TABLE IF NOT EXISTS warehouses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    location VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. PRODUCTS TABLE
CREATE TABLE IF NOT EXISTS products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    slug VARCHAR(200) NOT NULL UNIQUE,
    sku VARCHAR(100) NOT NULL UNIQUE,
    category_id INT NOT NULL,
    vendor_id INT DEFAULT NULL, -- NULL indicates admin-sold directly
    brand VARCHAR(100) NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    discount_price DECIMAL(10,2) DEFAULT NULL,
    stock_qty INT NOT NULL DEFAULT 0,
    `condition` ENUM('new', 'used', 'refurbished') NOT NULL DEFAULT 'new',
    warranty_months INT DEFAULT 0,
    core_charge DECIMAL(10,2) DEFAULT 0.00,
    description TEXT,
    images JSON, -- stores array of image filenames e.g. ["prod1_1.jpg", "prod1_2.jpg"]
    hsn_code VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE,
    FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE SET NULL,
    INDEX idx_sku (sku),
    INDEX idx_slug (slug),
    INDEX idx_category (category_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. PRODUCT VEHICLE FITMENT (Many-to-Many)
CREATE TABLE IF NOT EXISTS product_vehicle_fitment (
    product_id INT NOT NULL,
    vehicle_id INT NOT NULL,
    PRIMARY KEY (product_id, vehicle_id),
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (vehicle_id) REFERENCES vehicles_master(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. USER SAVED VEHICLES ("My Garage")
CREATE TABLE IF NOT EXISTS user_saved_vehicles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    vehicle_id INT NOT NULL,
    label VARCHAR(100),
    is_active TINYINT(1) DEFAULT 0,
    UNIQUE KEY uq_user_vehicle (user_id, vehicle_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (vehicle_id) REFERENCES vehicles_master(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 15. GARAGES TABLE
CREATE TABLE IF NOT EXISTS garages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    location VARCHAR(255) NOT NULL,
    contact VARCHAR(20) NOT NULL,
    services_offered TEXT,
    rating DECIMAL(3,2) DEFAULT 5.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- 9. CART TABLE
CREATE TABLE IF NOT EXISTS cart (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    session_id VARCHAR(100) DEFAULT NULL,
    product_id INT NOT NULL,
    qty INT NOT NULL DEFAULT 1,
    garage_id INT DEFAULT NULL,
    booking_date DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (garage_id) REFERENCES garages(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. COUPONS TABLE
CREATE TABLE IF NOT EXISTS coupons (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) NOT NULL UNIQUE,
    discount_type ENUM('Percentage', 'Flat') NOT NULL,
    discount_value DECIMAL(10,2) NOT NULL,
    expiry DATE NOT NULL,
    usage_limit INT DEFAULT 100,
    used_count INT DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 11. ORDERS TABLE
CREATE TABLE IF NOT EXISTS orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    order_no VARCHAR(50) NOT NULL UNIQUE,
    total_amount DECIMAL(10,2) NOT NULL,
    gst_amount DECIMAL(10,2) NOT NULL,
    cgst_amount DECIMAL(10,2) DEFAULT 0.00,
    sgst_amount DECIMAL(10,2) DEFAULT 0.00,
    igst_amount DECIMAL(10,2) DEFAULT 0.00,
    status ENUM('Placed', 'Packed', 'Shipped', 'Delivered', 'Cancelled') DEFAULT 'Placed',
    payment_method ENUM('COD', 'Card', 'UPI', 'B2B_Credit') NOT NULL,
    payment_status ENUM('Pending', 'Paid', 'Refunded') DEFAULT 'Pending',
    shipping_address TEXT NOT NULL,
    shipping_state VARCHAR(100) NOT NULL,
    coupon_code VARCHAR(50) DEFAULT NULL,
    discount_amount DECIMAL(10,2) DEFAULT 0.00,
    loyalty_points_used INT DEFAULT 0,
    loyalty_points_earned INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 12. ORDER ITEMS TABLE
CREATE TABLE IF NOT EXISTS order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    product_id INT NOT NULL,
    qty INT NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    core_charge DECIMAL(10,2) DEFAULT 0.00,
    core_charge_returned ENUM('Not Applicable', 'Pending Return', 'Approved', 'Rejected') DEFAULT 'Not Applicable',
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 13. REVIEWS TABLE
CREATE TABLE IF NOT EXISTS reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    user_id INT NOT NULL,
    rating TINYINT NOT NULL CHECK (rating BETWEEN 1 AND 5),
    comment TEXT,
    verified_purchase TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 14. WISHLIST TABLE
CREATE TABLE IF NOT EXISTS wishlist (
    user_id INT NOT NULL,
    product_id INT NOT NULL,
    PRIMARY KEY (user_id, product_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- 16. SERVICE BOOKINGS TABLE
CREATE TABLE IF NOT EXISTS service_bookings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    garage_id INT NOT NULL,
    service_type VARCHAR(150) NOT NULL,
    vehicle_details VARCHAR(255) NOT NULL,
    booking_date DATETIME NOT NULL,
    status ENUM('Pending', 'Confirmed', 'Completed', 'Cancelled') DEFAULT 'Pending',
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (garage_id) REFERENCES garages(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 17. INVENTORY LOG TABLE
CREATE TABLE IF NOT EXISTS inventory_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    change_qty INT NOT NULL,
    reason VARCHAR(255) NOT NULL,
    warehouse_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (warehouse_id) REFERENCES warehouses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 18. NOTIFICATIONS TABLE
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    message TEXT NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 19. FITMENT CLAIMS TABLE
CREATE TABLE IF NOT EXISTS fitment_claims (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    product_id INT NOT NULL,
    user_id INT NOT NULL,
    reason VARCHAR(255) NOT NULL,
    status ENUM('Pending', 'Approved', 'Rejected') DEFAULT 'Pending',
    compensation_points INT DEFAULT 100,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;





-- ==========================================
-- SEED DATA
-- ==========================================

-- 1. SEED USERS (Bcrypt passwords: password123 -> $2y$10$tZptz.K4X/Bw7j1YJj7uT.Zq62C2VbJg6iI3jVzD1o2n476h2n83G)
INSERT INTO users (id, name, email, phone, password, role, loyalty_points, referral_code, is_b2b_approved, b2b_credit_limit) VALUES
(1, 'Admin Namma', 'admin@namma.com', '9876543210', '$2y$10$tZptz.K4X/Bw7j1YJj7uT.Zq62C2VbJg6iI3jVzD1o2n476h2n83G', 'admin', 0, 'ADMINREF', 0, 0.00),
(2, 'Coimbatore Auto Spares', 'coimbatore@vendor.com', '9876543211', '$2y$10$tZptz.K4X/Bw7j1YJj7uT.Zq62C2VbJg6iI3jVzD1o2n476h2n83G', 'vendor', 0, 'CBEVNDR', 0, 0.00),
(3, 'Theni Car Zone', 'theni@vendor.com', '9876543212', '$2y$10$tZptz.K4X/Bw7j1YJj7uT.Zq62C2VbJg6iI3jVzD1o2n476h2n83G', 'vendor', 0, 'THNVNDR', 0, 0.00),
(4, 'Ramesh Kumar', 'ramesh@customer.com', '9876543213', '$2y$10$tZptz.K4X/Bw7j1YJj7uT.Zq62C2VbJg6iI3jVzD1o2n476h2n83G', 'customer', 250, 'RAMESH123', 0, 0.00),
(5, 'Senthil Garages', 'senthil@garage.com', '9876543214', '$2y$10$tZptz.K4X/Bw7j1YJj7uT.Zq62C2VbJg6iI3jVzD1o2n476h2n83G', 'customer', 500, 'SENTHILB2B', 1, 50000.00),
(6, 'Salem Spares', 'salem@vendor.com', '9876543215', '$2y$10$tZptz.K4X/Bw7j1YJj7uT.Zq62C2VbJg6iI3jVzD1o2n476h2n83G', 'vendor', 0, 'SLMVNDR', 0, 0.00);

-- 2. SEED VENDORS
INSERT INTO vendors (id, user_id, shop_name, owner_name, gst_number, location, commission_rate, status, whatsapp_number) VALUES
(1, 2, 'Coimbatore Auto Spares', 'Murugan Swamy', '33AAAAA1111A1Z1', 'Coimbatore, Tamil Nadu', 12.50, 'approved', '919876543211'),
(2, 3, 'Theni Car Zone', 'Pandi Durai', '33BBBBB2222B2Z2', 'Theni, Tamil Nadu', 10.00, 'approved', '919876543212'),
(3, 6, 'Salem Spares', 'Loganathan K', '33CCCCC3333C3Z3', 'Salem, Tamil Nadu', 15.00, 'pending', '919876543215');

-- 3. SEED VEHICLES MASTER (10 Models)
INSERT INTO vehicles_master (id, make, model, year_from, year_to, engine_type, fuel_type) VALUES
(1, 'Maruti Suzuki', 'Swift', 2018, 2024, '1.2L K-Series Petrol', 'Petrol'),
(2, 'Maruti Suzuki', 'Dzire', 2017, 2024, '1.2L K-Series Petrol', 'Petrol'),
(3, 'Hyundai', 'i20', 2020, 2024, '1.2L Kappa Petrol', 'Petrol'),
(4, 'Hyundai', 'Creta', 2015, 2020, '1.6L CRDi Diesel', 'Diesel'),
(5, 'Tata', 'Nexon', 2017, 2023, '1.2L Revotron Turbo Petrol', 'Petrol'),
(6, 'Tata', 'Nexon', 2017, 2023, '1.5L Revotorq Diesel', 'Diesel'),
(7, 'Mahindra', 'Thar', 2020, 2024, '2.2L mHawk Diesel', 'Diesel'),
(8, 'Mahindra', 'XUV500', 2011, 2018, '2.2L mHawk Diesel', 'Diesel'),
(9, 'Honda', 'City', 2014, 2020, '1.5L i-VTEC Petrol', 'Petrol'),
(10, 'Honda', 'Civic', 2019, 2022, '1.8L i-VTEC Petrol', 'Petrol');

-- 4. SEED CATEGORIES
INSERT INTO categories (id, name, slug, parent_id) VALUES
(1, 'Engine Parts', 'engine-parts', NULL),
(2, 'Brake System', 'brake-system', NULL),
(3, 'Suspension & Steering', 'suspension-steering', NULL),
(4, 'Electrical & Lighting', 'electrical-lighting', NULL),
(5, 'Body Parts & Accessories', 'body-parts-accessories', NULL),
-- Subcategories
(6, 'Spark Plugs', 'spark-plugs', 1),
(7, 'Alternators', 'alternators', 1),
(8, 'Fuel Injectors', 'fuel-injectors', 1),
(9, 'Brake Pads', 'brake-pads', 2),
(10, 'Brake Rotors', 'brake-rotors', 2),
(11, 'Shock Absorbers', 'shock-absorbers', 3),
(12, 'Power Steering Pumps', 'power-steering-pumps', 3),
(13, 'Batteries', 'batteries', 4),
(14, 'Headlights', 'headlights', 4),
(15, 'Starter Motors', 'starter-motors', 4),
(16, 'Wiper Blades', 'wiper-blades', 5),
(17, 'Side Mirrors', 'side-mirrors', 5);

-- 5. SEED WAREHOUSES
INSERT INTO warehouses (id, name, location) VALUES
(1, 'Coimbatore Warehouse', 'Avinashi Road, Coimbatore'),
(2, 'Madurai Warehouse', 'Mattuthavani, Madurai');

-- 6. SEED PRODUCTS (30 items across 5 main categories)
INSERT INTO products (id, name, slug, sku, category_id, vendor_id, brand, price, discount_price, stock_qty, `condition`, warranty_months, core_charge, description, images, hsn_code) VALUES
-- Category: Engine Parts (1) / Spark Plugs (6)
(1, 'NGK Iridium Spark Plug Set (4 pcs)', 'ngk-iridium-spark-plug-set', 'SKU-NGK-IR-001', 6, 1, 'NGK', 2800.00, 2499.00, 50, 'new', 12, 0.00, 'Premium iridium spark plugs for smoother engine response and higher fuel efficiency. Set of 4.', '["spark1.jpg"]', '85111000'),
(2, 'Bosch Super4 Spark Plug', 'bosch-super4-spark-plug', 'SKU-BOS-S4-002', 6, 2, 'Bosch', 450.00, 399.00, 100, 'new', 6, 0.00, 'Bosch high-performance spark plug with four ground electrodes for maximum spark coverage.', '["spark2.jpg"]', '85111000'),

-- Category: Engine Parts (1) / Alternators (7)
(3, 'Denso Refurbished 90A Alternator', 'denso-refurbished-90a-alternator', 'SKU-DEN-ALT-RF', 7, 1, 'Denso', 7500.00, 6800.00, 8, 'refurbished', 6, 1500.00, 'Fully rebuilt Denso alternator. Refundable core charge applies. Send back your old alternator to receive a refund of ₹1,500.', '["alt1.jpg"]', '85115000'),
(4, 'Lucas TVS 12V Alternator Assembly', 'lucas-tvs-12v-alternator', 'SKU-LUC-ALT-NEW', 7, 2, 'Lucas TVS', 9500.00, 8900.00, 12, 'new', 24, 0.00, 'Brand new heavy-duty Lucas TVS alternator assembly for diesel engine vehicles.', '["alt2.jpg"]', '85115000'),

-- Category: Engine Parts (1) / Fuel Injectors (8)
(5, 'Delphi Fuel Injector Nozzle', 'delphi-fuel-injector-nozzle', 'SKU-DEL-FI-005', 8, 1, 'Delphi', 4200.00, NULL, 25, 'new', 12, 0.00, 'Precision-engineered Delphi fuel injector for CRDI engines. Boosts power and cuts emissions.', '["inj1.jpg"]', '84099911'),
(6, 'Used Bosch Fuel Injector (Set of 4)', 'used-bosch-fuel-injector-set', 'SKU-BOS-FI-USD', 8, 2, 'Bosch', 12000.00, 10500.00, 3, 'used', 3, 2000.00, 'Tested and calibrated OEM Bosch injectors removed from low mileage vehicles. ₹2,000 core charge deposit included.', '["inj2.jpg", "inj2_actual.jpg"]', '84099911'),

-- Category: Brake System (2) / Brake Pads (9)
(7, 'TVS Girling Front Brake Pads Set', 'tvs-girling-front-brake-pads', 'SKU-TVS-BP-007', 9, 1, 'TVS Girling', 1800.00, 1599.00, 40, 'new', 6, 0.00, 'Asbestos-free organic brake pads for smooth, noise-free braking. High heat resistance.', '["pads1.jpg"]', '87083000'),
(8, 'Brembo Ceramic Brake Pads Front', 'brembo-ceramic-front-brake-pads', 'SKU-BRE-BP-008', 9, 2, 'Brembo', 4500.00, 4200.00, 15, 'new', 12, 0.00, 'Brembo premium ceramic formula brake pads. Offers maximum stopping power with minimal brake dust.', '["pads2.jpg"]', '87083000'),
(9, 'KBX Rear Brake Pad Set', 'kbx-rear-brake-pad-set', 'SKU-KBX-BP-009', 9, 1, 'KBX', 1200.00, 1099.00, 30, 'new', 6, 0.00, 'KBX genuine rear brake pads for daily commuting vehicles.', '["pads3.jpg"]', '87083000'),

-- Category: Brake System (2) / Brake Rotors (10)
(10, 'Brembo Ventilated Brake Disc Rotor (Pair)', 'brembo-ventilated-brake-rotor-pair', 'SKU-BRE-BR-010', 10, 1, 'Brembo', 8800.00, 7999.00, 10, 'new', 12, 0.00, 'High-carbon ventilated front brake disc rotors for advanced thermal dissipation. Set of 2.', '["rotor1.jpg"]', '87083000'),
(11, 'Refurbished OEM Brake Rotor Set', 'refurbished-oem-brake-rotor-set', 'SKU-OEM-BR-RF', 10, 2, 'OEM', 3200.00, 2900.00, 5, 'refurbished', 3, 800.00, 'Resurfaced and dynamically balanced factory front brake rotors. Safe and budget-friendly.', '["rotor2.jpg"]', '87083000'),

-- Category: Suspension & Steering (3) / Shock Absorbers (11)
(12, 'Gabriel Rear Shock Absorber (Gas Type)', 'gabriel-rear-shock-absorber', 'SKU-GAB-SA-012', 11, 1, 'Gabriel', 2400.00, 2199.00, 20, 'new', 12, 0.00, 'Gabriel Gas-pressure shock absorber for smooth rides on rough Indian roads.', '["shock1.jpg"]', '87088000'),
(13, 'Monroe Front Strut Assembly (Left)', 'monroe-front-strut-left', 'SKU-MON-SA-013', 11, 2, 'Monroe', 3800.00, 3499.00, 15, 'new', 12, 0.00, 'Monroe OE-Spectrum front strut assembly. Heavy duty construction for longevity.', '["shock2.jpg"]', '87088000'),
(14, 'Monroe Front Strut Assembly (Right)', 'monroe-front-strut-right', 'SKU-MON-SA-014', 11, 2, 'Monroe', 3800.00, 3499.00, 15, 'new', 12, 0.00, 'Monroe OE-Spectrum front strut assembly. Right side configuration.', '["shock2.jpg"]', '87088000'),

-- Category: Suspension & Steering (3) / Power Steering Pumps (12)
(15, 'Refurbished ZF Power Steering Pump', 'zf-refurbished-power-steering-pump', 'SKU-ZF-PSP-RF', 12, 1, 'ZF', 8500.00, 7500.00, 4, 'refurbished', 6, 2000.00, 'Fully rebuilt ZF hydraulic power steering pump. Core exchange required (₹2,000 refund upon returning core).', '["psp1.jpg"]', '87089400'),
(16, 'Rane Power Steering Pump Assembly', 'rane-power-steering-pump-new', 'SKU-RAN-PSP-016', 12, 2, 'Rane', 12500.00, 11500.00, 6, 'new', 18, 0.00, 'Original Rane high-performance power steering pump for diesel utility vehicles.', '["psp2.jpg"]', '87089400'),

-- Category: Electrical & Lighting (4) / Batteries (13)
(17, 'Exide Mileage FML0-MRED35L (35AH)', 'exide-mileage-35ah-battery', 'SKU-EXI-BT-017', 13, 1, 'Exide', 4800.00, 4299.00, 30, 'new', 36, 600.00, 'Exide Mileage maintenance-free car battery. Price includes a refundable ₹600 core charge for your old battery return.', '["bat1.jpg"]', '85071000'),
(18, 'Amaron FLO AAM-FL-550LMF (50AH)', 'amaron-flo-50ah-battery', 'SKU-AMA-BT-018', 13, 2, 'Amaron', 6200.00, 5699.00, 25, 'new', 48, 800.00, 'Amaron Flo series long life car battery. Premium warranty. Refundable core charge of ₹800 applies.', '["bat2.jpg"]', '85071000'),

-- Category: Electrical & Lighting (4) / Headlights (14)
(19, 'Lumax Projector Headlight Right Side', 'lumax-projector-headlight-rh', 'SKU-LUM-HL-019', 14, 1, 'Lumax', 9500.00, 8999.00, 10, 'new', 12, 0.00, 'Lumax OE projector headlight assembly with integrated LED DRL, right hand side.', '["hl1.jpg"]', '85122010'),
(20, 'Lumax Projector Headlight Left Side', 'lumax-projector-headlight-lh', 'SKU-LUM-HL-020', 14, 1, 'Lumax', 9500.00, 8999.00, 10, 'new', 12, 0.00, 'Lumax OE projector headlight assembly with integrated LED DRL, left hand side.', '["hl1.jpg"]', '85122010'),
(21, 'Philips RacingVision H7 Halogen Bulb Set', 'philips-racingvision-h7-bulb-pair', 'SKU-PHL-HB-021', 14, 2, 'Philips', 1450.00, 1199.00, 60, 'new', 6, 0.00, 'Up to 150% brighter light for superior night driving. 12V 55W H7 bulbs. Set of 2.', '["hl2.jpg"]', '85122010'),

-- Category: Electrical & Lighting (4) / Starter Motors (15)
(22, 'Lucas TVS Starter Motor Assembly', 'lucas-tvs-starter-motor-assembly', 'SKU-LUC-SM-022', 15, 1, 'Lucas TVS', 6800.00, 6200.00, 14, 'new', 12, 0.00, 'Original starter motor assembly compatible with subcompact petrol models.', '["start1.jpg"]', '85114000'),
(23, 'Refurbished Bosch Starter Motor 12V', 'refurbished-bosch-starter-motor', 'SKU-BOS-SM-RF', 15, 2, 'Bosch', 4500.00, 3999.00, 7, 'refurbished', 6, 1000.00, 'Fully bench-tested refurbished starter motor. Core charge of ₹1,000 applies.', '["start2.jpg"]', '85114000'),

-- Category: Body Parts & Accessories (5) / Wiper Blades (16)
(24, 'Bosch Clear Advantage Wiper Blade Set', 'bosch-clear-advantage-wiper-set', 'SKU-BOS-WB-024', 16, 1, 'Bosch', 850.00, 699.00, 80, 'new', 0, 0.00, 'Flat wiper blade technology for clean all-weather wiping. Easy installation connectors. Set of 2 (21" and 19").', '["wiper1.jpg"]', '96035000'),
(25, 'Hella Premium Conventional Wiper Blade', 'hella-premium-conventional-wiper', 'SKU-HEL-WB-025', 16, 2, 'Hella', 350.00, 299.00, 120, 'new', 0, 0.00, 'Quality rubber squeegee conventional wiper blade, size 20". Heavy-duty steel frame.', '["wiper2.jpg"]', '96035000'),

-- Category: Body Parts & Accessories (5) / Side Mirrors (17)
(26, 'OEM Electric Side Mirror Left Side', 'oem-electric-side-mirror-lh', 'SKU-OEM-MR-026', 17, 1, 'OEM', 3200.00, 2800.00, 18, 'new', 6, 0.00, 'Genuine replacement side mirror with motorized angle adjust, body-colored cover. Left side.', '["mirror1.jpg"]', '87082990'),
(27, 'OEM Electric Side Mirror Right Side', 'oem-electric-side-mirror-rh', 'SKU-OEM-MR-027', 17, 1, 'OEM', 3200.00, 2800.00, 18, 'new', 6, 0.00, 'Genuine replacement side mirror with motorized angle adjust, body-colored cover. Right side.', '["mirror1.jpg"]', '87082990'),
(28, 'Used OEM Wing Mirror Cover Chrome', 'used-oem-wing-mirror-chrome', 'SKU-OEM-MR-USD', 17, 2, 'OEM', 800.00, 650.00, 2, 'used', 0, 0.00, 'Minor scratches, chrome-plated mirror cover for utility vehicles.', '["mirror2_actual.jpg"]', '87082990'),

-- Category: Brake System (2) / Brake Rotors (10)
(29, 'KBX High Performance Brake Rotor Front', 'kbx-high-performance-brake-rotor', 'SKU-KBX-BR-029', 10, 1, 'KBX', 4200.00, NULL, 15, 'new', 6, 0.00, 'KBX high carbon brake disc rotor. Sold individually.', '["rotor3.jpg"]', '87083000'),

-- Category: Suspension & Steering (3) / Shock Absorbers (11)
(30, 'Gabriel Heavy Duty Front Strut Gas-charged', 'gabriel-hd-front-strut-gas', 'SKU-GAB-FS-030', 11, 2, 'Gabriel', 4900.00, 4499.00, 11, 'new', 12, 0.00, 'High durability gas strut assembly for rough road conditions.', '["shock3.jpg"]', '87088000');


-- 7. SEED PRODUCT VEHICLE FITMENT (Mapping parts to compatible vehicle models)
INSERT INTO product_vehicle_fitment (product_id, vehicle_id) VALUES
-- NGK Spark Plugs (1) fit Swift (1), Dzire (2), i20 (3)
(1, 1), (1, 2), (1, 3),
-- Bosch Spark Plug (2) fits Swift (1), Dzire (2), i20 (3), City (9)
(2, 1), (2, 2), (2, 3), (2, 9),
-- Denso Refurbished Alternator (3) fits Swift (1), Dzire (2)
(3, 1), (3, 2),
-- Lucas TVS Alternator (4) fits Thar (7), XUV500 (8), Nexon Diesel (6)
(4, 7), (4, 8), (4, 6),
-- Delphi Fuel Injector (5) fits Nexon Diesel (6), Thar (7)
(5, 6), (5, 7),
-- Used Bosch Fuel Injector (6) fits XUV500 (8), Thar (7)
(6, 8), (6, 7),
-- TVS Girling Front Brake Pads (7) fit Swift (1), Dzire (2)
(7, 1), (7, 2),
-- Brembo Ceramic Brake Pads (8) fit i20 (3), Civic (10), City (9)
(8, 3), (8, 10), (8, 9),
-- KBX Rear Brake Pads (9) fit i20 (3), Swift (1)
(9, 3), (9, 1),
-- Brembo Ventilated Rotor Pair (10) fits City (9), Civic (10)
(10, 9), (10, 10),
-- Refurbished OEM Brake Rotor Set (11) fits Swift (1), Dzire (2)
(11, 1), (11, 2),
-- Gabriel Rear Shock (12) fits Swift (1), Dzire (2)
(12, 1), (12, 2),
-- Monroe Front Strut Left (13) fits Nexon Petrol (5), Nexon Diesel (6)
(13, 5), (13, 6),
-- Monroe Front Strut Right (14) fits Nexon Petrol (5), Nexon Diesel (6)
(14, 5), (14, 6),
-- Refurbished ZF Power Steering Pump (15) fits Civic (10), City (9)
(15, 10), (15, 9),
-- Rane Power Steering Pump (16) fits Thar (7), XUV500 (8)
(16, 7), (16, 8),
-- Exide Battery (17) fits Swift (1), Dzire (2), i20 (3)
(17, 1), (17, 2), (17, 3),
-- Amaron Battery (18) fits Nexon (5), Nexon (6), Creta (4)
(18, 5), (18, 6), (18, 4),
-- Lumax Projector Headlight RH (19) fits Creta (4)
(19, 4),
-- Lumax Projector Headlight LH (20) fits Creta (4)
(20, 4),
-- Philips RacingVision bulbs (21) fits Creta (4), Nexon (5), Civic (10), City (9)
(21, 4), (21, 5), (21, 10), (21, 9),
-- Lucas TVS Starter Motor (22) fits Swift (1), Dzire (2), i20 (3)
(22, 1), (22, 2), (22, 3),
-- Refurbished Bosch Starter Motor (23) fits Nexon Petrol (5), Creta (4)
(23, 5), (23, 4),
-- Bosch Wiper Blades (24) fit i20 (3), Creta (4), Nexon (5)
(24, 3), (24, 4), (24, 5),
-- Hella Wiper Blade (25) fits Swift (1), Dzire (2), City (9)
(25, 1), (25, 2), (25, 9),
-- OEM Side Mirror LH (26) fits Nexon Petrol (5), Nexon Diesel (6)
(26, 5), (26, 6),
-- OEM Side Mirror RH (27) fits Nexon Petrol (5), Nexon Diesel (6)
(27, 5), (27, 6),
-- Used Wing Mirror Cover (28) fits Thar (7)
(28, 7),
-- KBX High Performance Rotor (29) fits Swift (1), Dzire (2)
(29, 1), (29, 2),
-- Gabriel Strut Gas-charged (30) fits Nexon Petrol (5), Nexon Diesel (6);
(30, 5), (30, 6);

-- 8. SEED GARAGES (partnered mechanic shops)
INSERT INTO garages (id, name, location, contact, services_offered, rating) VALUES
(1, 'Speedway Motors Coimbatore', 'Gandhipuram, Coimbatore', '9012345678', 'Engine repair, Brake service, Suspension alignment, Electrical diagnostics', 4.80),
(2, 'Theni Auto Care & Garage', 'Cumbum Road, Theni', '9012345679', 'Brake pad replacement, Battery testing, Wiper replacement, General service', 4.50),
(3, 'Sri Sai Garage & Fitment Center', 'Vadavalli, Coimbatore', '9012345680', 'Brake rotor turning, Shock absorber installation, Starter motor rebuild', 4.60);

-- 9. SEED COUPONS
INSERT INTO coupons (code, discount_type, discount_value, expiry, usage_limit, used_count) VALUES
( 'WELCOME10', 'Percentage', 10.00, '2027-12-31', 500, 0),
( 'SAVE500', 'Flat', 500.00, '2027-12-31', 200, 0),
( 'PONGAL2027', 'Percentage', 15.00, '2027-02-15', 300, 0);

-- 10. SEED INVENTORY INITIAL LOGS
INSERT INTO inventory_log (product_id, change_qty, reason, warehouse_id) VALUES
(1, 50, 'Initial Stock Receipt', 1),
(2, 100, 'Initial Stock Receipt', 1),
(3, 8, 'Initial Stock Receipt', 1),
(4, 12, 'Initial Stock Receipt', 1),
(5, 25, 'Initial Stock Receipt', 2),
(6, 3, 'Initial Stock Receipt', 2),
(7, 40, 'Initial Stock Receipt', 1),
(8, 15, 'Initial Stock Receipt', 1),
(9, 30, 'Initial Stock Receipt', 1),
(10, 10, 'Initial Stock Receipt', 1),
(11, 5, 'Initial Stock Receipt', 2),
(12, 20, 'Initial Stock Receipt', 1),
(13, 15, 'Initial Stock Receipt', 1),
(14, 15, 'Initial Stock Receipt', 1),
(15, 4, 'Initial Stock Receipt', 2),
(16, 6, 'Initial Stock Receipt', 2),
(17, 30, 'Initial Stock Receipt', 1),
(18, 25, 'Initial Stock Receipt', 1),
(19, 10, 'Initial Stock Receipt', 2),
(20, 10, 'Initial Stock Receipt', 2),
(21, 60, 'Initial Stock Receipt', 1),
(22, 14, 'Initial Stock Receipt', 1),
(23, 7, 'Initial Stock Receipt', 1),
(24, 80, 'Initial Stock Receipt', 2),
(25, 120, 'Initial Stock Receipt', 2),
(26, 18, 'Initial Stock Receipt', 1),
(27, 18, 'Initial Stock Receipt', 1),
(28, 2, 'Initial Stock Receipt', 2),
(29, 15, 'Initial Stock Receipt', 1),
(30, 11, 'Initial Stock Receipt', 1);

-- 11. SEED SOME SAMPLE REVIEWS
INSERT INTO reviews (product_id, user_id, rating, comment, verified_purchase) VALUES
(1, 4, 5, 'Excellent original spark plugs! Noticed immediate improvement in acceleration for my Swift.', 1),
(3, 4, 4, 'Alternator works perfectly. Received the core refund of ₹1,500 in my account 3 days after shipping my old one.', 1),
(7, 4, 4, 'Decent braking power, no noise so far. Good value for money.', 1);
