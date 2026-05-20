<?php
/**
 * Consumables History Logger
 * Helper class to log all consumable quantity changes
 */

class ConsumablesHistoryLogger {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    /**
     * Log a consumable history entry
     * 
     * @param int $consumable_id - ID of the consumable
     * @param string $action_type - Type of action: 'refill', 'deduction', 'adjustment', 'initial', 'edit'
     * @param int $previous_quantity - Quantity before the change
     * @param int $quantity_change - Amount changed (positive for add, negative for deduct)
     * @param int $new_quantity - Quantity after the change
     * @param int|null $performed_by - User ID who performed the action
     * @param string|null $reference_type - Type of reference (e.g., 'request', 'manual_refill', 'stock_adjustment')
     * @param int|null $reference_id - ID of the related record
     * @param string|null $remarks - Additional notes
     * @return bool - Success status
     */
    public function log($consumable_id, $action_type, $previous_quantity, $quantity_change, $new_quantity, 
                       $performed_by = null, $reference_type = null, $reference_id = null, $remarks = null) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO consumables_history 
                (consumable_id, action_type, previous_quantity, quantity_change, new_quantity, 
                 performed_by, reference_type, reference_id, remarks) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            return $stmt->execute([
                $consumable_id,
                $action_type,
                $previous_quantity,
                $quantity_change,
                $new_quantity,
                $performed_by,
                $reference_type,
                $reference_id,
                $remarks
            ]);
        } catch (Exception $e) {
            error_log("Consumables History Logger Error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Log a refill action
     */
    public function logRefill($consumable_id, $previous_quantity, $refill_amount, $new_quantity, 
                             $performed_by, $remarks = null) {
        return $this->log(
            $consumable_id, 
            'refill', 
            $previous_quantity, 
            $refill_amount, 
            $new_quantity, 
            $performed_by, 
            'manual_refill', 
            null, 
            $remarks
        );
    }
    
    /**
     * Log a deduction action (when items are released/approved)
     */
    public function logDeduction($consumable_id, $previous_quantity, $deduction_amount, $new_quantity, 
                                $performed_by, $request_id = null, $remarks = null) {
        return $this->log(
            $consumable_id, 
            'deduction', 
            $previous_quantity, 
            -$deduction_amount, // Negative for deduction
            $new_quantity, 
            $performed_by, 
            'request', 
            $request_id, 
            $remarks
        );
    }
    
    /**
     * Log an adjustment action (manual stock correction)
     */
    public function logAdjustment($consumable_id, $previous_quantity, $adjustment_amount, $new_quantity, 
                                 $performed_by, $remarks = null) {
        return $this->log(
            $consumable_id, 
            'adjustment', 
            $previous_quantity, 
            $adjustment_amount, 
            $new_quantity, 
            $performed_by, 
            'stock_adjustment', 
            null, 
            $remarks
        );
    }
    
    /**
     * Log an edit action (when consumable details are updated)
     */
    public function logEdit($consumable_id, $previous_quantity, $quantity_change, $new_quantity, 
                           $performed_by, $remarks = null) {
        return $this->log(
            $consumable_id, 
            'edit', 
            $previous_quantity, 
            $quantity_change, 
            $new_quantity, 
            $performed_by, 
            'manual_edit', 
            null, 
            $remarks
        );
    }
    
    /**
     * Get history for a specific consumable
     */
    public function getHistory($consumable_id, $limit = 50) {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    ch.*,
                    u.full_name as performed_by_name,
                    u.email as performed_by_email,
                    c.item_name as consumable_name
                FROM consumables_history ch
                LEFT JOIN users u ON ch.performed_by = u.id
                LEFT JOIN consumables c ON ch.consumable_id = c.id
                WHERE ch.consumable_id = ?
                ORDER BY ch.action_date DESC
                LIMIT ?
            ");
            
            $stmt->execute([$consumable_id, $limit]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Get History Error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get all history with filters
     */
    public function getAllHistory($filters = [], $limit = 100) {
        try {
            $where = ["1=1"];
            $params = [];
            
            if (!empty($filters['consumable_id'])) {
                $where[] = "ch.consumable_id = ?";
                $params[] = $filters['consumable_id'];
            }
            
            if (!empty($filters['action_type'])) {
                $where[] = "ch.action_type = ?";
                $params[] = $filters['action_type'];
            }
            
            if (!empty($filters['date_from'])) {
                $where[] = "DATE(ch.action_date) >= ?";
                $params[] = $filters['date_from'];
            }
            
            if (!empty($filters['date_to'])) {
                $where[] = "DATE(ch.action_date) <= ?";
                $params[] = $filters['date_to'];
            }
            
            if (!empty($filters['performed_by'])) {
                $where[] = "ch.performed_by = ?";
                $params[] = $filters['performed_by'];
            }
            
            $where_clause = implode(" AND ", $where);
            
            $stmt = $this->db->prepare("
                SELECT 
                    ch.*,
                    u.full_name as performed_by_name,
                    u.email as performed_by_email,
                    c.item_name as consumable_name,
                    c.category as consumable_category
                FROM consumables_history ch
                LEFT JOIN users u ON ch.performed_by = u.id
                LEFT JOIN consumables c ON ch.consumable_id = c.id
                WHERE $where_clause
                ORDER BY ch.action_date DESC
                LIMIT ?
            ");
            
            $params[] = $limit;
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Get All History Error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get statistics for a consumable
     */
    public function getStatistics($consumable_id) {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    COUNT(*) as total_actions,
                    SUM(CASE WHEN action_type = 'refill' THEN 1 ELSE 0 END) as total_refills,
                    SUM(CASE WHEN action_type = 'deduction' THEN 1 ELSE 0 END) as total_deductions,
                    SUM(CASE WHEN action_type = 'refill' THEN quantity_change ELSE 0 END) as total_refilled,
                    SUM(CASE WHEN action_type = 'deduction' THEN ABS(quantity_change) ELSE 0 END) as total_consumed,
                    MIN(action_date) as first_action,
                    MAX(action_date) as last_action
                FROM consumables_history
                WHERE consumable_id = ?
            ");
            
            $stmt->execute([$consumable_id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Get Statistics Error: " . $e->getMessage());
            return null;
        }
    }
}
?>
