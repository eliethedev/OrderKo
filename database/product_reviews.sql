-- Create product_reviews table
CREATE TABLE IF NOT EXISTS `product_reviews` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `rating` int(11) NOT NULL,
  `comment` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `product_id` (`product_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `product_reviews_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `product_reviews_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert sample review data
INSERT INTO `product_reviews` (`product_id`, `user_id`, `rating`, `comment`, `created_at`) VALUES
(1, 1, 5, 'Excellent product! Highly recommended.', '2025-05-10 08:30:00'),
(1, 2, 4, 'Very good quality and fast delivery.', '2025-05-11 10:15:00'),
(2, 1, 3, 'Good product but a bit expensive.', '2025-05-09 14:45:00'),
(3, 3, 5, 'Amazing taste and great packaging!', '2025-05-12 16:20:00'),
(4, 2, 4, 'Really enjoyed this. Will order again.', '2025-05-08 09:10:00');

-- Add a review count column to products table if it doesn't exist
ALTER TABLE `products` 
ADD COLUMN IF NOT EXISTS `review_count` int(11) NOT NULL DEFAULT 0,
ADD COLUMN IF NOT EXISTS `average_rating` decimal(3,1) DEFAULT NULL;

-- Update products table with review counts and average ratings
UPDATE products p
SET 
    review_count = (SELECT COUNT(*) FROM product_reviews pr WHERE pr.product_id = p.id),
    average_rating = (SELECT AVG(rating) FROM product_reviews pr WHERE pr.product_id = p.id);
