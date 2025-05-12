-- Sample data for businesses
INSERT INTO businesses (name, description, category, address, latitude, longitude, user_id, image_url, verification_status, status, rating) 
VALUES 
('Tasty Treats Bakery', 'Delicious homemade pastries and cakes', 'Bakery', '123 Main St, Manila', 14.5995, 120.9842, 1, 'assets/images/default-business.jpg', 'verified', 'active', 4.5),
('Fresh Grocery', 'Fresh fruits and vegetables daily', 'Grocery', '456 Market Ave, Quezon City', 14.6760, 121.0437, 1, 'assets/images/default-business.jpg', 'verified', 'active', 4.2),
('Tech Gadgets', 'Latest electronics and accessories', 'Retail', '789 Digital Blvd, Makati', 14.5547, 121.0244, 1, 'assets/images/default-business.jpg', 'pending', 'active', 4.0);

-- Sample data for products
INSERT INTO products (business_id, name, description, price, image_url, is_available) 
VALUES 
(1, 'Chocolate Cake', 'Rich chocolate cake with fudge frosting', 450.00, 'assets/images/default-product.jpg', 1),
(1, 'Blueberry Muffins', 'Fresh blueberry muffins, pack of 6', 180.00, 'assets/images/default-product.jpg', 1),
(1, 'Sourdough Bread', 'Artisan sourdough bread, freshly baked', 120.00, 'assets/images/default-product.jpg', 1),
(2, 'Organic Bananas', 'Organic bananas, 1kg', 80.00, 'assets/images/default-product.jpg', 1),
(2, 'Fresh Spinach', 'Locally grown spinach, 250g', 45.00, 'assets/images/default-product.jpg', 1),
(2, 'Avocados', 'Ripe avocados, pack of 3', 150.00, 'assets/images/default-product.jpg', 1),
(3, 'Wireless Earbuds', 'Bluetooth 5.0 wireless earbuds', 1200.00, 'assets/images/default-product.jpg', 1),
(3, 'Phone Charger', 'Fast charging USB-C cable and adapter', 350.00, 'assets/images/default-product.jpg', 1),
(3, 'Laptop Sleeve', 'Protective sleeve for 15-inch laptops', 650.00, 'assets/images/default-product.jpg', 1);
