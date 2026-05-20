<?php
/**
 * get_equipment_ajax.php
 * Paginated AJAX endpoint for All Equipment lazy-load.
 * Returns JSON: { html, total, loaded, has_more }
 */

session_start();
date_default_timezone_set('Asia/Manila');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

require_once '../config/database.php';
$database = new Database();
$db = $database->getConnection();

// ─── Parameters ───────────────────────────────────────────────────────────────
$page           = max(1, (int)($_GET['page']   ?? 1));
$per_page       = max(1, min(100, (int)($_GET['per_page'] ?? 30)));
$offset         = ($page - 1) * $per_page;

$search         = trim($_GET['search']   ?? '');
$type_filter    = trim($_GET['type']     ?? '');
$status_filter  = trim($_GET['status']   ?? '');
$location_filter= trim($_GET['location'] ?? '');
$campus_filter  = trim($_GET['campus']   ?? '');

// ─── Locations for dropdowns (needed inside row HTML) ─────────────────────────
$locations = $db->query("SELECT id, location_name FROM locations ORDER BY location_name")->fetchAll(PDO::FETCH_ASSOC);

// ─── Helper: build WHERE conditions for one table ─────────────────────────────
function buildConditions(string $alias, string $table, string $name_col, string $eq_type,
                          string $search, string $status_filter, string $location_filter,
                          string $campus_filter, PDO $db): array
{
    $conditions = [];
    $params     = [];

    // Condemned filter (skip condemned rows if column exists)
    try {
        $chk = $db->prepare("SHOW COLUMNS FROM {$table} LIKE 'is_condemned'");
        $chk->execute();
        if ($chk->rowCount() > 0) {
            $conditions[] = "({$alias}.is_condemned IS NULL OR {$alias}.is_condemned = FALSE)";
        }
    } catch (Exception $e) {}

    if ($campus_filter) {
        $conditions[] = "{$alias}.campus = :campus";
        $params[':campus'] = $campus_filter;
    }
    if ($location_filter) {
        $conditions[] = "{$alias}.location_id = :location";
        $params[':location'] = $location_filter;
    }
    if ($status_filter) {
        $conditions[] = "{$alias}.status = :status";
        $params[':status'] = $status_filter;
    }
    if ($search) {
        $s = "%{$search}%";
        // build search clause per table type
        $search_clause = match($eq_type) {
            'computer_lab' => "({$alias}.item_number LIKE :s OR {$alias}.computer_set_description LIKE :s
                               OR {$alias}.serial_number LIKE :s OR {$alias}.property_no LIKE :s
                               OR {$alias}.serial_number_monitor LIKE :s OR {$alias}.serial_number_system LIKE :s
                               OR {$alias}.remarks LIKE :s)",
            'office', 'general' => "({$alias}.item_number LIKE :s OR {$name_col} LIKE :s
                               OR {$alias}.serial_number LIKE :s OR {$alias}.property_no LIKE :s
                               OR {$alias}.remarks LIKE :s)",
            default => "({$alias}.item_number LIKE :s OR {$name_col} LIKE :s
                        OR {$alias}.serial_number LIKE :s OR {$alias}.property_no LIKE :s
                        OR {$alias}.remarks LIKE :s)",
        };
        $conditions[] = $search_clause;
        $params[':s']  = $s;
    }

    return [$conditions, $params];
}

// ─── Fetch each table ─────────────────────────────────────────────────────────
$all_equipment = [];

