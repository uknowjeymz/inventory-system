<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../index.php");
    exit();
}

require_once '../config/database.php';
$database = new Database();
$db = $database->getConnection();

// Get all location types with room counts and equipment counts
$query = "SELECT lt.*, 
          COUNT(DISTINCT l.id) as room_count,
          COALESCE(SUM(
              CASE 
                  WHEN lt.type_code = 'computer_lab' THEN (SELECT COUNT(*) FROM computer_inventory WHERE location_id = l.id)
                  WHEN lt.type_code = 'kitchen' THEN (SELECT COUNT(*) FROM kitchen_equipment WHERE location_id = l.id)
                  WHEN lt.type_code = 'office' THEN (SELECT COUNT(*) FROM office_equipment WHERE location_id = l.id)
                  WHEN lt.type_code = 'regular_lab' THEN (SELECT COUNT(*) FROM lab_equipment WHERE location_id = l.id)
                  ELSE (SELECT COUNT(*) FROM general_equipment WHERE location_id = l.id)
              END
          ), 0) as total_equipment
          FROM location_types lt 
          LEFT JOIN locations l ON lt.id = l.location_type_id OR lt.type_code = l.location_type
          WHERE lt.is_active = 1
          GROUP BY lt.id 
          ORDER BY lt.type_name";
$stmt = $db->prepare($query);
$stmt->execute();
$location_types = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get total statistics
$total_rooms = array_sum(array_column($location_types, 'room_count'));
$total_equipment = array_sum(array_column($location_types, 'total_equipment'));

$page_title = "Inventory Categories";
include '../includes/header.php';
?>

<style>
:root {
    --card-transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    --ucc-green-primary: #2E7D32;
    --ucc-green-secondary: #4CAF50;
    --ucc-green-light: #81C784;
    --ucc-green-soft: #E8F5E9;
    --ucc-green-mint: #C8E6C9;
    --ucc-green-dark: #1B5E20;
    --ucc-white: #FFFFFF;
    --ucc-off-white: #F8F9FA;
}

/* Hero Section - Green Theme */
.inventory-hero {
    background: linear-gradient(135deg, var(--ucc-green-primary) 0%, var(--ucc-green-dark) 100%);
    border-radius: 30px;
    padding: 3rem 2rem;
    margin-bottom: 3rem;
    color: white;
    position: relative;
    overflow: hidden;
    box-shadow: 0 20px 40px rgba(46, 125, 50, 0.2);
}

.inventory-hero::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -50%;
    width: 200%;
    height: 200%;
    background: radial-gradient(circle, rgba(129, 199, 132, 0.2) 0%, transparent 70%);
    animation: rotate 20s linear infinite;
}

@keyframes rotate {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

.hero-content {
    position: relative;
    z-index: 2;
}

.hero-title {
    font-size: 2.5rem;
    font-weight: 800;
    margin-bottom: 1rem;
    text-shadow: 2px 2px 4px rgba(0,0,0,0.1);
}

.hero-subtitle {
    font-size: 1.1rem;
    opacity: 0.95;
    margin-bottom: 2rem;
}

.hero-stats {
    display: flex;
    gap: 3rem;
    flex-wrap: wrap;
}

.hero-stat-item {
    text-align: center;
}

.hero-stat-number {
    font-size: 2.5rem;
    font-weight: 800;
    display: block;
    line-height: 1;
    margin-bottom: 0.5rem;
}

.hero-stat-label {
    font-size: 0.9rem;
    opacity: 0.9;
    text-transform: uppercase;
    letter-spacing: 1px;
}

/* Stats Cards - Green Theme */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2.5rem;
}

.stat-card {
    background: white;
    border-radius: 20px;
    padding: 1.5rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    box-shadow: 0 10px 30px rgba(46, 125, 50, 0.08);
    transition: var(--card-transition);
    border: 1px solid var(--ucc-green-mint);
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 20px 40px rgba(46, 125, 50, 0.15);
}

.stat-icon {
    width: 60px;
    height: 60px;
    border-radius: 15px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.8rem;
}

