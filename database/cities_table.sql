-- Create cities table for Philippines
CREATE TABLE `cities` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `region` varchar(100) DEFAULT NULL,
  `province` varchar(100) DEFAULT NULL,
  `country_code` varchar(2) NOT NULL DEFAULT 'PH',
  `latitude` decimal(10,6) NOT NULL,
  `longitude` decimal(10,6) NOT NULL,
  `population` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_country_code` (`country_code`),
  KEY `idx_coordinates` (`latitude`, `longitude`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert cities in the Negros Island Region with their coordinates
INSERT INTO `cities` (`name`, `region`, `province`, `country_code`, `latitude`, `longitude`, `population`) VALUES
('Bacolod', 'Negros Island Region', 'Negros Occidental', 'PH', 10.676180, 122.952080, 561875),
('Dumaguete', 'Negros Island Region', 'Negros Oriental', 'PH', 9.307270, 123.307198, 131377),
('Kabankalan', 'Negros Island Region', 'Negros Occidental', 'PH', 9.990000, 122.814720, 176970),
('Bago', 'Negros Island Region', 'Negros Occidental', 'PH', 10.537800, 122.837700, 170981),
('Talisay', 'Negros Island Region', 'Negros Occidental', 'PH', 10.743100, 122.970900, 133148),
('Silay', 'Negros Island Region', 'Negros Occidental', 'PH', 10.797800, 122.973600, 85350),
('Bayawan', 'Negros Island Region', 'Negros Oriental', 'PH', 9.362500, 122.802200, 114074),
('Bais', 'Negros Island Region', 'Negros Oriental', 'PH', 9.590500, 123.120800, 80136),
('Sagay', 'Negros Island Region', 'Negros Occidental', 'PH', 10.900600, 123.414700, 146264),
('San Carlos', 'Negros Island Region', 'Negros Occidental', 'PH', 10.493300, 123.424700, 129981),
('Canlaon', 'Negros Island Region', 'Negros Oriental', 'PH', 10.382800, 123.193600, 50975),
('Himamaylan', 'Negros Island Region', 'Negros Occidental', 'PH', 10.106400, 122.865300, 106880),
('La Carlota', 'Negros Island Region', 'Negros Occidental', 'PH', 10.421300, 122.910100, 63852),
('Cadiz', 'Negros Island Region', 'Negros Occidental', 'PH', 10.956800, 123.308600, 151500),
('Victorias', 'Negros Island Region', 'Negros Occidental', 'PH', 10.900800, 123.075800, 88915),
('Guihulngan', 'Negros Island Region', 'Negros Oriental', 'PH', 10.116100, 123.270800, 95969),
('Sipalay', 'Negros Island Region', 'Negros Occidental', 'PH', 9.750800, 122.400100, 67403),
('Tanjay', 'Negros Island Region', 'Negros Oriental', 'PH', 9.526100, 123.159200, 79098),
('Escalante', 'Negros Island Region', 'Negros Occidental', 'PH', 10.834700, 123.498600, 93005),
('Valencia', 'Negros Island Region', 'Negros Oriental', 'PH', 9.272200, 123.252200, 31977);