$table_configs = [
    'computer_lab'  => ['table' => 'computer_inventory',  'alias' => 'ci', 'name_col' => 'ci.computer_set_description',
                        'select' => "ci.*, 'computer_lab' AS equipment_type, 'Computer' AS type_label,
                                     l.location_name, l.id AS location_id,
                                     CONCAT(ci.item_number, ' - ', ci.computer_set_description) AS equipment_name,
                                     ci.serial_number, ci.status, ci.condition_status",
                        'join'   => "LEFT JOIN locations l ON ci.location_id = l.id"],

    'kitchen'       => ['table' => 'kitchen_equipment',   'alias' => 'ke', 'name_col' => 'ke.equipment_name',
                        'select' => "ke.*, 'kitchen' AS equipment_type, 'Kitchen Equipment' AS type_label,
                                     l.location_name, l.id AS location_id,
                                     CONCAT(ke.item_number, ' - ', ke.equipment_name) AS equipment_name,
                                     ke.serial_number, ke.status, ke.condition_status",
                        'join'   => "LEFT JOIN locations l ON ke.location_id = l.id"],

    'office'        => ['table' => 'office_equipment',    'alias' => 'oe', 'name_col' => 'oe.equipment_name',
                        'select' => "oe.*, 'office' AS equipment_type, 'Office Equipment' AS type_label,
                                     l.location_name, l.id AS location_id,
                                     CONCAT(oe.item_number, ' - ', oe.equipment_name) AS equipment_name,
                                     oe.serial_number, oe.property_no, oe.status, oe.condition_status",
                        'join'   => "LEFT JOIN locations l ON oe.location_id = l.id"],

    'regular_lab'   => ['table' => 'lab_equipment',       'alias' => 'le', 'name_col' => 'le.equipment_name',
                        'select' => "le.*, 'regular_lab' AS equipment_type, 'Lab Equipment' AS type_label,
                                     l.location_name, l.id AS location_id,
                                     CONCAT(le.item_number, ' - ', le.equipment_name) AS equipment_name,
                                     le.serial_number, le.status, le.condition_status",
                        'join'   => "LEFT JOIN locations l ON le.location_id = l.id"],

    'general'       => ['table' => 'general_equipment',   'alias' => 'ge', 'name_col' => 'ge.article',
                        'select' => "ge.*, 'general' AS equipment_type, 'General Equipment' AS type_label,
                                     l.location_name, l.id AS location_id,
                                     ge.article AS equipment_name,
                                     ge.serial_number, ge.property_no, ge.status, ge.condition_status,
                                     ge.projector_brand, ge.projector_model, ge.projector_serial_number",
                        'join'   => "LEFT JOIN locations l ON ge.location_id = l.id"],
];

// If a type filter is set, only query that table
$tables_to_query = $type_filter && isset($table_configs[$type_filter])
    ? [$type_filter => $table_configs[$type_filter]]
    : $table_configs;

foreach ($tables_to_query as $eq_type => $cfg) {
    try {
        [$conditions, $params] = buildConditions(
            $cfg['alias'], $cfg['table'], $cfg['name_col'], $eq_type,
            $search, $status_filter, $location_filter, $campus_filter, $db
        );

        $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
        $sql   = "SELECT {$cfg['select']} FROM {$cfg['table']} {$cfg['alias']}
                  {$cfg['join']}
                  {$where}
                  ORDER BY {$cfg['alias']}.created_at DESC";

        $stmt = $db->prepare($sql);
        foreach ($params as $k => $v) $stmt->bindValue($k, $v);
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $all_equipment = array_merge($all_equipment, $rows);
    } catch (Exception $e) {
        error_log("get_equipment_ajax: {$eq_type} error: " . $e->getMessage());
    }
}

// ─── Sort merged results newest first ─────────────────────────────────────────
usort($all_equipment, function($a, $b) {
    return strtotime($b['created_at'] ?? '0') - strtotime($a['created_at'] ?? '0');
});

$total  = count($all_equipment);
$page_items = array_slice($all_equipment, $offset, $per_page);
$has_more   = ($offset + $per_page) < $total;

// ─── Render rows as HTML ───────────────────────────────────────────────────────
ob_start();

