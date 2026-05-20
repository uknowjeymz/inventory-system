-- ============================================
-- CONSUMABLES HISTORY TABLE
-- Tracks all changes to consumable quantities
-- ============================================

CREATE TABLE IF NOT EXISTS `consumables_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `consumable_id` int(11) NOT NULL,
  `action_type` enum('refill','deduction','adjustment','initial','edit') NOT NULL,
  `previous_quantity` int(11) NOT NULL,
  `quantity_change` int(11) NOT NULL COMMENT 'Positive for additions, negative for deductions',
  `new_quantity` int(11) NOT NULL,
  `action_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `performed_by` int(11) DEFAULT NULL COMMENT 'User ID who performed the action',
  `reference_type` varchar(50) DEFAULT NULL COMMENT 'e.g., request, manual_refill, stock_adjustment',
  `reference_id` int(11) DEFAULT NULL COMMENT 'ID of related record (request_id, etc.)',
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_consumable_id` (`consumable_id`),
  KEY `idx_action_date` (`action_date`),
  KEY `idx_action_type` (`action_type`),
  KEY `idx_performed_by` (`performed_by`),
  CONSTRAINT `fk_consumables_history_consumable` FOREIGN KEY (`consumable_id`) REFERENCES `consumables` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_consumables_history_user` FOREIGN KEY (`performed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================
-- INDEXES FOR BETTER PERFORMANCE
-- ============================================

CREATE INDEX `idx_consumable_action` ON `consumables_history` (`consumable_id`, `action_date` DESC);
CREATE INDEX `idx_reference` ON `consumables_history` (`reference_type`, `reference_id`);

-- ============================================
-- SAMPLE DATA (Optional - for testing)
-- ============================================

-- You can insert sample data here if needed
-- INSERT INTO `consumables_history` (`consumable_id`, `action_type`, `previous_quantity`, `quantity_change`, `new_quantity`, `performed_by`, `reference_type`, `remarks`) 
-- VALUES (1, 'initial', 0, 100, 100, 1, 'manual_refill', 'Initial stock');
