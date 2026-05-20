<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    die("Unauthorized access");
}

require_once '../config/database.php';
$database = new Database();
$db = $database->getConnection();

$year = $_POST['year'] ?? date('Y');
$category = $_POST['category'] ?? 'all';
$department = $_POST['department'] ?? 'all';

// Main query for annual consumption - includes item_name for drill-down
$query = "SELECT 
            c.category,
            rg.office as department,
            c.item_name,
            c.unit,
            SUM(ri.quantity) as total_quantity,
            COUNT(DISTINCT rg.id) as request_count,
            COUNT(ri.id) as item_count,
            AVG(ri.quantity) as avg_per_request
          FROM request_items ri
          JOIN request_groups rg ON ri.group_id = rg.id
          JOIN consumables c ON ri.consumable_id = c.id
          WHERE rg.status = 'Approved'
            AND YEAR(rg.request_date) = :year";

if ($category && $category !== 'all') {
    $query .= " AND c.category = :category";
}

if ($department && $department !== 'all') {
    $query .= " AND rg.office = :department";
}

$query .= " GROUP BY c.category, rg.office, c.item_name
            ORDER BY c.category, rg.office, total_quantity DESC";

$stmt = $db->prepare($query);
$stmt->bindParam(':year', $year);

if ($category && $category !== 'all') {
    $stmt->bindParam(':category', $category);
}

if ($department && $department !== 'all') {
    $stmt->bindParam(':department', $department);
}

$stmt->execute();
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get summary totals - REMOVED unit_price
$summary_query = "SELECT 
                    SUM(ri.quantity) as total_consumption,
                    COUNT(DISTINCT rg.id) as total_requests,
                    COUNT(ri.id) as total_items,
                    COUNT(DISTINCT c.category) as total_categories,
                    COUNT(DISTINCT rg.office) as total_departments
                  FROM request_items ri
                  JOIN request_groups rg ON ri.group_id = rg.id
                  JOIN consumables c ON ri.consumable_id = c.id
                  WHERE rg.status = 'Approved'
                    AND YEAR(rg.request_date) = :year";

if ($category && $category !== 'all') {
    $summary_query .= " AND c.category = :category";
}

if ($department && $department !== 'all') {
    $summary_query .= " AND rg.office = :department";
}

$summary_stmt = $db->prepare($summary_query);
$summary_stmt->bindParam(':year', $year);

if ($category && $category !== 'all') {
    $summary_stmt->bindParam(':category', $category);
}

if ($department && $department !== 'all') {
    $summary_stmt->bindParam(':department', $department);
}

$summary_stmt->execute();
$summary = $summary_stmt->fetch(PDO::FETCH_ASSOC);

// Get category breakdown
$category_query = "SELECT 
                    c.category,
                    SUM(ri.quantity) as category_total,
                    COUNT(DISTINCT rg.id) as category_requests
                  FROM request_items ri
                  JOIN request_groups rg ON ri.group_id = rg.id
                  JOIN consumables c ON ri.consumable_id = c.id
                  WHERE rg.status = 'Approved'
                    AND YEAR(rg.request_date) = :year";

if ($category && $category !== 'all') {
    $category_query .= " AND c.category = :category";
}

if ($department && $department !== 'all') {
    $category_query .= " AND rg.office = :department";
}

$category_query .= " GROUP BY c.category
                     ORDER BY category_total DESC";

$category_stmt = $db->prepare($category_query);
$category_stmt->bindParam(':year', $year);

if ($category && $category !== 'all') {
    $category_stmt->bindParam(':category', $category);
}

if ($department && $department !== 'all') {
    $category_stmt->bindParam(':department', $department);
}

$category_stmt->execute();
$category_breakdown = $category_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get department breakdown
$dept_query = "SELECT 
                rg.office as department,
                SUM(ri.quantity) as dept_total,
                COUNT(DISTINCT rg.id) as dept_requests
              FROM request_items ri
              JOIN request_groups rg ON ri.group_id = rg.id
              JOIN consumables c ON ri.consumable_id = c.id
              WHERE rg.status = 'Approved'
                AND YEAR(rg.request_date) = :year";