foreach ($page_items as $equipment):
    $eq_type = $equipment['equipment_type'];
    $icon = match($eq_type) {
        'computer_lab' => 'fa-desktop',
        'kitchen'      => 'fa-utensils',
        'office'       => 'fa-briefcase',
        'regular_lab'  => 'fa-flask',
        default        => 'fa-tools',
    };
    $type_icon_tag = match($eq_type) {
        'computer_lab' => '<i class="fas fa-desktop me-1"></i>',
        'kitchen'      => '<i class="fas fa-utensils me-1"></i>',
        'office'       => '<i class="fas fa-briefcase me-1"></i>',
        'regular_lab'  => '<i class="fas fa-flask me-1"></i>',
        default        => '<i class="fas fa-tools me-1"></i>',
    };
    $type_badge_class = match($eq_type) {
        'computer_lab' => 'computer',
        'kitchen'      => 'kitchen',
        'office'       => 'office',
        'regular_lab'  => 'lab',
        default        => 'general',
    };

    // Unit badge
    $unit       = $equipment['unit'] ?? 'unit';
    $unit_icon  = match($unit) { 'box' => 'fa-box', 'pcs' => 'fa-puzzle-piece', 'lot' => 'fa-layer-group', default => 'fa-cube' };
    $unit_color = match($unit) { 'box' => 'success', 'pcs' => 'info', 'lot' => 'warning', default => 'primary' };

    // Age badge
    $age_html = '';
    if (!empty($equipment['purchase_date']) && $equipment['purchase_date'] !== '0000-00-00') {
        $age = (int)date('Y') - (int)date('Y', strtotime($equipment['purchase_date']));
        $age_class = $age >= 5 ? 'eligible' : ($age >= 3 ? 'warning' : 'new');
        $age_html = "<span class=\"age-badge {$age_class}\" title=\"{$age} years old\">{$age} yrs</span>";
    }

    // Serial / property display
    $serial_html = '';
    if ($eq_type === 'computer_lab') {
        $pn = htmlspecialchars($equipment['property_no'] ?? 'N/A');
        $sn = htmlspecialchars($equipment['serial_number'] ?? '');
        $sm = htmlspecialchars($equipment['serial_number_monitor'] ?? '');
        $ss = htmlspecialchars($equipment['serial_number_system'] ?? '');
        $serial_html = "<div><span class=\"fw-bold text-primary\">PN: {$pn}</span><div class=\"small text-muted\">";
        if ($sn && $sn !== $pn) $serial_html .= "SN: {$sn}<br>";
        if ($sm) $serial_html .= "M: {$sm}<br>";
        if ($ss) $serial_html .= "S: {$ss}";
        $serial_html .= "</div></div>";
    } elseif (in_array($eq_type, ['office', 'general'])) {
        $pn = htmlspecialchars($equipment['property_no'] ?? '');
        $sn = htmlspecialchars($equipment['serial_number'] ?? '');
        $serial_html = "<div>";
        if ($pn) $serial_html .= "<span class=\"fw-bold text-primary\">PN: {$pn}</span>";
        if ($sn && $sn !== 'N/A') $serial_html .= "<div class=\"small text-muted\">SN: {$sn}</div>";
        else $serial_html .= "<div class=\"small text-muted\"><em>No Serial Number</em></div>";
        $serial_html .= "</div>";
    } else {
        $serial_html = "<code>" . htmlspecialchars($equipment['serial_number'] ?? 'N/A') . "</code>";
    }

    // Location dropdown options
    $location_options = '<option value="">-- Unassigned --</option>';
    foreach ($locations as $loc) {
        $sel = ($equipment['location_id'] == $loc['id']) ? 'selected' : '';
        $location_options .= "<option value=\"{$loc['id']}\" {$sel}>" . htmlspecialchars($loc['location_name']) . "</option>";
    }

    $eq_name   = htmlspecialchars($equipment['equipment_name']);
    $item_no   = htmlspecialchars($equipment['item_number']);
    $remarks   = htmlspecialchars($equipment['remarks'] ?: 'Unassigned');
    $status    = $equipment['status'];
    $assigned  = $equipment['location_id'] ? 'assigned' : 'unassigned';
    $assigned_label = $equipment['location_id'] ? 'Assigned' : 'Unassigned';
    $updated   = !empty($equipment['updated_at']) ? date('M j, Y', strtotime($equipment['updated_at'])) : 'N/A';
    $eq_id     = (int)$equipment['id'];
    $eq_name_js = addslashes($equipment['equipment_name']);
    $campus    = htmlspecialchars($equipment['campus'] ?? 'N/A');
    $data_remarks = htmlspecialchars($equipment['remarks'] ?? 'None');
    $serial_search = htmlspecialchars(implode(' ', array_filter([
        $equipment['serial_number'] ?? '',
        $equipment['property_no'] ?? '',
        $equipment['serial_number_monitor'] ?? '',
        $equipment['serial_number_system'] ?? '',
    ])));
