<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    die("Unauthorized access");
}

require_once '../config/database.php';
$database = new Database();
$db = $database->getConnection();

$year       = $_POST['year']       ?? date('Y');
$month      = $_POST['month']      ?? 'all';
$category   = $_POST['category']   ?? 'all';
$department = $_POST['department'] ?? 'all';

// ── 1. Main consumption data: one row per category + department + month + item ──
$query = "SELECT
            MONTH(rg.request_date)                     AS month_num,
            MONTHNAME(rg.request_date)                 AS month_name,
            c.category,
            rg.office                                  AS department,
            c.item_name,
            c.unit,
            SUM(ri.quantity)                           AS total_quantity,
            COUNT(DISTINCT rg.id)                      AS request_count
          FROM request_items ri
          JOIN request_groups rg ON ri.group_id = rg.id
          JOIN consumables    c  ON ri.consumable_id = c.id
          WHERE rg.status = 'Approved'
            AND YEAR(rg.request_date) = :year";

if ($month !== 'all') {
    $query .= " AND MONTH(rg.request_date) = :month";
}
if ($category !== 'all') {
    $query .= " AND c.category = :category";
}
if ($department !== 'all') {
    $query .= " AND rg.office = :department";
}

$query .= " GROUP BY MONTH(rg.request_date), c.category, rg.office, c.item_name
            ORDER BY MONTH(rg.request_date), c.category, rg.office, total_quantity DESC";

$stmt = $db->prepare($query);
$stmt->bindParam(':year', $year);
if ($month !== 'all')      { $stmt->bindParam(':month',      $month); }
if ($category !== 'all')   { $stmt->bindParam(':category',   $category); }
if ($department !== 'all') { $stmt->bindParam(':department', $department); }
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ── 2. Summary totals ──
$sum_query = "SELECT
                SUM(ri.quantity)          AS total_consumption,
                COUNT(DISTINCT rg.id)     AS total_requests,
                COUNT(ri.id)              AS total_line_items,
                COUNT(DISTINCT c.category) AS total_categories,
                COUNT(DISTINCT rg.office)  AS total_departments
              FROM request_items ri
              JOIN request_groups rg ON ri.group_id = rg.id
              JOIN consumables    c  ON ri.consumable_id = c.id
              WHERE rg.status = 'Approved'
                AND YEAR(rg.request_date) = :year";

if ($month !== 'all')      { $sum_query .= " AND MONTH(rg.request_date) = :month"; }
if ($category !== 'all')   { $sum_query .= " AND c.category = :category"; }
if ($department !== 'all') { $sum_query .= " AND rg.office = :department"; }

$sum_stmt = $db->prepare($sum_query);
$sum_stmt->bindParam(':year', $year);
if ($month !== 'all')      { $sum_stmt->bindParam(':month',      $month); }
if ($category !== 'all')   { $sum_stmt->bindParam(':category',   $category); }
if ($department !== 'all') { $sum_stmt->bindParam(':department', $department); }
$sum_stmt->execute();
$summary = $sum_stmt->fetch(PDO::FETCH_ASSOC);

// ── 3. Chart data – monthly totals (for bar chart) ──
$chart_monthly_query = "SELECT
                          MONTH(rg.request_date)    AS month_num,
                          MONTHNAME(rg.request_date) AS month_name,
                          SUM(ri.quantity)           AS total
                        FROM request_items ri
                        JOIN request_groups rg ON ri.group_id = rg.id
                        JOIN consumables    c  ON ri.consumable_id = c.id
                        WHERE rg.status = 'Approved'
                          AND YEAR(rg.request_date) = :year";
if ($category !== 'all')   { $chart_monthly_query .= " AND c.category = :category"; }
if ($department !== 'all') { $chart_monthly_query .= " AND rg.office = :department"; }
$chart_monthly_query .= " GROUP BY MONTH(rg.request_date) ORDER BY month_num";

$cm_stmt = $db->prepare($chart_monthly_query);
$cm_stmt->bindParam(':year', $year);
if ($category !== 'all')   { $cm_stmt->bindParam(':category',   $category); }
if ($department !== 'all') { $cm_stmt->bindParam(':department', $department); }
$cm_stmt->execute();
$chart_monthly_raw = $cm_stmt->fetchAll(PDO::FETCH_ASSOC);