if ($category && $category !== 'all') {
    $dept_query .= " AND c.category = :category";
}

if ($department && $department !== 'all') {
    $dept_query .= " AND rg.office = :department";
}

$dept_query .= " GROUP BY rg.office
                 ORDER BY dept_total DESC";

$dept_stmt = $db->prepare($dept_query);
$dept_stmt->bindParam(':year', $year);

if ($category && $category !== 'all') {
    $dept_stmt->bindParam(':category', $category);
}

if ($department && $department !== 'all') {
    $dept_stmt->bindParam(':department', $department);
}

$dept_stmt->execute();
$dept_breakdown = $dept_stmt->fetchAll(PDO::FETCH_ASSOC);

// Prepare data for charts
$categories = [];
$category_totals = [];
$departments = [];
$dept_totals = [];

foreach ($category_breakdown as $row) {
    $categories[] = $row['category'];
    $category_totals[] = (int)$row['category_total'];
}

foreach ($dept_breakdown as $row) {
    $departments[] = $row['department'];
    $dept_totals[] = (int)$row['dept_total'];
}
?>

<!-- Summary Cards -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h6 class="text-muted mb-2">Total Consumption</h6>
                <h3 class="fw-bold mb-0"><?php echo number_format($summary['total_consumption'] ?? 0); ?></h3>
                <small class="text-success">items released</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h6 class="text-muted mb-2">Total Requests</h6>
                <h3 class="fw-bold mb-0"><?php echo number_format($summary['total_requests'] ?? 0); ?></h3>
                <small class="text-success">approved requests</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h6 class="text-muted mb-2">Active Categories</h6>
                <h3 class="fw-bold mb-0"><?php echo number_format($summary['total_categories'] ?? 0); ?></h3>
                <small class="text-success">with consumption</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h6 class="text-muted mb-2">Departments Served</h6>
                <h3 class="fw-bold mb-0"><?php echo number_format($summary['total_departments'] ?? 0); ?></h3>
                <small class="text-success">active departments</small>
            </div>
        </div>
    </div>
</div>

<!-- Charts Row -->
<div class="row mb-4 g-3">
    <!-- Category doughnut -->
    <div class="col-md-5">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-0 pt-3 pb-0">
                <h6 class="fw-bold mb-0"><i class="fas fa-chart-pie text-primary me-2"></i>Consumption by Category</h6>
                <small class="text-muted">Total items per category in <?php echo $year; ?></small>
            </div>
            <div class="card-body" style="position:relative;height:260px;">
                <?php if(empty($category_breakdown)): ?>
                <div class="d-flex align-items-center justify-content-center h-100 text-muted">
                    <div class="text-center"><i class="fas fa-chart-pie fa-2x mb-2"></i><p class="mb-0">No data</p></div>
                </div>
                <?php else: ?>
                <canvas id="annualCategoryChart"></canvas>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <!-- Department bar -->
    <div class="col-md-7">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-0 pt-3 pb-0">
                <h6 class="fw-bold mb-0"><i class="fas fa-chart-bar text-success me-2"></i>Consumption by Department</h6>
                <small class="text-muted">Top departments in <?php echo $year; ?></small>
            </div>
            <div class="card-body" style="position:relative;height:260px;">
                <?php if(empty($dept_breakdown)): ?>
                <div class="d-flex align-items-center justify-content-center h-100 text-muted">
                    <div class="text-center"><i class="fas fa-chart-bar fa-2x mb-2"></i><p class="mb-0">No data</p></div>
                </div>
                <?php else: ?>
                <canvas id="annualDeptChart"></canvas>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Detailed Table -->