?>
<tr class="equipment-row"
    data-equipment-type="<?= $eq_type ?>"
    data-equipment-id="<?= $eq_id ?>"
    data-current-campus="<?= $campus ?>"
    data-current-remarks="<?= $data_remarks ?>"
    data-serial-search="<?= $serial_search ?>">

    <td data-label="Select">
        <input type="checkbox" class="form-check-input equipment-checkbox"
               value="<?= $eq_type ?>:<?= $eq_id ?>"
               onchange="updateSelection()">
    </td>

    <td data-label="Equipment">
        <div class="equipment-info">
            <div class="equipment-icon"><i class="fas <?= $icon ?>"></i></div>
            <div class="equipment-details">
                <h6><?= $eq_name ?></h6>
                <small><i class="fas fa-hashtag"></i> <?= $item_no ?></small>
            </div>
        </div>
    </td>

    <td data-label="Type">
        <span class="type-badge <?= $type_badge_class ?>">
            <?= $type_icon_tag . $equipment['type_label'] ?>
        </span>
    </td>

    <td data-label="Unit">
        <span class="badge bg-<?= $unit_color ?> bg-opacity-10 text-<?= $unit_color ?> p-2">
            <i class="fas <?= $unit_icon ?> me-1"></i><?= strtoupper($unit) ?>
        </span>
    </td>

    <td data-label="Serial/Property No."><?= $serial_html ?></td>

    <td data-label="Accountable Person">
        <span class="fw-bold"><?= $remarks ?></span>
    </td>

    <td data-label="Status">
        <span class="status-badge <?= $status ?>">
            <i class="fas fa-circle"></i> <?= ucfirst($status) ?>
        </span>
        <?= $age_html ?>
    </td>

    <td data-label="Assignment">
        <span class="assignment-badge <?= $assigned ?>"><?= $assigned_label ?></span>
    </td>

    <td data-label="Location">
        <form method="POST" class="d-inline" onsubmit="return confirm('Update location?');">
            <input type="hidden" name="action" value="update_assignment">
            <input type="hidden" name="equipment_id" value="<?= $eq_id ?>">
            <input type="hidden" name="equipment_type" value="<?= $eq_type ?>">
            <select class="location-select" name="location_id" onchange="this.form.submit()">
                <?= $location_options ?>
            </select>
        </form>
    </td>

    <td data-label="Last Updated">
        <span class="small text-muted"><?= $updated ?></span>
    </td>

    <td data-label="Actions" class="text-end">
        <div class="action-buttons">
            <a href="generate_item_details_pdf.php?id=<?= $eq_id ?>&type=<?= $eq_type ?>"
               target="_blank" class="action-btn pdf" title="Generate PDF">
                <i class="fas fa-file-pdf"></i>
            </a>
            <button type="button" class="action-btn edit" title="Edit Equipment"
                    onclick="openEditModal(<?= $eq_id ?>, '<?= $eq_type ?>')">
                <i class="fas fa-edit"></i>
            </button>
            <button type="button" class="action-btn view" title="View Details"
                    onclick="viewEquipmentDetails(<?= $eq_id ?>, '<?= $eq_type ?>')">
                <i class="fas fa-eye"></i>
            </button>
            <button type="button" class="action-btn condemn" title="Manual Condemn (Broken/Unusable)"
                    onclick="manualCondemnEquipment(<?= $eq_id ?>, '<?= $eq_type ?>', '<?= $eq_name_js ?>')">
                <i class="fas fa-times-circle"></i>
            </button>
            <button type="button" class="action-btn delete" title="Delete Equipment"
                    onclick="confirmDeleteEquipment(<?= $eq_id ?>, '<?= $eq_type ?>', '<?= $eq_name_js ?>')">
                <i class="fas fa-trash-alt"></i>
            </button>
        </div>
    </td>
</tr>
<?php endforeach; ?>
<?php
$html = ob_get_clean();

// ─── Also fetch stats (only on page 1 so we can update counters) ──────────────
$stats = null;
if ($page === 1) {
    // Quick count queries per table for stats
    $stat_total = $total; // already computed from our merged set
    $assigned_count = count(array_filter($all_equipment, fn($i) => !empty($i['location_id'])));
    $maintenance_count = count(array_filter($all_equipment, fn($i) => $i['status'] === 'maintenance'));
    $stats = [
        'total'       => $stat_total,
        'assigned'    => $assigned_count,
        'unassigned'  => $stat_total - $assigned_count,
        'maintenance' => $maintenance_count,
    ];
}

header('Content-Type: application/json');
echo json_encode([
    'html'     => $html,
    'total'    => $total,
    'loaded'   => $offset + count($page_items),
    'has_more' => $has_more,
    'page'     => $page,
    'stats'    => $stats,
]);