// Build complete 12-month arrays
$all_months     = ['January','February','March','April','May','June','July','August','September','October','November','December'];
$monthly_labels = [];
$monthly_totals = [];
$monthly_map    = [];
foreach ($chart_monthly_raw as $r) { $monthly_map[(int)$r['month_num']] = (int)$r['total']; }
for ($m = 1; $m <= 12; $m++) {
    $monthly_labels[] = substr($all_months[$m - 1], 0, 3);
    $monthly_totals[] = $monthly_map[$m] ?? 0;
}

// ── 4. Chart data – top items (for doughnut) ──
$chart_item_query = "SELECT
                       c.item_name,
                       SUM(ri.quantity) AS total
                     FROM request_items ri
                     JOIN request_groups rg ON ri.group_id = rg.id
                     JOIN consumables    c  ON ri.consumable_id = c.id
                     WHERE rg.status = 'Approved'
                       AND YEAR(rg.request_date) = :year";
if ($month !== 'all')      { $chart_item_query .= " AND MONTH(rg.request_date) = :month"; }
if ($category !== 'all')   { $chart_item_query .= " AND c.category = :category"; }
if ($department !== 'all') { $chart_item_query .= " AND rg.office = :department"; }
$chart_item_query .= " GROUP BY c.item_name ORDER BY total DESC LIMIT 8";

$ci_stmt = $db->prepare($chart_item_query);
$ci_stmt->bindParam(':year', $year);
if ($month !== 'all')      { $ci_stmt->bindParam(':month',      $month); }
if ($category !== 'all')   { $ci_stmt->bindParam(':category',   $category); }
if ($department !== 'all') { $ci_stmt->bindParam(':department', $department); }
$ci_stmt->execute();
$chart_items_raw = $ci_stmt->fetchAll(PDO::FETCH_ASSOC);

$item_labels = [];
$item_totals = [];
foreach ($chart_items_raw as $r) {
    $item_labels[] = $r['item_name'];
    $item_totals[] = (int)$r['total'];
}

// ── 5. Group rows for the table display ──
// Structure: [month_num][category][department] => ['items' => [...], 'total' => n]
$grouped = [];
$grand_total = 0;
foreach ($rows as $r) {
    $mn = (int)$r['month_num'];
    $cat = $r['category'];
    $dept = $r['department'];
    if (!isset($grouped[$mn])) $grouped[$mn] = [];
    if (!isset($grouped[$mn][$cat])) $grouped[$mn][$cat] = [];
    if (!isset($grouped[$mn][$cat][$dept])) {
        $grouped[$mn][$cat][$dept] = ['items' => [], 'subtotal' => 0];
    }
    $grouped[$mn][$cat][$dept]['items'][]    = $r;
    $grouped[$mn][$cat][$dept]['subtotal']  += (int)$r['total_quantity'];
    $grand_total += (int)$r['total_quantity'];
}

$month_names = [1=>'January',2=>'February',3=>'March',4=>'April',5=>'May',6=>'June',
                7=>'July',8=>'August',9=>'September',10=>'October',11=>'November',12=>'December'];
?>