<?php
// Group results: [category][department] => ['items'=>[...], 'subtotal'=>n, 'req_count'=>n]
$grouped = [];
$grand_total = 0;
foreach ($results as $r) {
    $cat  = $r['category'];
    $dept = $r['department'];
    if (!isset($grouped[$cat])) $grouped[$cat] = [];
    if (!isset($grouped[$cat][$dept])) {
        $grouped[$cat][$dept] = ['items' => [], 'subtotal' => 0, 'req_count' => 0, 'item_count' => 0];
    }
    $grouped[$cat][$dept]['items'][]     = $r;
    $grouped[$cat][$dept]['subtotal']   += (int)$r['total_quantity'];
    $grouped[$cat][$dept]['req_count']  += (int)$r['request_count'];
    $grouped[$cat][$dept]['item_count'] += (int)$r['item_count'];
    $grand_total += (int)$r['total_quantity'];
}
?>
<div class="table-responsive">
    <table class="table table-bordered table-hover align-middle" style="font-size:0.875rem;">
        <thead class="table-success">
            <tr>
                <th>Category</th>
                <th>Department</th>
                <th>Requested Items</th>
                <th class="text-center">Requests</th>
                <th class="text-end">Total Qty</th>
                <th class="text-center">Avg/Request</th>
            </tr>
        </thead>
        <tbody>
        <?php if(empty($grouped)): ?>
            <tr>
                <td colspan="6" class="text-center py-5">
                    <i class="fas fa-chart-pie fa-2x text-muted mb-2"></i>
                    <p class="mb-0">No consumption data found for the selected filters</p>
                </td>
            </tr>
        <?php else: ?>
        <?php foreach($grouped as $cat => $depts): ?>
            <?php
            $cat_row_count = count($depts);
            $cat_first = true;
            $cat_total = array_sum(array_column($depts, 'subtotal'));
            ?>
            <?php foreach($depts as $dept => $data): ?>
            <tr>
                <?php if($cat_first): ?>
                <td rowspan="<?php echo $cat_row_count; ?>" style="vertical-align:middle;background:#f0fff4;">
                    <span class="badge rounded-pill" style="background:#d1e7dd;color:#0a5e3a;font-size:0.82rem;padding:0.45em 0.85em;">
                        <i class="fas fa-folder me-1"></i><?php echo htmlspecialchars($cat); ?>
                    </span>
                    <div class="text-muted mt-1" style="font-size:0.72rem;">
                        Subtotal: <strong><?php echo number_format($cat_total); ?></strong>
                    </div>
                </td>
                <?php $cat_first = false; ?>
                <?php endif; ?>

                <td style="vertical-align:middle;">
                    <span class="fw-semibold"><?php echo htmlspecialchars($dept); ?></span>
                </td>

                <!-- Item names with individual quantities -->
                <td>
                    <div class="d-flex flex-column gap-1">
                    <?php foreach($data['items'] as $item): ?>
                        <div class="d-flex justify-content-between align-items-center py-1 px-2 rounded"
                             style="background:#f8f9fa;border-left:3px solid #198754;">
                            <span style="font-size:0.82rem;"><?php echo htmlspecialchars($item['item_name']); ?></span>
                            <span class="badge bg-success ms-2" style="font-size:0.75rem;white-space:nowrap;">
                                <?php echo number_format($item['total_quantity']); ?>
                                <?php echo htmlspecialchars($item['unit'] ?? ''); ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                    </div>
                </td>

                <td class="text-center"><?php echo $data['req_count']; ?></td>
                <td class="text-end fw-bold text-success"><?php echo number_format($data['subtotal']); ?></td>
                <td class="text-center">
                    <?php
                    $avg = $data['req_count'] > 0 ? round($data['subtotal'] / $data['req_count'], 1) : 0;
                    echo $avg;
                    ?>
                </td>
            </tr>
            <?php endforeach; ?>
        <?php endforeach; ?>
        <tr class="table-secondary fw-bold">
            <td colspan="4" class="text-end">GRAND TOTAL</td>
            <td class="text-end text-success"><?php echo number_format($grand_total); ?></td>
            <td></td>
        </tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Additional Statistics -->