.stat-icon.primary { background: linear-gradient(135deg, var(--ucc-green-primary) 0%, var(--ucc-green-secondary) 100%); color: white; }
.stat-icon.success { background: linear-gradient(135deg, var(--ucc-green-secondary) 0%, var(--ucc-green-light) 100%); color: white; }
.stat-icon.info { background: linear-gradient(135deg, #26A69A 0%, #4DB6AC 100%); color: white; }
.stat-icon.warning { background: linear-gradient(135deg, #FFA726 0%, #FFB74D 100%); color: white; }

.stat-content {
    flex: 1;
}

.stat-content h3 {
    font-size: 2rem;
    font-weight: 800;
    margin: 0;
    line-height: 1.2;
    color: var(--ucc-green-dark);
}

.stat-content p {
    margin: 0;
    color: #546E7A;
    font-size: 0.9rem;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* Category Cards - Green Theme */
.categories-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.category-card {
    background: white;
    border-radius: 24px;
    overflow: hidden;
    box-shadow: 0 10px 30px rgba(46, 125, 50, 0.08);
    transition: var(--card-transition);
    cursor: pointer;
    border: 1px solid var(--ucc-green-mint);
    position: relative;
}

.category-card:hover {
    transform: translateY(-8px);
    box-shadow: 0 30px 60px rgba(46, 125, 50, 0.15);
}

.category-header {
    padding: 2rem 1.5rem;
    color: white;
    position: relative;
    overflow: hidden;
    background: linear-gradient(135deg, var(--ucc-green-primary) 0%, var(--ucc-green-secondary) 100%);
}

.category-header::after {
    content: '';
    position: absolute;
    top: -50%;
    right: -50%;
    width: 200%;
    height: 200%;
    background: radial-gradient(circle, rgba(255,255,255,0.2) 0%, transparent 70%);
    opacity: 0;
    transition: opacity 0.3s ease;
}

.category-card:hover .category-header::after {
    opacity: 1;
}

.category-icon {
    font-size: 3rem;
    margin-bottom: 1rem;
    position: relative;
    z-index: 2;
}

.category-title {
    font-size: 1.8rem;
    font-weight: 700;
    margin-bottom: 0.5rem;
    position: relative;
    z-index: 2;
}

.category-desc {
    font-size: 0.9rem;
    opacity: 0.9;
    position: relative;
    z-index: 2;
}

.category-body {
    padding: 1.5rem;
    background: white;
}

.category-stats {
    display: flex;
    justify-content: space-around;
    margin-bottom: 1.5rem;
    padding-bottom: 1.5rem;
    border-bottom: 2px dashed var(--ucc-green-mint);
}

.category-stat {
    text-align: center;
}

.category-stat-value {
    font-size: 1.8rem;
    font-weight: 800;
    display: block;
    color: var(--ucc-green-dark);
    line-height: 1.2;
}

.category-stat-label {
    font-size: 0.8rem;
    color: #546E7A;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    font-weight: 500;
}

.category-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.category-badge {
    padding: 0.4rem 1rem;
    border-radius: 50px;
    font-size: 0.8rem;
    font-weight: 600;
    background: var(--ucc-green-soft);
    color: var(--ucc-green-dark);
}

.category-btn {
    background: linear-gradient(135deg, var(--ucc-green-primary) 0%, var(--ucc-green-secondary) 100%);
    color: white;
    border: none;
    padding: 0.6rem 1.5rem;
    border-radius: 50px;
    font-size: 0.9rem;
    font-weight: 600;
    transition: var(--card-transition);
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.category-btn:hover {
    transform: translateX(5px);
    box-shadow: 0 10px 20px rgba(46, 125, 50, 0.3);
    color: white;
}

/* Quick Actions Panel - Green Theme */
.quick-actions-panel {
    background: white;
    border-radius: 20px;
    padding: 2rem;
    margin-top: 2rem;
    box-shadow: 0 10px 30px rgba(46, 125, 50, 0.08);
    border: 1px solid var(--ucc-green-mint);
}

.quick-actions-title {
    font-size: 1.3rem;
    font-weight: 700;
    color: var(--ucc-green-dark);
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.quick-actions-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
}

.quick-action-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem;
    border-radius: 12px;
    background: var(--ucc-green-soft);
    transition: var(--card-transition);
    text-decoration: none;
    color: var(--ucc-green-dark);
    border: 1px solid transparent;
}

.quick-action-item:hover {
    background: white;
    border-color: var(--ucc-green-primary);
    transform: translateY(-2px);
    box-shadow: 0 10px 20px rgba(46, 125, 50, 0.1);
}

.quick-action-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    color: white;
}

.quick-action-icon.primary { background: linear-gradient(135deg, var(--ucc-green-primary) 0%, var(--ucc-green-secondary) 100%); }
.quick-action-icon.success { background: linear-gradient(135deg, var(--ucc-green-secondary) 0%, var(--ucc-green-light) 100%); }
.quick-action-icon.info { background: linear-gradient(135deg, #26A69A 0%, #4DB6AC 100%); }
.quick-action-icon.warning { background: linear-gradient(135deg, #FFA726 0%, #FFB74D 100%); }

.quick-action-content h6 {
    font-weight: 600;
    margin: 0;
    font-size: 1rem;
    color: var(--ucc-green-dark);
}

.quick-action-content small {
    color: #546E7A;
    font-size: 0.8rem;
}

/* Empty State - Green Theme */
.empty-state {
    text-align: center;
    padding: 4rem 2rem;
    background: white;
    border-radius: 30px;
    box-shadow: 0 10px 30px rgba(46, 125, 50, 0.08);
}

.empty-state-icon {
    width: 120px;
    height: 120px;
    border-radius: 60px;
    background: linear-gradient(135deg, var(--ucc-green-primary) 0%, var(--ucc-green-secondary) 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 2rem;
    font-size: 3rem;
    color: white;
    box-shadow: 0 20px 40px rgba(46, 125, 50, 0.3);
}

.empty-state h3 {
    font-size: 1.8rem;
    font-weight: 700;
    color: var(--ucc-green-dark);
    margin-bottom: 1rem;
}

.empty-state p {
    color: #546E7A;
    margin-bottom: 2rem;
    max-width: 500px;
    margin-left: auto;
    margin-right: auto;
}

.empty-state .btn-create {
    background: linear-gradient(135deg, var(--ucc-green-primary) 0%, var(--ucc-green-secondary) 100%);
    color: white;
    border: none;
    padding: 1rem 2rem;
    border-radius: 50px;
    font-weight: 600;
    transition: var(--card-transition);
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.empty-state .btn-create:hover {
    transform: translateY(-2px);
    box-shadow: 0 20px 40px rgba(46, 125, 50, 0.4);
    color: white;
}

/* Loading Skeleton - Green Theme */
.skeleton-card {
    background: white;
    border-radius: 24px;
    padding: 1rem;
    box-shadow: 0 10px 30px rgba(46, 125, 50, 0.08);
}

.skeleton-header {
    height: 150px;
    background: linear-gradient(90deg, var(--ucc-green-soft) 25%, var(--ucc-green-mint) 50%, var(--ucc-green-soft) 75%);
    background-size: 200% 100%;
    animation: loading 1.5s infinite;
    border-radius: 20px 20px 0 0;
    margin: -1rem -1rem 1rem -1rem;
}

.skeleton-line {
    height: 20px;
    background: linear-gradient(90deg, var(--ucc-green-soft) 25%, var(--ucc-green-mint) 50%, var(--ucc-green-soft) 75%);
    background-size: 200% 100%;
    animation: loading 1.5s infinite;
    border-radius: 10px;
    margin-bottom: 1rem;
}

.skeleton-line.short { width: 60%; }
.skeleton-line.medium { width: 80%; }

@keyframes loading {
    0% { background-position: 200% 0; }
    100% { background-position: -200% 0; }
}

/* Responsive */
@media (max-width: 768px) {
    .hero-title { font-size: 2rem; }
    .hero-stats { gap: 1.5rem; }
    .hero-stat-number { font-size: 2rem; }
    .categories-grid { grid-template-columns: 1fr; }
    .quick-actions-grid { grid-template-columns: 1fr; }
}
</style>

<!-- Loading State (shown until page fully loads) -->
<div id="loading-skeleton" style="display: none;">
    <div class="stats-grid">
        <?php for ($i = 0; $i < 4; $i++): ?>
        <div class="skeleton-card">
            <div class="skeleton-header"></div>
            <div class="skeleton-line short"></div>
            <div class="skeleton-line medium"></div>
        </div>
        <?php endfor; ?>
    </div>
</div>

<!-- Hero Section -->
<div class="inventory-hero">
    <div class="hero-content">
        <div class="row align-items-center">
            <div class="col-lg-8">
                <h1 class="hero-title">
                    <i class="fas fa-warehouse me-3"></i>Inventory Management System
                </h1>
                <p class="hero-subtitle">
                    Organize and manage all your equipment across different categories and locations.
                    Track assignments, monitor usage, and maintain inventory control.
                </p>
                <div class="hero-stats">
                    <div class="hero-stat-item">
                        <span class="hero-stat-number"><?php echo count($location_types); ?></span>
                        <span class="hero-stat-label">Categories</span>
                    </div>
                    <div class="hero-stat-item">
                        <span class="hero-stat-number"><?php echo $total_rooms; ?></span>
                        <span class="hero-stat-label">Rooms</span>
                    </div>
                    <div class="hero-stat-item">
                        <span class="hero-stat-number"><?php echo $total_equipment; ?></span>
                        <span class="hero-stat-label">Equipment</span>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 text-lg-end">
                <a href="location_types.php" class="btn btn-light btn-lg px-4" style="color: var(--ucc-green-primary); border: none;">
                    <i class="fas fa-plus-circle me-2"></i>New Category
                </a>
            </div>
        </div>
    </div>
</div>

<?php if (empty($location_types)): ?>
<!-- Empty State -->
<div class="empty-state">
    <div class="empty-state-icon">
        <i class="fas fa-box-open"></i>
    </div>
    <h3>No Inventory Categories Found</h3>
    <p>Get started by creating your first inventory category. Categories help you organize different types of equipment and locations.</p>
    <a href="location_types.php" class="btn-create">
        <i class="fas fa-plus-circle me-2"></i>Create Your First Category
    </a>
</div>
<?php else: ?>

<!-- Statistics Cards -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon primary">
            <i class="fas fa-layer-group"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo count($location_types); ?></h3>
            <p>Total Categories</p>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon success">
            <i class="fas fa-door-open"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo $total_rooms; ?></h3>
            <p>Active Rooms</p>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon info">
            <i class="fas fa-boxes"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo $total_equipment; ?></h3>
            <p>Equipment Items</p>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon warning">
            <i class="fas fa-chart-line"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo round(($total_rooms > 0 ? ($total_equipment / $total_rooms) : 0), 1); ?></h3>
            <p>Avg Items/Room</p>
        </div>
    </div>
</div>

<!-- Categories Grid -->
<div class="categories-grid">
    <?php foreach ($location_types as $type): ?>
    <div class="category-card" onclick="window.location.href='inventory_rooms.php?category_id=<?php echo $type['id']; ?>'">
        <!-- Category Header with Gradient -->
        <div class="category-header">
            <div class="category-icon">
                <i class="fas <?php echo $type['icon_class']; ?>"></i>
            </div>
            <h3 class="category-title"><?php echo htmlspecialchars($type['type_name']); ?></h3>
            <p class="category-desc"><?php echo htmlspecialchars($type['description'] ?: 'No description'); ?></p>
        </div>
        
        <!-- Category Body -->
        <div class="category-body">
            <div class="category-stats">
                <div class="category-stat">
                    <span class="category-stat-value"><?php echo $type['room_count']; ?></span>
                    <span class="category-stat-label">Rooms</span>
                </div>
                <div class="category-stat">
                    <span class="category-stat-value"><?php echo $type['total_equipment']; ?></span>
                    <span class="category-stat-label">Equipment</span>
                </div>
            </div>
            
            <div class="category-footer">
                <span class="category-badge">
                    <i class="fas fa-tag me-1"></i>
                    <?php echo htmlspecialchars($type['type_code']); ?>
                </span>
                <span class="category-btn">
                    Manage <i class="fas fa-arrow-right"></i>
                </span>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Quick Actions Panel -->
<div class="quick-actions-panel">
    <h5 class="quick-actions-title">
        <i class="fas fa-bolt" style="color: var(--ucc-green-primary);"></i>
        Quick Actions
    </h5>
    
    <div class="quick-actions-grid">
        <a href="location_types.php" class="quick-action-item">
            <div class="quick-action-icon primary">
                <i class="fas fa-plus"></i>
            </div>
            <div class="quick-action-content">
                <h6>Add Category</h6>
                <small>Create new type</small>
            </div>
        </a>
        
        <a href="locations.php" class="quick-action-item">
            <div class="quick-action-icon success">
                <i class="fas fa-door-open"></i>
            </div>
            <div class="quick-action-content">
                <h6>Add Room</h6>
                <small>Create new location</small>
            </div>
        </a>
        
        <a href="users.php" class="quick-action-item">
            <div class="quick-action-icon info">
                <i class="fas fa-users"></i>
            </div>
            <div class="quick-action-content">
                <h6>Manage Users</h6>
                <small>Add/Edit users</small>
            </div>
        </a>
        
        <a href="all_equipment.php" class="quick-action-item">
            <div class="quick-action-icon warning">
                <i class="fas fa-list-alt"></i>
            </div>
            <div class="quick-action-content">
                <h6>All Equipment</h6>
                <small>View inventory</small>
            </div>
        </a>
        
        <a href="inventory_monitor.php" class="quick-action-item">
            <div class="quick-action-icon" style="background: linear-gradient(135deg, #FFA726 0%, #FFB74D 100%);">
                <i class="fas fa-chart-bar"></i>
            </div>
            <div class="quick-action-content">
                <h6>Reports</h6>
                <small>Analytics</small>
            </div>
        </a>
        
        <a href="consumables.php" class="quick-action-item">
            <div class="quick-action-icon" style="background: linear-gradient(135deg, #26A69A 0%, #4DB6AC 100%);">
                <i class="fas fa-tint"></i>
            </div>
            <div class="quick-action-content">
                <h6>Consumables</h6>
                <small>Manage supplies</small>
            </div>
        </a>
    </div>
</div>

<?php endif; ?>

<!-- Smooth scroll behavior -->
<style>
html {
    scroll-behavior: smooth;
}
</style>

<?php include '../includes/footer.php'; ?>