<!-- ═══════════════════════════ SUMMARY CARDS ═══════════════════════════ -->
<div class="row mb-4 g-3">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div style="width:46px;height:46px;border-radius:12px;background:linear-gradient(135deg,#0d6efd,#0b5ed7);display:flex;align-items:center;justify-content:center;color:#fff;font-size:1.3rem;flex-shrink:0;">
                    <i class="fas fa-boxes"></i>
                </div>
                <div>
                    <div class="text-muted small fw-semibold">Total Consumed</div>
                    <div class="fw-bold fs-4 lh-1"><?php echo number_format($summary['total_consumption'] ?? 0); ?></div>
                    <div class="text-muted" style="font-size:0.72rem;">items released</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div style="width:46px;height:46px;border-radius:12px;background:linear-gradient(135deg,#198754,#157347);display:flex;align-items:center;justify-content:center;color:#fff;font-size:1.3rem;flex-shrink:0;">
                    <i class="fas fa-file-alt"></i>
                </div>
                <div>
                    <div class="text-muted small fw-semibold">Approved Requests</div>
                    <div class="fw-bold fs-4 lh-1"><?php echo number_format($summary['total_requests'] ?? 0); ?></div>
                    <div class="text-muted" style="font-size:0.72rem;">total requests</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div style="width:46px;height:46px;border-radius:12px;background:linear-gradient(135deg,#ffc107,#ffca2c);display:flex;align-items:center;justify-content:center;color:#fff;font-size:1.3rem;flex-shrink:0;">
                    <i class="fas fa-layer-group"></i>
                </div>
                <div>
                    <div class="text-muted small fw-semibold">Categories</div>
                    <div class="fw-bold fs-4 lh-1"><?php echo number_format($summary['total_categories'] ?? 0); ?></div>
                    <div class="text-muted" style="font-size:0.72rem;">with consumption</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div style="width:46px;height:46px;border-radius:12px;background:linear-gradient(135deg,#0dcaf0,#31d2f2);display:flex;align-items:center;justify-content:center;color:#fff;font-size:1.3rem;flex-shrink:0;">
                    <i class="fas fa-building"></i>
                </div>
                <div>
                    <div class="text-muted small fw-semibold">Departments</div>
                    <div class="fw-bold fs-4 lh-1"><?php echo number_format($summary['total_departments'] ?? 0); ?></div>
                    <div class="text-muted" style="font-size:0.72rem;">departments served</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ═══════════════════════════ CHARTS ═══════════════════════════ -->
<div class="row mb-4 g-3">
    <!-- Bar chart: monthly totals -->
    <div class="col-md-7">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-0 pt-3 pb-0">
                <h6 class="fw-bold mb-0"><i class="fas fa-chart-bar text-primary me-2"></i>Monthly Consumption Trend</h6>
                <small class="text-muted">Total items released per month in <?php echo $year; ?></small>
            </div>
            <div class="card-body" style="position:relative;height:260px;">
                <canvas id="monthlyBarChart"></canvas>
            </div>
        </div>
    </div>
    <!-- Doughnut chart: top items -->
    <div class="col-md-5">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-0 pt-3 pb-0">
                <h6 class="fw-bold mb-0"><i class="fas fa-chart-pie text-success me-2"></i>Top Requested Items</h6>
                <small class="text-muted">By total quantity consumed</small>
            </div>
            <div class="card-body" style="position:relative;height:260px;">
                <?php if (empty($item_labels)): ?>
                <div class="d-flex align-items-center justify-content-center h-100 text-muted">
                    <div class="text-center"><i class="fas fa-chart-pie fa-2x mb-2"></i><p class="mb-0">No data</p></div>
                </div>
                <?php else: ?>
                <canvas id="monthlyDoughnutChart"></canvas>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- ═══════════════════════════ CONSUMPTION TABLE ═══════════════════════════ -->
<?php if (empty($rows)): ?>
<div class="text-center py-5">
    <i class="fas fa-chart-line fa-3x text-muted mb-3"></i>
    <h6 class="text-muted">No consumption data found for the selected filters.</h6>
    <p class="text-muted small">Try adjusting the year, month, category or department filter.</p>
</div>
<?php else: ?>