<?php if(!empty($results)): ?>
<div class="row mt-4">
    <div class="col-md-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0 pt-3">
                <h6 class="fw-bold mb-0">Top Categories</h6>
            </div>
            <div class="card-body">
                <div class="list-group list-group-flush">
                    <?php 
                    $top_categories = array_slice($category_breakdown, 0, 5);
                    foreach($top_categories as $index => $cat): 
                    ?>
                    <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <div>
                            <span class="badge bg-primary me-2"><?php echo $index + 1; ?></span>
                            <strong><?php echo htmlspecialchars($cat['category']); ?></strong>
                        </div>
                        <div>
                            <span class="badge bg-info"><?php echo $cat['category_requests']; ?> requests</span>
                            <span class="badge bg-success ms-2"><?php echo number_format($cat['category_total']); ?> items</span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0 pt-3">
                <h6 class="fw-bold mb-0">Top Departments</h6>
            </div>
            <div class="card-body">
                <div class="list-group list-group-flush">
                    <?php 
                    $top_departments = array_slice($dept_breakdown, 0, 5);
                    foreach($top_departments as $index => $dept): 
                    ?>
                    <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <div>
                            <span class="badge bg-primary me-2"><?php echo $index + 1; ?></span>
                            <strong><?php echo htmlspecialchars($dept['department']); ?></strong>
                        </div>
                        <div>
                            <span class="badge bg-info"><?php echo $dept['dept_requests']; ?> requests</span>
                            <span class="badge bg-success ms-2"><?php echo number_format($dept['dept_total']); ?> items</span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
(function() {
    // Destroy any old instances first
    ['annualCategoryChart','annualDeptChart'].forEach(function(id) {
        var existing = Chart.getChart(id);
        if (existing) existing.destroy();
    });

    // ── Doughnut: by category ──
    var catCtx = document.getElementById('annualCategoryChart');
    if (catCtx && <?php echo json_encode(!empty($categories)); ?>) {
        new Chart(catCtx.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode($categories); ?>,
                datasets: [{
                    data: <?php echo json_encode($category_totals); ?>,
                    backgroundColor: [
                        'rgba(13,110,253,0.8)','rgba(25,135,84,0.8)','rgba(255,193,7,0.8)',
                        'rgba(220,53,69,0.8)', 'rgba(13,202,240,0.8)','rgba(111,66,193,0.8)',
                        'rgba(253,126,20,0.8)','rgba(23,162,184,0.8)'
                    ],
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { boxWidth: 12, font: { size: 10 }, padding: 8 }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(ctx) {
                                var total = ctx.dataset.data.reduce(function(a,b){return a+b;},0);
                                var pct = total > 0 ? ((ctx.parsed / total) * 100).toFixed(1) : 0;
                                return ' ' + ctx.parsed.toLocaleString() + ' (' + pct + '%)';
                            }
                        }
                    }
                }
            }
        });
    }

    // ── Bar: by department ──
    var deptCtx = document.getElementById('annualDeptChart');
    if (deptCtx && <?php echo json_encode(!empty($departments)); ?>) {
        new Chart(deptCtx.getContext('2d'), {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_slice($departments, 0, 8)); ?>,
                datasets: [{
                    label: 'Items Consumed',
                    data: <?php echo json_encode(array_slice($dept_totals, 0, 8)); ?>,
                    backgroundColor: 'rgba(25,135,84,0.75)',
                    borderColor: 'rgba(25,135,84,1)',
                    borderWidth: 1,
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { font: { size: 10 } },
                        grid: { color: 'rgba(0,0,0,0.05)' }
                    },
                    x: {
                        ticks: { maxRotation: 40, minRotation: 20, font: { size: 9 } },
                        grid: { display: false }
                    }
                }
            }
        });
    }
})();
</script>