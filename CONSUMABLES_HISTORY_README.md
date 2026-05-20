# Consumables History Feature

## Overview
This feature tracks all changes to consumable quantities, including refills, deductions, adjustments, and edits. Every time a consumable's stock is modified, a record is created in the `consumables_history` table.

## Database Setup

### 1. Run the Migration
Execute the SQL file to create the `consumables_history` table:

```bash
# Using MySQL command line
mysql -u your_username -p your_database < database/consumables_history_migration.sql

# Or import via phpMyAdmin
# Navigate to phpMyAdmin > Import > Choose file: database/consumables_history_migration.sql
```

### 2. Table Structure
The `consumables_history` table includes:
- `id` - Primary key
- `consumable_id` - Reference to consumables table
- `action_type` - Type of action (refill, deduction, adjustment, initial, edit)
- `previous_quantity` - Stock before the change
- `quantity_change` - Amount changed (positive for additions, negative for deductions)
- `new_quantity` - Stock after the change
- `action_date` - When the action occurred
- `performed_by` - User who performed the action
- `reference_type` - Type of reference (request, manual_refill, stock_adjustment)
- `reference_id` - ID of related record (e.g., request_id)
- `remarks` - Additional notes

## Features

### 1. Automatic History Logging
The system automatically logs history when:
- **Refilling stock** - When admin adds more stock to a consumable
- **Releasing items** - When items are approved and released to users
- **Adjusting stock** - When admin manually adjusts quantities
- **Editing items** - When consumable details are updated

### 2. History Logger Class
Location: `admin/consumables_history_logger.php`

#### Methods:
```php
// Log a refill
$historyLogger->logRefill($consumable_id, $previous_qty, $refill_amount, $new_qty, $user_id, $remarks);

// Log a deduction (when items are released)
$historyLogger->logDeduction($consumable_id, $previous_qty, $deduction_amount, $new_qty, $user_id, $request_id, $remarks);

// Log an adjustment
$historyLogger->logAdjustment($consumable_id, $previous_qty, $adjustment_amount, $new_qty, $user_id, $remarks);

// Log an edit
$historyLogger->logEdit($consumable_id, $previous_qty, $quantity_change, $new_qty, $user_id, $remarks);

// Get history for a specific consumable
$history = $historyLogger->getHistory($consumable_id, $limit);

// Get all history with filters
$history = $historyLogger->getAllHistory($filters, $limit);

// Get statistics
$stats = $historyLogger->getStatistics($consumable_id);
```

### 3. View History Page
Location: `admin/consumable_history.php`

Features:
- View all consumable history records
- Filter by:
  - Consumable item
  - Action type (refill, deduction, adjustment, etc.)
  - Date range
  - User who performed the action
- Export/Print functionality
- Color-coded action badges
- Detailed information for each change

### 4. API Endpoint
Location: `admin/get_consumable_history.php`

Get history for a specific consumable via AJAX:
```javascript
fetch('get_consumable_history.php?id=123')
  .then(response => response.json())
  .then(data => {
    console.log(data.consumable); // Consumable details
    console.log(data.history);    // History records
    console.log(data.statistics); // Statistics
  });
```

## Usage Examples

### Example 1: Refilling Stock
When you refill a ballpen from 100 to 200 units:
```
Action Type: refill
Previous Quantity: 100
Quantity Change: +100
New Quantity: 200
Performed By: Admin Name
Reference: manual_refill
Remarks: "Restocked from supplier"
```

### Example 2: Releasing Items
When a user requests 50 ballpens and it's approved:
```
Action Type: deduction
Previous Quantity: 200
Quantity Change: -50
New Quantity: 150
Performed By: Admin Name
Reference: request #45
Remarks: "Released to request #45"
```

### Example 3: Stock Adjustment
When correcting inventory count:
```
Action Type: adjustment
Previous Quantity: 150
Quantity Change: -10
New Quantity: 140
Performed By: Admin Name
Reference: stock_adjustment
Remarks: "Physical count correction"
```

## Integration Points

### Files Modified:
1. `admin/consumables.php` - Added history logger integration
   - Logs refills when stock is added
   - Logs deductions when items are released
   
2. `admin/consumables_edit_request_action.php` - Should be updated to log adjustments
3. `admin/process_check_items.php` - Should be updated to log deductions

### Files Created:
1. `database/consumables_history_migration.sql` - Database migration
2. `admin/consumables_history_logger.php` - Logger class
3. `admin/consumable_history.php` - History viewing page
4. `admin/get_consumable_history.php` - API endpoint
5. `CONSUMABLES_HISTORY_README.md` - This documentation

## Navigation

Add a link to the history page in your admin navigation:
```html
<a href="consumable_history.php" class="nav-link">
    <i class="fas fa-history"></i> Consumables History
</a>
```

Or add a button in the consumables page:
```html
<a href="consumable_history.php" class="btn btn-info">
    <i class="fas fa-history"></i> View History
</a>
```

## Statistics Available

For each consumable, you can get:
- Total number of actions
- Total refills count
- Total deductions count
- Total amount refilled
- Total amount consumed
- First action date
- Last action date

## Benefits

1. **Audit Trail** - Complete record of all stock changes
2. **Accountability** - Know who made each change and when
3. **Trend Analysis** - Identify consumption patterns
4. **Inventory Management** - Better stock planning based on history
5. **Troubleshooting** - Quickly identify when and why stock levels changed
6. **Compliance** - Meet audit requirements with detailed logs

## Future Enhancements

Potential additions:
- Export history to Excel/PDF
- Dashboard charts showing consumption trends
- Alerts for unusual stock changes
- Batch import/export of history data
- Integration with reporting system
- Mobile-friendly history view

## Support

For issues or questions:
1. Check the database migration ran successfully
2. Verify the `consumables_history` table exists
3. Check PHP error logs for any issues
4. Ensure proper permissions for admin users

## Version History

- v1.0 (2026-03-03) - Initial implementation
  - Created consumables_history table
  - Added history logger class
  - Integrated with refill and release actions
  - Created history viewing page