<div class="table-responsive">
    <table class="table table-bordered table-hover align-middle" style="font-size:0.875rem;">
        <thead class="table-primary">
            <tr>
                <th style="width:110px;">Month</th>
                <th>Category</th>
                <th>Department</th>
                <th>Requested Items</th>
                <th class="text-center" style="width:100px;">Requests</th>
                <th class="text-end" style="width:120px;">Total Qty</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($grouped as $mn => $cats): ?>
            <?php
            // Count total rows for this month to set rowspan
            $month_row_count = 0;
            foreach ($cats as $dept_arr) { $month_row_count += count($dept_arr); }
            $month_first = true;
            ?>
            <?php foreach ($cats as $cat => $depts): ?>
                <?php
                $cat_row_count = count($depts);
                $cat_first = true;
                ?>
                <?php foreach ($depts as $dept => $data): ?>
                <tr>
                    <?php if ($month_first): ?>
                    <td rowspan="<?php echo $month_row_count; ?>" class="text-center fw-bold" style="background:#e7f1ff;vertical-align:middle;">
                        <span class="badge bg-primary rounded-pill px-3 py-2" style="font-size:0.8rem;">
                            <?php echo $month_names[$mn]; ?>
                        </span>
                    </td>
                    <?php $month_first = false; ?>
                    <?php endif; ?>

                    <?php if ($cat_first): ?>
                    <td rowspan="<?php echo $cat_row_count; ?>" style="vertical-align:middle;background:#f8f9fa;">
                        <span class="badge rounded-pill" style="background:#e7f1ff;color:#0b5ed7;font-size:0.8rem;padding:0.4em 0.8em;">
                            <i class="fas fa-folder me-1"></i><?php echo htmlspecialchars($cat); ?>
                        </span>
                    </td>
                    <?php $cat_first = false; ?>
                    <?php endif; ?>

                    <td style="vertical-align:middle;">
                        <span class="fw-semibold text-dark"><?php echo htmlspecialchars($dept); ?></span>
                    </td>

                    <!-- Items list with individual quantities -->
                    <td>
                        <div class="d-flex flex-column gap-1">
                        <?php foreach ($data['items'] as $item): ?>
                            <div class="d-flex justify-content-between align-items-center py-1 px-2 rounded"
                                 style="background:#f8f9fa;border-left:3px solid #0d6efd;">
                                <span class="text-dark" style="font-size:0.82rem;">
                                    <?php echo htmlspecialchars($item['item_name']); ?>
                                </span>
                                <span class="badge bg-primary ms-2" style="font-size:0.75rem;white-space:nowrap;">
                                    <?php echo number_format($item['total_quantity']); ?>
                                    <?php echo htmlspecialchars($item['unit'] ?? ''); ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                        </div>
                    </td>

                    <td class="text-center">
                        <?php
                        $total_reqs = array_sum(array_column($data['items'], 'request_count'));
                        echo $total_reqs;
                        ?>
                    </td>

                    <td class="text-end fw-bold text-primary">
                        <?php echo number_format($data['subtotal']); ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endforeach; ?>
        <?php endforeach; ?>

        <!-- Grand total row -->
        <tr class="table-secondary fw-bold">
            <td colspan="5" class="text-end">GRAND TOTAL</td>
            <td class="text-end text-primary"><?php echo number_format($grand_total); ?></td>
        </tr>
        </tbody>
    </table>
</div>
<?php endif; ?>

<!-- ═══════════════════════════ CHART SCRIPTS ═══════════════════════════ -->
<script>
(function() {
    // Destroy any previously created monthly charts before recreating
    ['monthlyBarChart','monthlyDoughnutChart'].forEach(function(id) {
        var existing = Chart.getChart(id);
        if (existing) existing.destroy();
    });

    // ── Bar chart: monthly totals ──
    var barCtx = document.getElementById('monthlyBarChart');
    if (barCtx) {
        new Chart(barCtx.getContext('2d'), {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($monthly_labels); ?>,
                datasets: [{
                    label: 'Items Consumed',
                    data: <?php echo json_encode($monthly_totals); ?>,
                    backgroundColor: function(ctx) {
                        var idx = ctx.dataIndex;
                        var alpha = 0.5 + (0.5 * (ctx.dataset.data[idx] / (Math.max(...ctx.dataset.data) || 1)));
                        return 'rgba(13,110,253,' + alpha.toFixed(2) + ')';
                    },
                    borderColor: 'rgba(13,110,253,1)',
                    borderWidth: 1,
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(ctx) { return ' ' + ctx.parsed.y.toLocaleString() + ' items'; }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { font: { size: 10 } },
                        grid: { color: 'rgba(0,0,0,0.05)' }
                    },
                    x: {
                        ticks: { font: { size: 10 } },
                        grid: { display: false }
                    }
                }
            }
        });
    }

    // ── Doughnut chart: top items ──
    var dCtx = document.getElementById('monthlyDoughnutChart');
    if (dCtx && <?php echo json_encode(!empty($item_labels)); ?>) {
        new Chart(dCtx.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode($item_labels); ?>,
                datasets: [{
                    data: <?php echo json_encode($item_totals); ?>,
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
                        position: 'right',
                        labels: { boxWidth: 12, font: { size: 10 }, padding: 8 }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(ctx) {
                                var total = ctx.dataset.data.reduce(function(a,b){return a+b;},0);
                                var pct   = total > 0 ? ((ctx.parsed / total) * 100).toFixed(1) : 0;
                                return ' ' + ctx.parsed.toLocaleString() + ' (' + pct + '%)';
                            }
                        }
                    }
                }
            }
        });
    }
})();
</script>
