-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jan 08, 2026 at 11:10 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `pharmacy_system`
--

-- --------------------------------------------------------

--
-- Table structure for table `alerts`
--

CREATE TABLE `alerts` (
  `id` int(11) NOT NULL,
  `medicine_id` int(11) NOT NULL,
  `alert_type` enum('low_stock','near_expiry') NOT NULL,
  `message` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `alerts`
--

INSERT INTO `alerts` (`id`, `medicine_id`, `alert_type`, `message`, `created_at`) VALUES
(1, 2, 'low_stock', 'Calpol stock is low!', '2025-12-30 07:37:04'),
(2, 3, 'near_expiry', 'CoughClear batch B003 is near expiry!', '2025-12-30 07:37:04');

-- --------------------------------------------------------

--
-- Table structure for table `disposal_history`
--

CREATE TABLE `disposal_history` (
  `id` int(11) NOT NULL,
  `batch_id` int(11) DEFAULT NULL,
  `medicine_id` int(11) DEFAULT NULL,
  `batch_no` varchar(50) DEFAULT NULL,
  `quantity` int(11) DEFAULT NULL,
  `purchase_price` decimal(10,2) DEFAULT NULL,
  `disposal_reason` varchar(255) DEFAULT NULL,
  `disposal_method` varchar(255) DEFAULT NULL,
  `disposal_notes` text DEFAULT NULL,
  `disposed_by` int(11) DEFAULT NULL,
  `disposed_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `expenses`
--

CREATE TABLE `expenses` (
  `id` int(11) NOT NULL,
  `expense_type` enum('salary','utility','transport','store_expense','internet','other') NOT NULL,
  `description` varchar(255) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `expense_date` date NOT NULL,
  `payment_method` enum('cash','online','cheque') DEFAULT 'cash',
  `category` varchar(100) NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `receipt_image` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `expenses`
--

INSERT INTO `expenses` (`id`, `expense_type`, `description`, `amount`, `expense_date`, `payment_method`, `category`, `created_by`, `created_at`, `updated_at`, `receipt_image`, `notes`) VALUES
(7, 'salary', 'Pharmacist salary - January', 35000.00, '2024-01-05', 'online', 'Salary', NULL, '2026-01-08 09:20:18', '2026-01-08 09:20:18', NULL, ''),
(8, 'utility', 'Electricity bill', 8500.00, '2024-01-10', 'cash', 'Electricity', NULL, '2026-01-08 09:20:18', '2026-01-08 09:20:18', NULL, ''),
(9, 'utility', 'Water bill', 2200.00, '2024-01-12', 'cash', 'Water', NULL, '2026-01-08 09:20:18', '2026-01-08 09:20:18', NULL, ''),
(10, 'internet', 'Internet bill', 1180.00, '2024-01-08', 'online', 'Internet', NULL, '2026-01-08 09:20:18', '2026-01-08 09:20:18', NULL, ''),
(11, 'store_expense', 'Shop rent', 25000.00, '2024-01-01', 'online', 'Rent', NULL, '2026-01-08 09:20:18', '2026-01-08 09:20:18', NULL, '');

-- --------------------------------------------------------

--
-- Table structure for table `invoices`
--

CREATE TABLE `invoices` (
  `id` int(11) NOT NULL,
  `invoice_no` varchar(50) NOT NULL,
  `sale_id` int(11) NOT NULL,
  `pharmacist_id` int(11) NOT NULL,
  `customer_name` varchar(100) DEFAULT 'Walk-in Customer',
  `total_amount` decimal(10,2) NOT NULL,
  `discount` decimal(10,2) DEFAULT 0.00,
  `payment_method` enum('Cash','Online') DEFAULT 'Cash',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `invoices`
--

INSERT INTO `invoices` (`id`, `invoice_no`, `sale_id`, `pharmacist_id`, `customer_name`, `total_amount`, `discount`, `payment_method`, `created_at`) VALUES
(1, 'INV-2026010001', 9, 6, 'zahid', 1350.00, 50.00, 'Cash', '2026-01-01 07:48:24'),
(2, 'INV-2026010000', 14, 6, 'sajid', 250.00, 12.00, 'Cash', '2026-01-01 09:30:31'),
(3, 'INV-2026010002', 15, 6, 'izhan', 100.00, 55.00, 'Cash', '2026-01-01 11:20:20'),
(4, 'INV-2026010003', 16, 6, 'sajid', 937.50, 21.00, 'Cash', '2026-01-02 05:19:09'),
(5, 'INV-2026010004', 19, 6, 'ibad', 950.00, 50.00, 'Cash', '2026-01-06 06:29:22'),
(6, 'INV-2026010005', 20, 6, 'shani', 1368.00, 72.00, 'Cash', '2026-01-06 10:08:42'),
(7, 'INV-2026010006', 21, 6, 'shani', 10.00, 0.00, 'Cash', '2026-01-07 09:16:11'),
(8, 'INV-2026010007', 22, 6, 'Hasan', 12960.00, 1440.00, 'Cash', '2026-01-08 06:41:58'),
(9, 'INV-2026010008', 23, 6, 'Hasan', 250.00, 0.00, 'Cash', '2026-01-08 07:12:23'),
(10, 'INV-2026010009', 24, 6, 'sajid', 2790.00, 210.00, 'Cash', '2026-01-08 10:01:44');

-- --------------------------------------------------------

--
-- Table structure for table `invoice_items`
--

CREATE TABLE `invoice_items` (
  `id` int(11) NOT NULL,
  `invoice_id` int(11) NOT NULL,
  `medicine_id` int(11) NOT NULL,
  `batch_id` int(11) DEFAULT NULL,
  `category_name` varchar(100) DEFAULT NULL,
  `type_name` varchar(50) DEFAULT NULL,
  `company_name` varchar(100) DEFAULT NULL,
  `quantity` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `discount` decimal(10,2) DEFAULT 0.00,
  `total_price` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `invoice_items`
--

INSERT INTO `invoice_items` (`id`, `invoice_id`, `medicine_id`, `batch_id`, `category_name`, `type_name`, `company_name`, `quantity`, `price`, `discount`, `total_price`) VALUES
(1, 1, 18, NULL, NULL, NULL, NULL, 10, 25.00, 0.00, 250.00),
(2, 1, 13, NULL, NULL, NULL, NULL, 10, 110.00, 0.00, 1100.00),
(4, 2, 17, NULL, NULL, NULL, NULL, 10, 25.00, 0.00, 250.00),
(6, 3, 18, NULL, NULL, NULL, NULL, 4, 25.00, 0.00, 100.00),
(7, 4, 2, NULL, NULL, NULL, NULL, 50, 18.75, 0.00, 937.50),
(8, 5, 5, 225, NULL, NULL, NULL, 100, 10.00, 50.00, 950.00),
(9, 6, 206, 275, NULL, NULL, NULL, 10, 144.00, 72.00, 1368.00),
(10, 7, 5, 225, NULL, NULL, NULL, 1, 10.00, 0.00, 10.00),
(11, 8, 138, 274, NULL, NULL, NULL, 10, 1440.00, 1440.00, 12960.00),
(12, 9, 5, 225, NULL, NULL, NULL, 25, 10.00, 0.00, 250.00),
(13, 10, 3, 222, NULL, NULL, NULL, 100, 30.00, 210.00, 2790.00);

-- --------------------------------------------------------

--
-- Table structure for table `invoice_sequences`
--

CREATE TABLE `invoice_sequences` (
  `seq_key` varchar(20) NOT NULL,
  `last_value` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `invoice_sequences`
--

INSERT INTO `invoice_sequences` (`seq_key`, `last_value`) VALUES
('INV-202601', 9);

-- --------------------------------------------------------

--
-- Table structure for table `medicines`
--

CREATE TABLE `medicines` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `category_id` int(11) NOT NULL,
  `type_id` int(11) NOT NULL,
  `generic_name` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `medicines`
--

INSERT INTO `medicines` (`id`, `name`, `category_id`, `type_id`, `generic_name`, `description`, `created_at`) VALUES
(1, 'Panadol', 1, 1, 'Paracetamol', 'Pain reliever and fever reducer', '2025-12-30 03:30:15'),
(2, 'Calpol', 1, 2, 'Paracetamol', 'Liquid paracetamol for children', '2025-12-30 03:30:15'),
(3, 'CoughClear', 2, 2, 'Dextromethorphan', 'Cough syrup', '2025-12-30 03:30:15'),
(4, 'Amoxil', 4, 1, 'Amoxicillin', 'Antibiotic tablet', '2025-12-30 03:30:15'),
(5, 'Allergex', 5, 1, 'Cetirizine', 'Allergy relief tablet', '2025-12-30 03:30:15'),
(7, 'Calpol', 1, 2, 'Paracetamol', 'Liquid paracetamol for children', '2025-12-30 03:30:15'),
(8, 'Paracetamol Extra', 1, 1, 'Paracetamol', 'Extra strength tablet for pain and fever', '2025-12-30 03:30:15'),
(9, 'CoughClear', 2, 2, 'Dextromethorphan', 'Cough syrup', '2025-12-30 03:30:15'),
(10, 'Benylin', 2, 2, 'Dextromethorphan', 'Syrup for cough relief', '2025-12-30 03:30:15'),
(11, 'Delsym', 2, 2, 'Dextromethorphan', 'Extended-release cough syrup', '2025-12-30 03:30:15'),
(12, 'IbuProfen', 3, 1, 'Ibuprofen', 'Pain and inflammation relief tablet', '2025-12-30 03:30:15'),
(13, 'Brufen', 3, 1, 'Ibuprofen', 'Anti-inflammatory painkiller', '2025-12-30 03:30:15'),
(14, 'Motrin', 3, 5, 'Ibuprofen', 'Capsule for pain relief', '2025-12-30 03:30:15'),
(15, 'Amoxil', 4, 1, 'Amoxicillin', 'Antibiotic tablet', '2025-12-30 03:30:15'),
(16, 'Moxatag', 4, 1, 'Amoxicillin', 'Extended-release antibiotic tablet', '2025-12-30 03:30:15'),
(17, 'Augmentin', 4, 1, 'Amoxicillin + Clavulanate', 'Combination antibiotic', '2025-12-30 03:30:15'),
(18, 'Allergex', 5, 1, 'Cetirizine', 'Allergy relief tablet', '2025-12-30 03:30:15'),
(19, 'Zyrtec', 5, 1, 'Cetirizine', 'Allergy and hay fever relief', '2025-12-30 03:30:15'),
(20, 'Claritin', 5, 1, 'Loratadine', 'Non-drowsy allergy relief', '2025-12-30 03:30:15'),
(21, 'FluGone', 6, 2, 'Paracetamol + Pseudoephedrine', 'Cold and flu syrup', '2025-12-30 03:30:15'),
(22, 'ColdFast', 6, 1, 'Paracetamol + Phenylephrine', 'Cold and flu tablets', '2025-12-30 03:30:15'),
(23, 'Gaviscon', 7, 2, 'Alginic Acid', 'Antacid syrup', '2025-12-30 03:30:15'),
(24, 'Maalox', 7, 2, 'Aluminum Hydroxide', 'Acidity and heartburn syrup', '2025-12-30 03:30:15'),
(25, 'Centrum', 8, 1, 'Multivitamins', 'Daily multivitamin tablet', '2025-12-30 03:30:15'),
(26, 'Neurobion', 8, 1, 'B-Complex Vitamins', 'Vitamin B complex tablet', '2025-12-30 03:30:15'),
(27, 'Cortizone', 9, 6, 'Hydrocortisone', 'Anti-itch cream', '2025-12-30 03:30:15'),
(28, 'SkinRelief', 9, 6, 'Clotrimazole', 'Antifungal cream for skin infections', '2025-12-30 03:30:15'),
(29, 'Refresh Tears', 10, 2, 'Carboxymethylcellulose', 'Eye drops for dryness', '2025-12-30 03:30:15'),
(30, 'Optrex', 10, 2, 'Tetryzoline', 'Redness relief eye drops', '2025-12-30 03:30:15'),
(31, 'Otipax', 11, 2, 'Lidocaine + Phenazone', 'Ear drops for pain relief', '2025-12-30 03:30:15'),
(32, 'Debrox', 11, 2, 'Carbamide Peroxide', 'Earwax removal drops', '2025-12-30 03:30:15'),
(33, 'Atenolol', 12, 1, 'Atenolol', 'Blood pressure control tablet', '2025-12-30 03:30:15'),
(34, 'Norvasc', 12, 1, 'Amlodipine', 'Hypertension management', '2025-12-30 03:30:15'),
(35, 'Metformin', 13, 1, 'Metformin', 'Blood sugar control tablet', '2025-12-30 03:30:15'),
(36, 'Glucophage', 13, 1, 'Metformin', 'Type 2 diabetes management', '2025-12-30 03:30:15'),
(37, 'Ventolin', 14, 3, 'Salbutamol', 'Asthma inhaler', '2025-12-30 03:30:15'),
(38, 'Symbicort', 14, 3, 'Budesonide + Formoterol', 'Asthma and COPD inhaler', '2025-12-30 03:30:15'),
(39, 'Voltaren', 15, 6, 'Diclofenac', 'Topical cream for muscle pain', '2025-12-30 03:30:15'),
(40, 'Biofreeze', 15, 6, 'Menthol', 'Pain relief cream for joints', '2025-12-30 03:30:15'),
(41, 'Ibuprofen', 16, 1, 'Ibuprofen', 'Anti-inflammatory tablet', '2025-12-30 03:30:15'),
(42, 'Naprosyn', 16, 1, 'Naproxen', 'Anti-inflammatory tablet', '2025-12-30 03:30:15'),
(43, 'Clotrimazole Cream', 17, 6, 'Clotrimazole', 'Topical antifungal cream', '2025-12-30 03:30:15'),
(44, 'Fluconazole', 17, 1, 'Fluconazole', 'Oral antifungal tablet', '2025-12-30 03:30:15'),
(45, 'Acyclovir', 18, 1, 'Acyclovir', 'Antiviral tablet', '2025-12-30 03:30:15'),
(46, 'Valtrex', 18, 1, 'Valacyclovir', 'Antiviral tablet', '2025-12-30 03:30:15'),
(47, 'Dettol', 19, 6, 'Chloroxylenol', 'Antiseptic solution', '2025-12-30 03:30:15'),
(48, 'Savlon', 19, 6, 'Cetrimide + Chlorhexidine', 'Antiseptic cream', '2025-12-30 03:30:15'),
(49, 'Sensodyne', 20, 6, 'Fluoride', 'Toothpaste for sensitive teeth', '2025-12-30 03:30:15'),
(50, 'Listerine', 20, 2, 'Essential Oils', 'Mouthwash for oral hygiene', '2025-12-30 03:30:15'),
(51, 'Zovirax', 21, 6, 'Acyclovir', 'Cream for cold sores', '2025-12-30 03:30:15'),
(52, 'Blistex', 21, 6, 'Petrolatum', 'Lip balm for protection', '2025-12-30 03:30:15'),
(53, 'Dormicum', 22, 1, 'Midazolam', 'Sleep aid tablet', '2025-12-30 03:30:15'),
(54, 'Melatonin', 22, 1, 'Melatonin', 'Sleep supplement tablet', '2025-12-30 03:30:15'),
(55, 'Sertraline', 23, 1, 'Sertraline', 'Antidepressant tablet', '2025-12-30 03:30:15'),
(56, 'Fluoxetine', 23, 1, 'Fluoxetine', 'Antidepressant tablet', '2025-12-30 03:30:15'),
(57, 'Euthyrox', 24, 1, 'Levothyroxine', 'Thyroid hormone tablet', '2025-12-30 03:30:15'),
(58, 'Thyronorm', 24, 1, 'Levothyroxine', 'Thyroid hormone tablet', '2025-12-30 03:30:15'),
(59, 'Orlistat', 25, 1, 'Orlistat', 'Weight management tablet', '2025-12-30 03:30:15'),
(60, 'Alli', 25, 1, 'Orlistat', 'Over-the-counter weight loss tablet', '2025-12-30 03:30:15'),
(61, 'Caltrate', 26, 1, 'Calcium + Vitamin D', 'Bone health tablet', '2025-12-30 03:30:15'),
(62, 'OsteoCal', 26, 1, 'Calcium + Vitamin D', 'Bone supplement tablet', '2025-12-30 03:30:15'),
(63, 'Immunace', 27, 1, 'Multivitamins', 'Immunity booster tablet', '2025-12-30 03:30:15'),
(64, 'VitaBoost', 27, 1, 'Vitamin C', 'Vitamin C supplement tablet', '2025-12-30 03:30:15'),
(65, 'Regaine', 28, 6, 'Minoxidil', 'Hair growth solution', '2025-12-30 03:30:15'),
(66, 'HairMax', 28, 6, 'Biotin', 'Hair growth supplement', '2025-12-30 03:30:15'),
(67, 'Otrivin', 29, 2, 'Xylometazoline', 'Nasal spray', '2025-12-30 03:30:15'),
(68, 'Strepsils', 29, 2, 'Flurbiprofen', 'Throat lozenge', '2025-12-30 03:30:15'),
(69, 'Gaviscon', 30, 2, 'Alginic Acid', 'Antacid syrup', '2025-12-30 03:30:15'),
(70, 'Maalox', 30, 2, 'Aluminum Hydroxide + Magnesium Hydroxide', 'Acidity relief syrup', '2025-12-30 03:30:15'),
(71, 'Fever / Temperature Injection', 1, 3, 'Fever / Temperature Injection', 'Injection form of Fever / Temperature', '2026-01-01 01:56:09'),
(72, 'Cough Injection', 2, 3, 'Cough Injection', 'Injection form of Cough', '2026-01-01 01:56:09'),
(73, 'Painkiller Injection', 3, 3, 'Painkiller Injection', 'Injection form of Painkiller', '2026-01-01 01:56:09'),
(74, 'Antibiotic Injection', 4, 3, 'Antibiotic Injection', 'Injection form of Antibiotic', '2026-01-01 01:56:09'),
(75, 'Allergy Injection', 5, 3, 'Allergy Injection', 'Injection form of Allergy', '2026-01-01 01:56:09'),
(76, 'Fever / Temperature Injection', 6, 3, 'Fever / Temperature Injection', 'Injection form of Fever / Temperature', '2026-01-01 01:56:09'),
(77, 'Cough Injection', 7, 3, 'Cough Injection', 'Injection form of Cough', '2026-01-01 01:56:09'),
(78, 'Painkiller Injection', 8, 3, 'Painkiller Injection', 'Injection form of Painkiller', '2026-01-01 01:56:09'),
(79, 'Antibiotic Injection', 9, 3, 'Antibiotic Injection', 'Injection form of Antibiotic', '2026-01-01 01:56:09'),
(80, 'Allergy Injection', 10, 3, 'Allergy Injection', 'Injection form of Allergy', '2026-01-01 01:56:09'),
(81, 'Cold & Flu Injection', 11, 3, 'Cold & Flu Injection', 'Injection form of Cold & Flu', '2026-01-01 01:56:09'),
(82, 'Digestive Health Injection', 12, 3, 'Digestive Health Injection', 'Injection form of Digestive Health', '2026-01-01 01:56:09'),
(83, 'Vitamins & Supplements Injection', 13, 3, 'Vitamins & Supplements Injection', 'Injection form of Vitamins & Supplements', '2026-01-01 01:56:09'),
(84, 'Skin Care Injection', 14, 3, 'Skin Care Injection', 'Injection form of Skin Care', '2026-01-01 01:56:09'),
(85, 'Eye Care Injection', 15, 3, 'Eye Care Injection', 'Injection form of Eye Care', '2026-01-01 01:56:09'),
(86, 'Ear Care Injection', 16, 3, 'Ear Care Injection', 'Injection form of Ear Care', '2026-01-01 01:56:09'),
(87, 'Heart & Blood Pressure Injection', 17, 3, 'Heart & Blood Pressure Injection', 'Injection form of Heart & Blood Pressure', '2026-01-01 01:56:09'),
(88, 'Diabetes Injection', 18, 3, 'Diabetes Injection', 'Injection form of Diabetes', '2026-01-01 01:56:09'),
(89, 'Respiratory Injection', 19, 3, 'Respiratory Injection', 'Injection form of Respiratory', '2026-01-01 01:56:09'),
(90, 'Pain Relief Injection', 20, 3, 'Pain Relief Injection', 'Injection form of Pain Relief', '2026-01-01 01:56:09'),
(91, 'Anti-inflammatory Injection', 21, 3, 'Anti-inflammatory Injection', 'Injection form of Anti-inflammatory', '2026-01-01 01:56:09'),
(92, 'Antifungal Injection', 22, 3, 'Antifungal Injection', 'Injection form of Antifungal', '2026-01-01 01:56:09'),
(93, 'Antiviral Injection', 23, 3, 'Antiviral Injection', 'Injection form of Antiviral', '2026-01-01 01:56:09'),
(94, 'Antiseptic Injection', 24, 3, 'Antiseptic Injection', 'Injection form of Antiseptic', '2026-01-01 01:56:09'),
(95, 'Oral Care Injection', 25, 3, 'Oral Care Injection', 'Injection form of Oral Care', '2026-01-01 01:56:09'),
(96, 'Cold Sores & Lip Care Injection', 26, 3, 'Cold Sores & Lip Care Injection', 'Injection form of Cold Sores & Lip Care', '2026-01-01 01:56:09'),
(97, 'Sleep & Relaxation Injection', 27, 3, 'Sleep & Relaxation Injection', 'Injection form of Sleep & Relaxation', '2026-01-01 01:56:09'),
(98, 'Mental Health Injection', 28, 3, 'Mental Health Injection', 'Injection form of Mental Health', '2026-01-01 01:56:09'),
(99, 'Hormones & Thyroid Injection', 29, 3, 'Hormones & Thyroid Injection', 'Injection form of Hormones & Thyroid', '2026-01-01 01:56:09'),
(100, 'Weight Management Injection', 30, 3, 'Weight Management Injection', 'Injection form of Weight Management', '2026-01-01 01:56:09'),
(101, 'Bone & Joint Health Injection', 31, 3, 'Bone & Joint Health Injection', 'Injection form of Bone & Joint Health', '2026-01-01 01:56:09'),
(102, 'Immunity Boosters Injection', 32, 3, 'Immunity Boosters Injection', 'Injection form of Immunity Boosters', '2026-01-01 01:56:09'),
(103, 'Hair Care Injection', 33, 3, 'Hair Care Injection', 'Injection form of Hair Care', '2026-01-01 01:56:09'),
(104, 'Ear, Nose & Throat Injection', 34, 3, 'Ear, Nose & Throat Injection', 'Injection form of Ear, Nose & Throat', '2026-01-01 01:56:09'),
(105, 'Antacids & Stomach Relief Injection', 35, 3, 'Antacids & Stomach Relief Injection', 'Injection form of Antacids & Stomach Relief', '2026-01-01 01:56:09'),
(106, 'Cold & Allergy Relief Injection', 36, 3, 'Cold & Allergy Relief Injection', 'Injection form of Cold & Allergy Relief', '2026-01-01 01:56:09'),
(134, 'Fever / Temperature Drip', 1, 4, 'Fever / Temperature Drip', 'Drip form of Fever / Temperature', '2026-01-01 01:56:09'),
(135, 'Cough Drip', 2, 4, 'Cough Drip', 'Drip form of Cough', '2026-01-01 01:56:09'),
(136, 'Painkiller Drip', 3, 4, 'Painkiller Drip', 'Drip form of Painkiller', '2026-01-01 01:56:09'),
(137, 'Antibiotic Drip', 4, 4, 'Antibiotic Drip', 'Drip form of Antibiotic', '2026-01-01 01:56:09'),
(138, 'Allergy Drip', 5, 4, 'Allergy Drip', 'Drip form of Allergy', '2026-01-01 01:56:09'),
(139, 'Fever / Temperature Drip', 6, 4, 'Fever / Temperature Drip', 'Drip form of Fever / Temperature', '2026-01-01 01:56:09'),
(140, 'Cough Drip', 7, 4, 'Cough Drip', 'Drip form of Cough', '2026-01-01 01:56:09'),
(141, 'Painkiller Drip', 8, 4, 'Painkiller Drip', 'Drip form of Painkiller', '2026-01-01 01:56:09'),
(142, 'Antibiotic Drip', 9, 4, 'Antibiotic Drip', 'Drip form of Antibiotic', '2026-01-01 01:56:09'),
(143, 'Allergy Drip', 10, 4, 'Allergy Drip', 'Drip form of Allergy', '2026-01-01 01:56:09'),
(144, 'Cold & Flu Drip', 11, 4, 'Cold & Flu Drip', 'Drip form of Cold & Flu', '2026-01-01 01:56:09'),
(145, 'Digestive Health Drip', 12, 4, 'Digestive Health Drip', 'Drip form of Digestive Health', '2026-01-01 01:56:09'),
(146, 'Vitamins & Supplements Drip', 13, 4, 'Vitamins & Supplements Drip', 'Drip form of Vitamins & Supplements', '2026-01-01 01:56:09'),
(147, 'Skin Care Drip', 14, 4, 'Skin Care Drip', 'Drip form of Skin Care', '2026-01-01 01:56:09'),
(148, 'Eye Care Drip', 15, 4, 'Eye Care Drip', 'Drip form of Eye Care', '2026-01-01 01:56:09'),
(149, 'Ear Care Drip', 16, 4, 'Ear Care Drip', 'Drip form of Ear Care', '2026-01-01 01:56:09'),
(150, 'Heart & Blood Pressure Drip', 17, 4, 'Heart & Blood Pressure Drip', 'Drip form of Heart & Blood Pressure', '2026-01-01 01:56:09'),
(151, 'Diabetes Drip', 18, 4, 'Diabetes Drip', 'Drip form of Diabetes', '2026-01-01 01:56:09'),
(152, 'Respiratory Drip', 19, 4, 'Respiratory Drip', 'Drip form of Respiratory', '2026-01-01 01:56:09'),
(153, 'Pain Relief Drip', 20, 4, 'Pain Relief Drip', 'Drip form of Pain Relief', '2026-01-01 01:56:09'),
(154, 'Anti-inflammatory Drip', 21, 4, 'Anti-inflammatory Drip', 'Drip form of Anti-inflammatory', '2026-01-01 01:56:09'),
(155, 'Antifungal Drip', 22, 4, 'Antifungal Drip', 'Drip form of Antifungal', '2026-01-01 01:56:09'),
(156, 'Antiviral Drip', 23, 4, 'Antiviral Drip', 'Drip form of Antiviral', '2026-01-01 01:56:09'),
(157, 'Antiseptic Drip', 24, 4, 'Antiseptic Drip', 'Drip form of Antiseptic', '2026-01-01 01:56:09'),
(158, 'Oral Care Drip', 25, 4, 'Oral Care Drip', 'Drip form of Oral Care', '2026-01-01 01:56:09'),
(159, 'Cold Sores & Lip Care Drip', 26, 4, 'Cold Sores & Lip Care Drip', 'Drip form of Cold Sores & Lip Care', '2026-01-01 01:56:09'),
(160, 'Sleep & Relaxation Drip', 27, 4, 'Sleep & Relaxation Drip', 'Drip form of Sleep & Relaxation', '2026-01-01 01:56:09'),
(161, 'Mental Health Drip', 28, 4, 'Mental Health Drip', 'Drip form of Mental Health', '2026-01-01 01:56:09'),
(162, 'Hormones & Thyroid Drip', 29, 4, 'Hormones & Thyroid Drip', 'Drip form of Hormones & Thyroid', '2026-01-01 01:56:09'),
(163, 'Weight Management Drip', 30, 4, 'Weight Management Drip', 'Drip form of Weight Management', '2026-01-01 01:56:09'),
(164, 'Bone & Joint Health Drip', 31, 4, 'Bone & Joint Health Drip', 'Drip form of Bone & Joint Health', '2026-01-01 01:56:09'),
(165, 'Immunity Boosters Drip', 32, 4, 'Immunity Boosters Drip', 'Drip form of Immunity Boosters', '2026-01-01 01:56:09'),
(166, 'Hair Care Drip', 33, 4, 'Hair Care Drip', 'Drip form of Hair Care', '2026-01-01 01:56:09'),
(167, 'Ear, Nose & Throat Drip', 34, 4, 'Ear, Nose & Throat Drip', 'Drip form of Ear, Nose & Throat', '2026-01-01 01:56:09'),
(168, 'Antacids & Stomach Relief Drip', 35, 4, 'Antacids & Stomach Relief Drip', 'Drip form of Antacids & Stomach Relief', '2026-01-01 01:56:09'),
(169, 'Cold & Allergy Relief Drip', 36, 4, 'Cold & Allergy Relief Drip', 'Drip form of Cold & Allergy Relief', '2026-01-01 01:56:09'),
(197, 'Canesten', 22, 1, 'Clotrimazole', 'Treats fungal infections', '2026-01-01 02:06:15'),
(198, 'Lamisil', 22, 1, 'Terbinafine', 'Treats skin and nail fungal infections', '2026-01-01 02:06:15'),
(199, 'Nizoral', 22, 2, 'Ketoconazole', 'Topical cream for fungal infections', '2026-01-01 02:06:15'),
(200, 'Diflucan', 22, 1, 'Fluconazole', 'Oral antifungal for various infections', '2026-01-01 02:06:15'),
(201, 'Lotrimin', 22, 2, 'Clotrimazole', 'Topical antifungal for skin infections', '2026-01-01 02:06:15'),
(202, 'Tinactin', 22, 2, 'Tolnaftate', 'Powder for athlete’s foot', '2026-01-01 02:06:15'),
(203, 'Micatin', 22, 2, 'Miconazole', 'Topical cream for skin fungal infection', '2026-01-01 02:06:15'),
(204, 'Mapanol', 3, 1, 'Paracetamol', 'A common paracetamol-based analgesic and antipyretic medication used for pain relief and fever reduction', '2026-01-01 23:09:06'),
(205, 'Mapanol', 20, 2, 'Paracetamol', 'relief from pain and also the headcahe', '2026-01-05 21:35:10'),
(206, 'Metodine', 12, 1, 'Fluconazole', 'relief from pain in stoamach etc', '2026-01-05 21:40:17');

-- --------------------------------------------------------

--
-- Table structure for table `medicine_categories`
--

CREATE TABLE `medicine_categories` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `medicine_categories`
--

INSERT INTO `medicine_categories` (`id`, `name`, `description`) VALUES
(1, 'Fever / Temperature', 'Medicines used to reduce fever or regulate temperature'),
(2, 'Cough', 'Medicines for cough relief'),
(3, 'Painkiller', 'Pain relief medicines'),
(4, 'Antibiotic', 'Antibiotics for infections'),
(5, 'Allergy', 'Medicines for allergies'),
(6, 'Fever / Temperature', 'Medicines used to reduce fever or regulate temperature'),
(7, 'Cough', 'Medicines for cough relief'),
(8, 'Painkiller', 'Pain relief medicines'),
(9, 'Antibiotic', 'Antibiotics for infections'),
(10, 'Allergy', 'Medicines for allergies'),
(11, 'Cold & Flu', 'Medicines for cold and flu symptoms'),
(12, 'Digestive Health', 'Medicines for stomach, digestion, and acidity'),
(13, 'Vitamins & Supplements', 'Vitamins and dietary supplements'),
(14, 'Skin Care', 'Medicines and creams for skin issues'),
(15, 'Eye Care', 'Medicines for eye-related problems'),
(16, 'Ear Care', 'Medicines for ear infections and care'),
(17, 'Heart & Blood Pressure', 'Medicines for heart and hypertension'),
(18, 'Diabetes', 'Medicines to manage blood sugar levels'),
(19, 'Respiratory', 'Medicines for asthma and breathing issues'),
(20, 'Pain Relief', 'Muscle pain, joint pain, and general analgesics'),
(21, 'Anti-inflammatory', 'Medicines to reduce inflammation'),
(22, 'Antifungal', 'Medicines for fungal infections'),
(23, 'Antiviral', 'Medicines for viral infections'),
(24, 'Antiseptic', 'Disinfectants and topical antiseptics'),
(25, 'Oral Care', 'Toothpaste, mouthwash, and dental medicines'),
(26, 'Cold Sores & Lip Care', 'Medicines for cold sores and lip problems'),
(27, 'Sleep & Relaxation', 'Medicines for insomnia and relaxation'),
(28, 'Mental Health', 'Medicines for anxiety, depression, and mood'),
(29, 'Hormones & Thyroid', 'Medicines for hormonal and thyroid issues'),
(30, 'Weight Management', 'Supplements and medicines for weight control'),
(31, 'Bone & Joint Health', 'Calcium, arthritis, and bone medicines'),
(32, 'Immunity Boosters', 'Medicines to strengthen immunity'),
(33, 'Hair Care', 'Medicines for hair growth and scalp care'),
(34, 'Ear, Nose & Throat', 'ENT-related medicines'),
(35, 'Antacids & Stomach Relief', 'Medicines for acidity and gastritis'),
(36, 'Cold & Allergy Relief', 'Combined medicines for cold and allergy');

-- --------------------------------------------------------

--
-- Table structure for table `medicine_types`
--

CREATE TABLE `medicine_types` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `medicine_types`
--

INSERT INTO `medicine_types` (`id`, `name`, `description`) VALUES
(1, 'Tablet', 'Oral tablets'),
(2, 'Syrup', 'Liquid syrup'),
(3, 'Injection', 'Injectable medicines'),
(4, 'Drip', 'IV drip solutions'),
(5, 'Capsule', 'Capsule form'),
(6, 'Cream', 'Topical creams'),
(7, 'Inhaler', 'Respiratory  issues');

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` int(11) NOT NULL,
  `payment_date` datetime DEFAULT current_timestamp(),
  `payment_type` enum('sale','return_to_company') NOT NULL,
  `reference_id` int(11) NOT NULL COMMENT 'sale_id or return_id',
  `invoice_no` varchar(50) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_method` enum('Cash','Online','Bank Transfer','Card') DEFAULT 'Cash',
  `payment_status` enum('pending','completed','cancelled') DEFAULT 'completed',
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL COMMENT 'pharmacist_id who created the payment',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_auto_generated` tinyint(1) DEFAULT 0,
  `auto_generated_from` varchar(50) DEFAULT NULL,
  `transaction_net_amount` decimal(10,2) DEFAULT 0.00,
  `transaction_discount` decimal(10,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`id`, `payment_date`, `payment_type`, `reference_id`, `invoice_no`, `amount`, `payment_method`, `payment_status`, `notes`, `created_by`, `created_at`, `updated_at`, `is_auto_generated`, `auto_generated_from`, `transaction_net_amount`, `transaction_discount`) VALUES
(1, '2026-01-07 23:12:21', 'sale', 23, 'INV-2026010008', 250.00, 'Cash', 'completed', 'Auto-generated payment for sale', 6, '2026-01-07 23:12:23', '2026-01-07 23:12:23', 1, 'SALES_SYSTEM', 250.00, 0.00),
(2, '2026-01-06 21:56:35', 'return_to_company', 5, 'RET-PAY-000005', 650.00, 'Cash', 'completed', 'Auto-generated payment for return to company', 6, '2026-01-07 23:32:01', '2026-01-07 23:32:01', 1, 'RETURNS_SYSTEM', 650.00, 0.00),
(3, '2026-01-08 02:01:44', 'sale', 24, 'INV-2026010009', 2580.00, 'Cash', 'completed', 'Auto-generated payment for sale', 6, '2026-01-08 02:01:44', '2026-01-08 02:01:44', 1, 'SALES_SYSTEM', 2580.00, 210.00),
(4, '2026-01-06 21:35:19', 'return_to_company', 4, 'RET-PAY-000004', 3600.00, 'Cash', 'completed', 'Auto-generated payment for return to company', 6, '2026-01-08 02:06:33', '2026-01-08 02:06:33', 1, 'RETURNS_SYSTEM', 3600.00, 0.00);

-- --------------------------------------------------------

--
-- Table structure for table `returns_to_company`
--

CREATE TABLE `returns_to_company` (
  `id` int(11) NOT NULL,
  `batch_id` int(11) DEFAULT NULL,
  `medicine_id` int(11) DEFAULT NULL,
  `batch_no` varchar(50) DEFAULT NULL,
  `quantity` int(11) DEFAULT NULL,
  `purchase_price` decimal(10,2) DEFAULT NULL,
  `return_reason` varchar(255) DEFAULT NULL,
  `return_notes` text DEFAULT NULL,
  `returned_by` int(11) DEFAULT NULL,
  `returned_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `return_amount` decimal(10,2) DEFAULT 0.00,
  `total_price` decimal(10,2) GENERATED ALWAYS AS (`quantity` * `purchase_price`) STORED
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `returns_to_company`
--

INSERT INTO `returns_to_company` (`id`, `batch_id`, `medicine_id`, `batch_no`, `quantity`, `purchase_price`, `return_reason`, `return_notes`, `returned_by`, `returned_at`, `created_at`, `return_amount`) VALUES
(1, 251, 12, 'IBU-2203-001', 120, 130.00, 'Expired', '', 6, '2026-01-06 03:05:06', '2026-01-06 11:05:06', 15600.00),
(2, 251, 12, 'IBU-2203-001', 120, 130.00, 'Expired', '', 6, '2026-01-06 03:05:27', '2026-01-06 11:05:27', 15600.00),
(3, 251, 12, 'IBU-2203-001', 120, 130.00, 'Expired', '', 6, '2026-01-06 21:15:05', '2026-01-07 05:15:05', 15600.00),
(4, 223, 3, 'COU-2303-001', 150, 24.00, 'Expired', '', 6, '2026-01-06 21:35:19', '2026-01-07 05:35:19', 3600.00),
(5, 249, 1, 'PAN-2206-001', 50, 13.00, 'Expired', 'this medicnes are expried and ready to return', 6, '2026-01-06 21:56:35', '2026-01-07 05:56:35', 650.00),
(6, 252, 19, 'ZYR-2211-001', 90, 14.00, 'Expired', '', 6, '2026-01-07 02:57:18', '2026-01-07 10:57:18', 650.00);

-- --------------------------------------------------------

--
-- Table structure for table `sales`
--

CREATE TABLE `sales` (
  `id` int(11) NOT NULL,
  `invoice_no` varchar(50) NOT NULL,
  `pharmacist_id` int(11) NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `discount` decimal(10,2) DEFAULT 0.00,
  `payment_method` enum('Cash','Online') DEFAULT 'Cash',
  `sale_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sales`
--

INSERT INTO `sales` (`id`, `invoice_no`, `pharmacist_id`, `total_amount`, `discount`, `payment_method`, `sale_date`) VALUES
(8, 'INV-1001', 6, 1000.00, 50.00, 'Cash', '2026-01-01 07:13:49'),
(9, 'INV-2026010001', 6, 1350.00, 50.00, 'Cash', '2026-01-01 07:48:24'),
(14, 'INV-2026010000', 6, 250.00, 12.00, 'Cash', '2026-01-01 09:30:31'),
(15, 'INV-2026010002', 6, 100.00, 55.00, 'Cash', '2026-01-01 11:20:20'),
(16, 'INV-2026010003', 6, 937.50, 21.00, 'Cash', '2026-01-02 05:19:09'),
(19, 'INV-2026010004', 6, 950.00, 50.00, 'Cash', '2026-01-06 06:29:22'),
(20, 'INV-2026010005', 6, 1368.00, 72.00, 'Cash', '2026-01-06 10:08:42'),
(21, 'INV-2026010006', 6, 10.00, 0.00, 'Cash', '2026-01-07 09:16:11'),
(22, 'INV-2026010007', 6, 12960.00, 1440.00, 'Cash', '2026-01-08 06:41:58'),
(23, 'INV-2026010008', 6, 250.00, 0.00, 'Cash', '2026-01-08 07:12:21'),
(24, 'INV-2026010009', 6, 2790.00, 210.00, 'Cash', '2026-01-08 10:01:44');

-- --------------------------------------------------------

--
-- Table structure for table `sale_items`
--

CREATE TABLE `sale_items` (
  `id` int(11) NOT NULL,
  `sale_id` int(11) NOT NULL,
  `medicine_id` int(11) NOT NULL,
  `batch_id` int(11) DEFAULT NULL,
  `quantity` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `discount` decimal(10,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sale_items`
--

INSERT INTO `sale_items` (`id`, `sale_id`, `medicine_id`, `batch_id`, `quantity`, `price`, `discount`) VALUES
(15, 8, 1, NULL, 2, 200.00, 20.00),
(16, 8, 2, NULL, 3, 150.00, 10.00),
(17, 9, 18, NULL, 10, 25.00, 0.00),
(18, 9, 13, NULL, 10, 110.00, 0.00),
(19, 14, 17, NULL, 10, 25.00, 0.00),
(21, 15, 18, NULL, 4, 25.00, 0.00),
(22, 16, 2, NULL, 50, 18.75, 0.00),
(23, 19, 5, 225, 100, 10.00, 50.00),
(24, 20, 206, 275, 10, 144.00, 72.00),
(25, 21, 5, 225, 1, 10.00, 0.00),
(26, 22, 138, 274, 10, 1440.00, 1440.00),
(27, 23, 5, 225, 25, 10.00, 0.00),
(28, 24, 3, 222, 100, 30.00, 210.00);

-- --------------------------------------------------------

--
-- Table structure for table `stock_batches`
--

CREATE TABLE `stock_batches` (
  `id` int(11) NOT NULL,
  `medicine_id` int(11) NOT NULL,
  `batch_no` varchar(50) DEFAULT NULL,
  `quantity` int(11) NOT NULL,
  `units_per_packet` int(11) NOT NULL DEFAULT 10,
  `packets_per_box` int(11) NOT NULL DEFAULT 10,
  `purchase_price` decimal(10,2) DEFAULT NULL,
  `selling_price` decimal(10,2) DEFAULT NULL,
  `mrp` decimal(10,2) DEFAULT NULL,
  `supplier_id` int(11) DEFAULT NULL,
  `received_date` date DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `location` varchar(50) DEFAULT NULL,
  `is_expired` tinyint(1) DEFAULT 0,
  `added_at` datetime DEFAULT current_timestamp(),
  `is_returned` tinyint(1) DEFAULT 0,
  `returned_at` datetime DEFAULT NULL,
  `is_disposed` tinyint(1) DEFAULT 0,
  `disposed_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `stock_batches`
--

INSERT INTO `stock_batches` (`id`, `medicine_id`, `batch_no`, `quantity`, `units_per_packet`, `packets_per_box`, `purchase_price`, `selling_price`, `mrp`, `supplier_id`, `received_date`, `expiry_date`, `location`, `is_expired`, `added_at`, `is_returned`, `returned_at`, `is_disposed`, `disposed_at`) VALUES
(217, 1, 'PAN-2501-001', 1000, 10, 10, 15.00, 20.00, 25.00, 1, '2025-01-25', '2026-12-25', 'Shelf-A1', 0, '2026-01-02 01:59:32', 0, NULL, 0, NULL),
(220, 2, 'CAL-2502-001', 1000, 10, 10, 18.00, 22.00, 28.00, 1, '2025-02-10', '2027-02-10', 'Shelf-B1', 0, '2026-01-02 01:59:32', 0, NULL, 0, NULL),
(221, 2, 'CAL-2406-001', 200, 10, 10, 17.00, 21.00, 26.00, 1, '2024-06-15', '2026-01-17', 'Shelf-B2', 0, '2026-01-02 01:59:32', 0, NULL, 0, NULL),
(222, 3, 'COU-2503-001', 900, 10, 10, 25.00, 30.00, 35.00, 2, '2025-03-05', '2026-09-05', 'Shelf-C1', 0, '2026-01-02 01:59:32', 0, NULL, 0, NULL),
(223, 3, 'COU-2303-001', 150, 10, 10, 24.00, 29.00, 34.00, 2, '2023-03-10', '2024-03-10', 'Quarantine', 1, '2026-01-02 01:59:32', 1, '2026-01-06 21:35:19', 0, NULL),
(224, 4, 'AMO-2504-001', 1000, 10, 10, 12.00, 15.00, 18.00, 2, '2025-04-12', '2027-04-12', 'Shelf-D1', 0, '2026-01-02 01:59:32', 0, NULL, 0, NULL),
(225, 5, 'ALL-2505-001', 874, 10, 10, 8.00, 10.00, 12.00, 1, '2025-05-20', '2027-05-20', 'Shelf-E1', 0, '2026-01-02 01:59:32', 0, NULL, 0, NULL),
(226, 5, 'ALL-2408-001', 300, 10, 10, 7.50, 9.50, 11.50, 1, '2024-08-10', '2026-03-03', 'Shelf-E2', 0, '2026-01-02 01:59:32', 0, NULL, 0, NULL),
(227, 7, 'CAL-2507-001', 800, 10, 10, 19.00, 23.00, 29.00, 1, '2025-07-01', '2027-07-01', 'Shelf-F1', 0, '2026-01-02 01:59:32', 0, NULL, 0, NULL),
(229, 9, 'COU-2509-001', 700, 10, 10, 26.00, 31.00, 36.00, 2, '2025-09-10', '2027-03-10', 'Shelf-H1', 0, '2026-01-02 01:59:32', 0, NULL, 0, NULL),
(230, 10, 'BEN-2510-001', 1000, 10, 10, 0.00, 0.00, 0.00, 2, '2025-10-03', '2026-10-03', 'Shelf-I1', 0, '2026-01-02 01:59:32', 0, NULL, 0, NULL),
(231, 11, 'DEL-2511-001', 1000, 10, 10, 32.00, 38.00, 45.00, 2, '2025-11-18', '2027-05-18', 'Shelf-J1', 0, '2026-01-02 01:59:32', 0, NULL, 0, NULL),
(232, 12, 'IBU-2512-001', 1000, 10, 10, 9.00, 12.00, 15.00, 1, '2025-12-01', '2027-06-01', 'Shelf-K1', 0, '2026-01-02 01:59:32', 0, NULL, 0, NULL),
(233, 13, 'BRU-2513-001', 1000, 10, 10, 10.00, 13.00, 16.00, 1, '2026-01-10', '2028-01-10', 'Shelf-L1', 0, '2026-01-02 01:59:32', 0, NULL, 0, NULL),
(234, 13, 'BRU-2301-001', 75, 10, 10, 9.00, 12.00, 15.00, 1, '2023-01-20', '2024-07-20', 'Quarantine', 1, '2026-01-02 01:59:32', 0, NULL, 0, NULL),
(235, 14, 'MOT-2514-001', 1000, 10, 10, 11.00, 14.00, 17.00, 1, '2026-02-15', '2028-02-15', 'Shelf-M1', 0, '2026-01-02 01:59:32', 0, NULL, 0, NULL),
(236, 15, 'AMO-2515-001', 600, 10, 10, 13.00, 16.00, 19.00, 2, '2026-03-20', '2028-03-20', 'Shelf-N1', 0, '2026-01-02 01:59:32', 0, NULL, 0, NULL),
(237, 16, 'MOX-2516-001', 1000, 10, 10, 45.00, 55.00, 65.00, 2, '2026-04-20', '2028-04-20', 'Shelf-O1', 0, '2026-01-02 01:59:32', 0, NULL, 0, NULL),
(238, 17, 'AUG-2517-001', 1000, 10, 10, 50.00, 60.00, 70.00, 2, '2026-05-05', '2028-05-05', 'Shelf-P1', 0, '2026-01-02 01:59:32', 0, NULL, 0, NULL),
(239, 18, 'ALL-2518-001', 500, 10, 10, 8.50, 10.50, 12.50, 1, '2026-06-15', '2028-06-15', 'Shelf-Q1', 0, '2026-01-02 01:59:32', 0, NULL, 0, NULL),
(240, 19, 'ZYR-2519-001', 1000, 10, 10, 15.00, 18.00, 22.00, 1, '2026-07-10', '2028-07-10', 'Shelf-R1', 0, '2026-01-02 01:59:32', 0, NULL, 0, NULL),
(241, 20, 'CLA-2520-001', 1000, 10, 10, 20.00, 25.00, 30.00, 1, '2026-08-15', '2028-08-15', 'Shelf-S1', 0, '2026-01-02 01:59:32', 0, NULL, 0, NULL),
(242, 20, 'CLA-2409-001', 250, 10, 10, 19.00, 24.00, 29.00, 1, '2024-09-01', '2026-02-16', 'Shelf-S2', 0, '2026-01-02 01:59:32', 0, NULL, 0, NULL),
(243, 21, 'FLU-2521-001', 1000, 10, 10, 35.00, 42.00, 50.00, 2, '2026-09-20', '2027-09-20', 'Shelf-T1', 0, '2026-01-02 01:59:32', 0, NULL, 0, NULL),
(244, 22, 'COL-2522-001', 1000, 10, 10, 30.00, 36.00, 43.00, 2, '2026-10-05', '2028-04-05', 'Shelf-U1', 0, '2026-01-02 01:59:32', 0, NULL, 0, NULL),
(245, 23, 'GAV-2523-001', 1000, 10, 10, 22.00, 28.00, 34.00, 1, '2026-11-12', '2028-05-12', 'Shelf-V1', 0, '2026-01-02 01:59:32', 0, NULL, 0, NULL),
(246, 24, 'MAA-2524-001', 1000, 10, 10, 20.00, 25.00, 30.00, 1, '2026-12-01', '2028-06-01', 'Shelf-W1', 0, '2026-01-02 01:59:32', 0, NULL, 0, NULL),
(247, 25, 'CEN-2525-001', 1000, 10, 10, 40.00, 50.00, 60.00, 1, '2027-01-15', '2029-01-15', 'Shelf-X1', 0, '2026-01-02 01:59:32', 0, NULL, 0, NULL),
(248, 26, 'NEU-2526-001', 1000, 10, 10, 25.00, 30.00, 36.00, 1, '2027-02-20', '2029-02-20', 'Shelf-Y1', 0, '2026-01-02 01:59:32', 0, NULL, 0, NULL),
(249, 1, 'PAN-2206-001', 50, 10, 10, 13.00, 17.00, 21.00, 1, '2022-06-10', '2023-12-10', 'Quarantine', 1, '2026-01-02 01:59:32', 1, '2026-01-06 21:56:35', 0, NULL),
(251, 12, 'IBU-2203-001', 120, 10, 10, 130.00, 145.00, 170.00, 1, '2022-03-15', '2023-09-15', 'Quarantine', 1, '2026-01-02 01:59:32', 1, '2026-01-06 21:15:05', 0, NULL),
(252, 19, 'ZYR-2211-001', 90, 10, 10, 14.00, 17.00, 21.00, 1, '2022-11-20', '2023-11-20', 'Quarantine', 1, '2026-01-02 01:59:32', 1, '2026-01-07 02:57:18', 0, NULL),
(253, 1, 'PAN-2503-003', 20, 10, 10, 16.00, 21.00, 26.00, 1, '2025-03-15', '2026-09-15', 'Shelf-A3', 0, '2026-01-02 01:59:32', 0, NULL, 0, NULL),
(254, 2, 'CAL-2503-002', 15, 10, 10, 18.50, 22.50, 28.50, 1, '2025-03-20', '2027-01-20', 'Shelf-B3', 0, '2026-01-02 01:59:32', 0, NULL, 0, NULL),
(255, 4, 'AMO-2505-002', 30, 10, 10, 12.50, 15.50, 18.50, 2, '2025-05-25', '2027-05-25', 'Shelf-D2', 0, '2026-01-02 01:59:32', 0, NULL, 0, NULL),
(256, 1, 'PAN-2412-001', 400, 10, 10, 14.75, 19.50, 24.50, 1, '2024-12-01', '2026-06-01', 'Shelf-A4', 0, '2026-01-02 01:59:32', 0, NULL, 0, NULL),
(257, 1, 'PAN-2506-001', 600, 10, 10, 15.25, 20.25, 25.25, 1, '2025-06-01', '2027-06-01', 'Shelf-A5', 0, '2026-01-02 01:59:32', 0, NULL, 0, NULL),
(258, 2, 'CAL-2411-001', 350, 10, 10, 17.50, 21.50, 27.00, 1, '2024-11-15', '2026-11-15', 'Shelf-B4', 0, '2026-01-02 01:59:32', 0, NULL, 0, NULL),
(274, 138, 'ADS-202601-002', 510, 10, 10, 1200.00, 1440.00, 1560.00, 3, '2026-01-06', '2027-01-06', 'Shelf B-4', 0, '2026-01-05 23:34:37', 0, NULL, 0, NULL),
(275, 206, 'MET-0126-001', 240, 10, 10, 120.00, 144.00, 156.00, 7, '2026-01-06', '2030-11-20', 'shelf B', 0, '2026-01-05 23:47:17', 0, NULL, 0, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `suppliers`
--

CREATE TABLE `suppliers` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `suppliers`
--

INSERT INTO `suppliers` (`id`, `name`, `phone`, `email`, `address`, `created_at`) VALUES
(1, 'HealthPlus Pharma', '0300-1234567', 'contact@healthplus.com', 'Karachi, Pakistan', '2025-12-30 07:37:04'),
(2, 'MedSupply Co.', '0311-7654321', 'info@medsupply.com', 'Lahore, Pakistan', '2025-12-30 07:37:04'),
(3, 'PharmaCare Distributors', '0321-2345678', 'orders@pharmacare.pk', 'Suite 101, Commercial Area, Islamabad, Pakistan', '2026-01-06 06:41:06'),
(4, 'MediLife Pharmaceuticals', '0333-3456789', 'supply@medilife.com', '45-A, Gulberg III, Lahore, Pakistan', '2026-01-06 06:41:06'),
(5, 'Prime Health Suppliers', '0345-4567890', 'contact@primehealth.pk', 'Plot 22, Industrial Area, Karachi, Pakistan', '2026-01-06 06:41:06'),
(6, 'BioGen Solutions', '0300-1122334', 'info@biogensolutions.com', 'Science Park, Faisalabad, Pakistan', '2026-01-06 06:41:06'),
(7, 'Global Pharma Traders', '0312-2233445', 'sales@globalpharmatraders.com', 'Business Center, Multan, Pakistan', '2026-01-06 06:41:06'),
(8, 'CarePlus Medical Supplies', '0331-3344556', 'support@careplusmedical.pk', 'Healthcare Street, Rawalpindi, Pakistan', '2026-01-06 06:41:06'),
(9, 'MediCorp Distributors', '0344-4455667', 'orders@medicorp.com.pk', 'Corporate Tower, Karachi, Pakistan', '2026-01-06 06:41:06'),
(10, 'LifeLine Pharma', '0301-5566778', 'contact@lifelinepharma.pk', 'Pharma City, Lahore, Pakistan', '2026-01-06 06:41:06'),
(11, 'TrustMed Suppliers', '0315-6677889', 'supplies@trustmed.pk', 'Trust Plaza, Islamabad, Pakistan', '2026-01-06 06:41:06'),
(12, 'HealthFirst Distributors', '0336-7788990', 'info@healthfirstdistributors.com', 'Medical Zone, Peshawar, Pakistan', '2026-01-06 06:41:06');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','pharmacist') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `username`, `password`, `role`, `created_at`) VALUES
(5, 'Admin', 'admin', 'admin123', 'admin', '2025-12-31 11:09:11'),
(6, 'Pharmacist', 'pharmacist', 'pharma123', 'pharmacist', '2025-12-31 11:09:11');

-- --------------------------------------------------------

--
-- Table structure for table `user_sessions`
--

CREATE TABLE `user_sessions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `last_activity` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `alerts`
--
ALTER TABLE `alerts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `medicine_id` (`medicine_id`);

--
-- Indexes for table `disposal_history`
--
ALTER TABLE `disposal_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `batch_id` (`batch_id`),
  ADD KEY `medicine_id` (`medicine_id`),
  ADD KEY `disposed_by` (`disposed_by`);

--
-- Indexes for table `expenses`
--
ALTER TABLE `expenses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_expense_date` (`expense_date`),
  ADD KEY `idx_expense_type` (`expense_type`),
  ADD KEY `idx_category` (`category`);

--
-- Indexes for table `invoices`
--
ALTER TABLE `invoices`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `invoice_no` (`invoice_no`),
  ADD KEY `sale_id` (`sale_id`),
  ADD KEY `pharmacist_id` (`pharmacist_id`);

--
-- Indexes for table `invoice_items`
--
ALTER TABLE `invoice_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `invoice_id` (`invoice_id`),
  ADD KEY `medicine_id` (`medicine_id`),
  ADD KEY `batch_id` (`batch_id`);

--
-- Indexes for table `invoice_sequences`
--
ALTER TABLE `invoice_sequences`
  ADD PRIMARY KEY (`seq_key`);

--
-- Indexes for table `medicines`
--
ALTER TABLE `medicines`
  ADD PRIMARY KEY (`id`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `type_id` (`type_id`);

--
-- Indexes for table `medicine_categories`
--
ALTER TABLE `medicine_categories`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `medicine_types`
--
ALTER TABLE `medicine_types`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_payments_type_reference` (`payment_type`,`reference_id`),
  ADD KEY `idx_payments_invoice` (`invoice_no`),
  ADD KEY `idx_payments_date` (`payment_date`),
  ADD KEY `idx_payments_created_by` (`created_by`),
  ADD KEY `idx_payments_auto` (`is_auto_generated`,`auto_generated_from`);

--
-- Indexes for table `returns_to_company`
--
ALTER TABLE `returns_to_company`
  ADD PRIMARY KEY (`id`),
  ADD KEY `batch_id` (`batch_id`),
  ADD KEY `medicine_id` (`medicine_id`),
  ADD KEY `returned_by` (`returned_by`);

--
-- Indexes for table `sales`
--
ALTER TABLE `sales`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `invoice_no` (`invoice_no`),
  ADD KEY `pharmacist_id` (`pharmacist_id`);

--
-- Indexes for table `sale_items`
--
ALTER TABLE `sale_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sale_id` (`sale_id`),
  ADD KEY `medicine_id` (`medicine_id`),
  ADD KEY `batch_id` (`batch_id`);

--
-- Indexes for table `stock_batches`
--
ALTER TABLE `stock_batches`
  ADD PRIMARY KEY (`id`),
  ADD KEY `medicine_id` (`medicine_id`),
  ADD KEY `supplier_id` (`supplier_id`);

--
-- Indexes for table `suppliers`
--
ALTER TABLE `suppliers`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `user_sessions`
--
ALTER TABLE `user_sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `alerts`
--
ALTER TABLE `alerts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `disposal_history`
--
ALTER TABLE `disposal_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `expenses`
--
ALTER TABLE `expenses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `invoices`
--
ALTER TABLE `invoices`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `invoice_items`
--
ALTER TABLE `invoice_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `medicines`
--
ALTER TABLE `medicines`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=207;

--
-- AUTO_INCREMENT for table `medicine_categories`
--
ALTER TABLE `medicine_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT for table `medicine_types`
--
ALTER TABLE `medicine_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `returns_to_company`
--
ALTER TABLE `returns_to_company`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `sales`
--
ALTER TABLE `sales`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `sale_items`
--
ALTER TABLE `sale_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT for table `stock_batches`
--
ALTER TABLE `stock_batches`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=276;

--
-- AUTO_INCREMENT for table `suppliers`
--
ALTER TABLE `suppliers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `user_sessions`
--
ALTER TABLE `user_sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `alerts`
--
ALTER TABLE `alerts`
  ADD CONSTRAINT `alerts_ibfk_1` FOREIGN KEY (`medicine_id`) REFERENCES `medicines` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `disposal_history`
--
ALTER TABLE `disposal_history`
  ADD CONSTRAINT `disposal_history_ibfk_1` FOREIGN KEY (`batch_id`) REFERENCES `stock_batches` (`id`),
  ADD CONSTRAINT `disposal_history_ibfk_2` FOREIGN KEY (`medicine_id`) REFERENCES `medicines` (`id`),
  ADD CONSTRAINT `disposal_history_ibfk_3` FOREIGN KEY (`disposed_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `expenses`
--
ALTER TABLE `expenses`
  ADD CONSTRAINT `expenses_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `invoices`
--
ALTER TABLE `invoices`
  ADD CONSTRAINT `invoices_ibfk_1` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `invoices_ibfk_2` FOREIGN KEY (`pharmacist_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `invoice_items`
--
ALTER TABLE `invoice_items`
  ADD CONSTRAINT `invoice_items_ibfk_1` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `invoice_items_ibfk_2` FOREIGN KEY (`medicine_id`) REFERENCES `medicines` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `invoice_items_ibfk_3` FOREIGN KEY (`batch_id`) REFERENCES `stock_batches` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `medicines`
--
ALTER TABLE `medicines`
  ADD CONSTRAINT `medicines_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `medicine_categories` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `medicines_ibfk_2` FOREIGN KEY (`type_id`) REFERENCES `medicine_types` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `returns_to_company`
--
ALTER TABLE `returns_to_company`
  ADD CONSTRAINT `returns_to_company_ibfk_1` FOREIGN KEY (`batch_id`) REFERENCES `stock_batches` (`id`),
  ADD CONSTRAINT `returns_to_company_ibfk_2` FOREIGN KEY (`medicine_id`) REFERENCES `medicines` (`id`),
  ADD CONSTRAINT `returns_to_company_ibfk_3` FOREIGN KEY (`returned_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `sales`
--
ALTER TABLE `sales`
  ADD CONSTRAINT `sales_ibfk_1` FOREIGN KEY (`pharmacist_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `sale_items`
--
ALTER TABLE `sale_items`
  ADD CONSTRAINT `sale_items_ibfk_1` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `sale_items_ibfk_2` FOREIGN KEY (`medicine_id`) REFERENCES `medicines` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `sale_items_ibfk_3` FOREIGN KEY (`batch_id`) REFERENCES `stock_batches` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `stock_batches`
--
ALTER TABLE `stock_batches`
  ADD CONSTRAINT `stock_batches_ibfk_1` FOREIGN KEY (`medicine_id`) REFERENCES `medicines` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `stock_batches_ibfk_2` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `user_sessions`
--
ALTER TABLE `user_sessions`
  ADD CONSTRAINT `user_sessions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
