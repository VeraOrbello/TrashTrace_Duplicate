<?php
require_once "../config.php";

if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){
    header("location: ../login.php");
    exit;
}

if($_SESSION["user_type"] !== 'driver'){
    header("location: ../dashboard.php");
    exit;
}

$driver_id = $_SESSION["id"];
$driver_name = $_SESSION["full_name"];

// Get filter parameters
$filter_status = $_GET['status'] ?? 'all';
$filter_date = $_GET['date'] ?? 'all';
$filter_waste = $_GET['waste'] ?? 'all';
$search = $_GET['search'] ?? '';

// Fetch collections history
$history_data = [];

try {
    $sql = "SELECT 
                c.*, 
                u.full_name as customer_name,
                u.phone as customer_phone,
                a.zone_name,
                a.area
            FROM collections c
            LEFT JOIN users u ON c.customer_id = u.id
            LEFT JOIN assignments a ON c.assignment_id = a.id
            WHERE c.driver_id = ?";
    
    $params = [$driver_id];
    $types = "i";
    
    // Apply filters
    if($filter_status !== 'all') {
        $sql .= " AND c.status = ?";
        $params[] = $filter_status;
        $types .= "s";
    }
    
    if($filter_waste !== 'all') {
        $sql .= " AND c.waste_type = ?";
        $params[] = $filter_waste;
        $types .= "s";
    }
    
    if($filter_date !== 'all') {
        $today = date('Y-m-d');
        if($filter_date === 'today') {
            $sql .= " AND DATE(c.collection_date) = ?";
            $params[] = $today;
            $types .= "s";
        } elseif($filter_date === 'week') {
            $week_ago = date('Y-m-d', strtotime('-7 days'));
            $sql .= " AND DATE(c.collection_date) >= ?";
            $params[] = $week_ago;
            $types .= "s";
        } elseif($filter_date === 'month') {
            $month_ago = date('Y-m-d', strtotime('-30 days'));
            $sql .= " AND DATE(c.collection_date) >= ?";
            $params[] = $month_ago;
            $types .= "s";
        }
    }
    
    if(!empty($search)) {
        $sql .= " AND (u.full_name LIKE ? OR c.pickup_address LIKE ? OR c.waste_type LIKE ?)";
        $search_term = "%$search%";
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
        $types .= "sss";
    }
    
    $sql .= " ORDER BY c.collection_date DESC";
    
    if($stmt = mysqli_prepare($link, $sql)){
        if(!empty($params)) {
            mysqli_stmt_bind_param($stmt, $types, ...$params);
        }
        if(mysqli_stmt_execute($stmt)){
            $result = mysqli_stmt_get_result($stmt);
            while($row = mysqli_fetch_assoc($result)){
                $history_data[] = $row;
            }
        }
        mysqli_stmt_close($stmt);
    }
    
} catch(Exception $e) {
    error_log("History error: " . $e->getMessage());
    // Generate sample data for demo
    $history_data = generateSampleHistory();
}

function generateSampleHistory() {
    $sample_data = [];
    $statuses = ['completed', 'cancelled', 'pending'];
    $waste_types = ['Plastic', 'Paper', 'Glass', 'Metal', 'Organic', 'E-waste'];
    $customers = ['Juan Dela Cruz', 'Maria Santos', 'Pedro Reyes', 'Ana Lopez', 'James Wilson'];
    $areas = ['Lahug, Cebu', 'Apas, Cebu', 'IT Park, Cebu', 'Mabolo, Cebu'];
    
    for($i = 1; $i <= 25; $i++) {
        $weight = rand(10, 100);
        $amount = $weight * 5;
        $days_ago = rand(0, 90);
        
        $sample_data[] = [
            'id' => $i,
            'collection_date' => date('Y-m-d H:i:s', strtotime("-$days_ago days")),
            'customer_name' => $customers[array_rand($customers)],
            'customer_phone' => '09' . rand(100000000, 999999999),
            'pickup_address' => $areas[array_rand($areas)],
            'waste_type' => $waste_types[array_rand($waste_types)],
            'weight_kg' => $weight,
            'payment_amount' => $amount,
            'status' => $statuses[array_rand($statuses)],
            'zone_name' => 'Zone ' . chr(65 + ($i % 5)),
            'area' => $areas[array_rand($areas)],
            'notes' => $i % 3 == 0 ? 'Special handling required' : 'No issues'
        ];
    }
    
    return $sample_data;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>History - TrashTrace Driver</title>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- History CSS -->
    <link rel="stylesheet" href="../css/driver/master-styles.css">
</head>
<body>
    <div class="dashboard-container">
        <!-- Updated Navbar -->
        <header class="dashboard-header">
            <!-- Grid Background Pattern -->
            <div class="grid-background-nav"></div>
            
            <div class="header-content">
                <a href="../driver_dashboard.php" class="logo">
                    <i class="fas fa-recycle"></i>
                    <span>TrashTrace Driver</span>
                </a>
                
                <button class="mobile-menu-toggle" id="mobileMenuToggle">
                    <i class="fas fa-bars"></i>
                </button>
                
                <nav id="mainNav">
                    <div class="nav-container">
                        <ul>
                            <li><a href="../driver_dashboard.php" class="nav-link"><i class="fas fa-tachometer-alt"></i> <span>Dashboard</span></a></li>
                            <li><a href="assignments.php" class="nav-link"><i class="fas fa-tasks"></i> <span>Assignments</span></a></li>
                            <li><a href="routes.php" class="nav-link"><i class="fas fa-route"></i> <span>Routes</span></a></li>
                            <li><a href="collections.php" class="nav-link"><i class="fas fa-trash"></i> <span>Collections</span></a></li>
                            <li><a href="earnings.php" class="nav-link"><i class="fas fa-money-bill-wave"></i> <span>Earnings</span></a></li>
                            <li><a href="history.php" class="nav-link active"><i class="fas fa-history"></i> <span>History</span></a></li>
                            <li><a href="profile.php" class="nav-link"><i class="fas fa-user"></i> <span>Profile</span></a></li>
                        </ul>
                    </div>
                </nav>
                
                <div class="user-menu">
                    <div class="user-info" onclick="window.location.href='profile.php'">
                        <div class="user-avatar">
                            <?php echo strtoupper(substr($driver_name, 0, 1)); ?>
                        </div>
                        <div class="user-details">
                            <span class="user-name"><?php echo htmlspecialchars($driver_name); ?></span>
                            <span class="user-id">ID: #<?php echo str_pad($driver_id, 4, '0', STR_PAD_LEFT); ?></span>
                        </div>
                    </div>
                    <a href="../logout.php" class="btn-logout">
                        <i class="fas fa-sign-out-alt"></i>
                    </a>
                </div>
            </div>
        </header>
        
        <main class="dashboard-main">
            <div class="container">
                <div class="page-header">
                    <h1 class="page-title">
                        <i class="fas fa-history"></i>
                        Collection History
                    </h1>
                    <p class="page-subtitle">View your complete collection history with filters and search capabilities.</p>
                </div>
                
                <!-- Filters -->
                <div class="dashboard-card" style="margin-bottom: 30px;">
                    <div class="card-header">
                        <h3><i class="fas fa-filter"></i> Filters</h3>
                    </div>
                    
                    <form method="GET" action="history.php" id="filterForm">
                        <div class="filters">
                            <div class="filter-group">
                                <label for="status">Status</label>
                                <select name="status" id="status" class="filter-select">
                                    <option value="all" <?php echo $filter_status == 'all' ? 'selected' : ''; ?>>All Status</option>
                                    <option value="completed" <?php echo $filter_status == 'completed' ? 'selected' : ''; ?>>Completed</option>
                                    <option value="pending" <?php echo $filter_status == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="cancelled" <?php echo $filter_status == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                </select>
                            </div>
                            
                            <div class="filter-group">
                                <label for="date">Date Range</label>
                                <select name="date" id="date" class="filter-select">
                                    <option value="all" <?php echo $filter_date == 'all' ? 'selected' : ''; ?>>All Time</option>
                                    <option value="today" <?php echo $filter_date == 'today' ? 'selected' : ''; ?>>Today</option>
                                    <option value="week" <?php echo $filter_date == 'week' ? 'selected' : ''; ?>>Last 7 Days</option>
                                    <option value="month" <?php echo $filter_date == 'month' ? 'selected' : ''; ?>>Last 30 Days</option>
                                </select>
                            </div>
                            
                            <div class="filter-group">
                                <label for="waste">Waste Type</label>
                                <select name="waste" id="waste" class="filter-select">
                                    <option value="all" <?php echo $filter_waste == 'all' ? 'selected' : ''; ?>>All Types</option>
                                    <option value="plastic" <?php echo $filter_waste == 'plastic' ? 'selected' : ''; ?>>Plastic</option>
                                    <option value="paper" <?php echo $filter_waste == 'paper' ? 'selected' : ''; ?>>Paper</option>
                                    <option value="glass" <?php echo $filter_waste == 'glass' ? 'selected' : ''; ?>>Glass</option>
                                    <option value="metal" <?php echo $filter_waste == 'metal' ? 'selected' : ''; ?>>Metal</option>
                                    <option value="organic" <?php echo $filter_waste == 'organic' ? 'selected' : ''; ?>>Organic</option>
                                    <option value="e-waste" <?php echo $filter_waste == 'e-waste' ? 'selected' : ''; ?>>E-waste</option>
                                </select>
                            </div>
                            
                            <div class="filter-group">
                                <label for="search">Search</label>
                                <input type="text" name="search" id="search" class="filter-select" placeholder="Search customer or address..." value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                        </div>
                        
                        <div style="display: flex; gap: 10px; margin-top: 20px;">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search"></i> Apply Filters
                            </button>
                            <button type="button" id="resetFilters" class="btn btn-outline">
                                <i class="fas fa-redo"></i> Reset
                            </button>
                            <button type="button" id="exportHistory" class="btn btn-secondary">
                                <i class="fas fa-download"></i> Export
                            </button>
                        </div>
                    </form>
                </div>
                
                <!-- Statistics -->
                <div class="stats-grid" style="margin-bottom: 30px;">
                    <?php
                    $completed = count(array_filter($history_data, function($h) { return $h['status'] == 'completed'; }));
                    $cancelled = count(array_filter($history_data, function($h) { return $h['status'] == 'cancelled'; }));
                    $pending = count(array_filter($history_data, function($h) { return $h['status'] == 'pending'; }));
                    $total_weight = array_sum(array_column($history_data, 'weight_kg'));
                    $total_amount = array_sum(array_column($history_data, 'payment_amount'));
                    ?>
                    
                    <div class="stat-card">
                        <div class="stat-content">
                            <div class="stat-icon" style="background: linear-gradient(135deg, #4CAF50, #2E7D32);">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <div class="stat-info">
                                <h3><?php echo $completed; ?></h3>
                                <p>Completed</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-content">
                            <div class="stat-icon" style="background: linear-gradient(135deg, #F44336, #D32F2F);">
                                <i class="fas fa-times-circle"></i>
                            </div>
                            <div class="stat-info">
                                <h3><?php echo $cancelled; ?></h3>
                                <p>Cancelled</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-content">
                            <div class="stat-icon" style="background: linear-gradient(135deg, #FF9800, #F57C00);">
                                <i class="fas fa-clock"></i>
                            </div>
                            <div class="stat-info">
                                <h3><?php echo $pending; ?></h3>
                                <p>Pending</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-content">
                            <div class="stat-icon" style="background: linear-gradient(135deg, #2196F3, #0D47A1);">
                                <i class="fas fa-weight-hanging"></i>
                            </div>
                            <div class="stat-info">
                                <h3><?php echo number_format($total_weight, 0); ?> kg</h3>
                                <p>Total Weight</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-content">
                            <div class="stat-icon" style="background: linear-gradient(135deg, #9C27B0, #6A1B9A);">
                                <i class="fas fa-money-bill-wave"></i>
                            </div>
                            <div class="stat-info">
                                <h3>₱<?php echo number_format($total_amount, 2); ?></h3>
                                <p>Total Amount</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- History Table -->
                <div class="dashboard-card">
                    <div class="card-header">
                        <h3><i class="fas fa-list"></i> Collection History (<?php echo count($history_data); ?> records)</h3>
                    </div>
                    
                    <?php if(empty($history_data)): ?>
                        <div class="empty-state">
                            <i class="fas fa-history"></i>
                            <h3>No History Found</h3>
                            <p>No collections match your current filters.</p>
                            <button onclick="window.location.href='history.php'" class="btn btn-primary">
                                <i class="fas fa-redo"></i> Clear Filters
                            </button>
                        </div>
                    <?php else: ?>
                        <div class="table-container">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Date & Time</th>
                                        <th>Customer</th>
                                        <th>Zone</th>
                                        <th>Waste Type</th>
                                        <th>Weight</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($history_data as $item): ?>
                                        <tr>
                                            <td>
                                                <div style="font-weight: 500;"><?php echo date('M d, Y', strtotime($item['collection_date'])); ?></div>
                                                <div style="font-size: 0.8rem; color: #999;"><?php echo date('h:i A', strtotime($item['collection_date'])); ?></div>
                                            </td>
                                            <td>
                                                <div style="font-weight: 500;"><?php echo htmlspecialchars($item['customer_name']); ?></div>
                                                <div style="font-size: 0.8rem; color: #999;"><?php echo htmlspecialchars($item['customer_phone']); ?></div>
                                            </td>
                                            <td>
                                                <div style="font-weight: 500;"><?php echo htmlspecialchars($item['zone_name'] ?? 'N/A'); ?></div>
                                                <div style="font-size: 0.8rem; color: #999;"><?php echo htmlspecialchars($item['area'] ?? ''); ?></div>
                                            </td>
                                            <td>
                                                <span class="waste-badge waste-<?php echo htmlspecialchars(strtolower($item['waste_type'])); ?>">
                                                    <?php echo htmlspecialchars($item['waste_type']); ?>
                                                </span>
                                            </td>
                                            <td style="font-weight: 600; color: #2e7d32;">
                                                <?php echo number_format($item['weight_kg'], 1); ?> kg
                                            </td>
                                            <td style="font-weight: 600; color: #ff9800;">
                                                ₱<?php echo number_format($item['payment_amount'], 2); ?>
                                            </td>
                                            <td>
                                                <span class="status-badge status-<?php echo htmlspecialchars($item['status']); ?>">
                                                    <?php echo ucfirst($item['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="action-buttons">
                                                    <button class="btn-action btn-view view-details" data-id="<?php echo $item['id']; ?>">
                                                        <i class="fas fa-eye"></i> View
                                                    </button>
                                                    <?php if($item['status'] == 'completed'): ?>
                                                        <button class="btn-action btn-edit print-receipt" data-id="<?php echo $item['id']; ?>">
                                                            <i class="fas fa-print"></i> Print
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Pagination -->
                        <div class="pagination">
                            <button class="page-btn"><i class="fas fa-chevron-left"></i></button>
                            <button class="page-btn active">1</button>
                            <button class="page-btn">2</button>
                            <button class="page-btn">3</button>
                            <button class="page-btn">4</button>
                            <button class="page-btn">5</button>
                            <button class="page-btn"><i class="fas fa-chevron-right"></i></button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
        
        <!-- Details Modal -->
        <div class="modal" id="detailsModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
            <div style="background: white; border-radius: 16px; padding: 30px; max-width: 600px; width: 90%; max-height: 80vh; overflow-y: auto;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h3 style="margin: 0; color: #2e7d32;">Collection Details</h3>
                    <button id="closeModal" style="background: none; border: none; font-size: 20px; cursor: pointer; color: #999;">×</button>
                </div>
                <div id="modalContent">
                    <!-- Content will be loaded here -->
                </div>
            </div>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Mobile menu toggle
            const mobileMenuToggle = document.getElementById('mobileMenuToggle');
            const mainNav = document.getElementById('mainNav');
            
            if(mobileMenuToggle && mainNav) {
                mobileMenuToggle.addEventListener('click', function(e) {
                    e.stopPropagation();
                    mainNav.classList.toggle('active');
                });
                
                // Close menu when clicking outside
                document.addEventListener('click', function(event) {
                    if(window.innerWidth <= 900) {
                        if(!mainNav.contains(event.target) && !mobileMenuToggle.contains(event.target)) {
                            mainNav.classList.remove('active');
                        }
                    }
                });
            }
            
            // Reset Filters
            document.getElementById('resetFilters').addEventListener('click', function() {
                window.location.href = 'history.php';
            });
            
            // Export History
            document.getElementById('exportHistory').addEventListener('click', function() {
                if(confirm('Export all history records?')) {
                    const form = document.getElementById('filterForm');
                    form.target = '_blank';
                    form.action = 'export_history.php';
                    form.submit();
                    form.target = '';
                    form.action = 'history.php';
                }
            });
            
            // View Details
            document.querySelectorAll('.view-details').forEach(button => {
                button.addEventListener('click', function() {
                    const itemId = this.getAttribute('data-id');
                    
                    // Load details
                    document.getElementById('modalContent').innerHTML = `
                        <div style="text-align: center; padding: 40px 0;">
                            <div class="loader" style="width: 40px; height: 40px; border: 3px solid #f3f3f3; border-top: 3px solid #4caf50; border-radius: 50%; animation: spin 1s linear infinite; margin: 0 auto;"></div>
                            <p>Loading details...</p>
                        </div>
                    `;
                    
                    // Show modal
                    document.getElementById('detailsModal').style.display = 'flex';
                    
                    // Simulate API call
                    setTimeout(() => {
                        document.getElementById('modalContent').innerHTML = `
                            <div style="display: flex; flex-direction: column; gap: 20px;">
                                <!-- Header -->
                                <div style="background: #f8fdf9; padding: 20px; border-radius: 12px; border-left: 4px solid #4caf50;">
                                    <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                                        <div>
                                            <div style="font-size: 1.2rem; font-weight: 600; color: #2c3e50; margin-bottom: 5px;">Collection #${String(itemId).padStart(6, '0')}</div>
                                            <div style="font-size: 0.9rem; color: #666;">Completed on ${new Date().toLocaleDateString()}</div>
                                        </div>
                                        <span class="status-badge status-completed">Completed</span>
                                    </div>
                                </div>
                                
                                <!-- Details Grid -->
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                                    <div>
                                        <div style="font-size: 0.85rem; color: #666; margin-bottom: 5px;">Customer</div>
                                        <div style="font-weight: 600; color: #333;">Juan Dela Cruz</div>
                                        <div style="font-size: 0.85rem; color: #999;">09123456789</div>
                                    </div>
                                    <div>
                                        <div style="font-size: 0.85rem; color: #666; margin-bottom: 5px;">Zone</div>
                                        <div style="font-weight: 600; color: #333;">Zone A - Lahug</div>
                                        <div style="font-size: 0.85rem; color: #999;">Barangay Lahug, Cebu City</div>
                                    </div>
                                    <div>
                                        <div style="font-size: 0.85rem; color: #666; margin-bottom: 5px;">Waste Type</div>
                                        <div>
                                            <span class="waste-badge waste-plastic">Plastic</span>
                                        </div>
                                    </div>
                                    <div>
                                        <div style="font-size: 0.85rem; color: #666; margin-bottom: 5px;">Weight</div>
                                        <div style="font-weight: 600; color: #2e7d32; font-size: 1.2rem;">45.5 kg</div>
                                    </div>
                                    <div>
                                        <div style="font-size: 0.85rem; color: #666; margin-bottom: 5px;">Amount</div>
                                        <div style="font-weight: 600; color: #ff9800; font-size: 1.2rem;">₱227.50</div>
                                    </div>
                                    <div>
                                        <div style="font-size: 0.85rem; color: #666; margin-bottom: 5px;">Payment Method</div>
                                        <div style="font-weight: 600; color: #333;">Cash</div>
                                    </div>
                                </div>
                                
                                <!-- Notes -->
                                <div>
                                    <div style="font-size: 0.9rem; color: #666; margin-bottom: 10px; font-weight: 500;">Notes</div>
                                    <div style="background: #f8fdf9; padding: 15px; border-radius: 8px; border: 1px solid #e8f5e9;">
                                        <p style="margin: 0; color: #555; font-size: 0.95rem;">Collection completed successfully. Customer had pre-sorted recyclables. No issues encountered.</p>
                                    </div>
                                </div>
                                
                                <!-- Driver Actions -->
                                <div>
                                    <div style="font-size: 0.9rem; color: #666; margin-bottom: 10px; font-weight: 500;">Driver Actions</div>
                                    <div style="background: #f0f7f3; padding: 12px; border-radius: 8px; font-size: 0.85rem; color: #666;">
                                        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 5px;">
                                            <i class="fas fa-user-check" style="color: #4caf50;"></i>
                                            <span>Driver verified waste type and weight</span>
                                        </div>
                                        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 5px;">
                                            <i class="fas fa-camera" style="color: #2196f3;"></i>
                                            <span>Photo of collected waste was taken</span>
                                        </div>
                                        <div style="display: flex; align-items: center; gap: 8px;">
                                            <i class="fas fa-receipt" style="color: #ff9800;"></i>
                                            <span>Digital receipt issued to customer</span>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Buttons -->
                                <div style="display: flex; gap: 10px; margin-top: 20px;">
                                    <button class="btn btn-primary" style="flex: 1;" onclick="printDetails(${itemId})">
                                        <i class="fas fa-print"></i> Print Receipt
                                    </button>
                                    <button class="btn btn-outline" style="flex: 1;" onclick="sendReceipt(${itemId})">
                                        <i class="fas fa-paper-plane"></i> Send to Customer
                                    </button>
                                </div>
                            </div>
                        `;
                    }, 500);
                });
            });
            
            // Print Receipt
            document.querySelectorAll('.print-receipt').forEach(button => {
                button.addEventListener('click', function() {
                    const itemId = this.getAttribute('data-id');
                    if(confirm('Print receipt for collection #' + itemId + '?')) {
                        window.open('receipt.php?id=' + itemId, '_blank');
                    }
                });
            });
            
            // Close Modal
            document.getElementById('closeModal').addEventListener('click', function() {
                document.getElementById('detailsModal').style.display = 'none';
            });
            
            // Close modal when clicking outside
            document.getElementById('detailsModal').addEventListener('click', function(e) {
                if(e.target === this) {
                    this.style.display = 'none';
                }
            });
            
            // Auto-submit form on filter change for better UX
            document.getElementById('status').addEventListener('change', function() {
                document.getElementById('filterForm').submit();
            });
            
            document.getElementById('date').addEventListener('change', function() {
                document.getElementById('filterForm').submit();
            });
            
            document.getElementById('waste').addEventListener('change', function() {
                document.getElementById('filterForm').submit();
            });
        });
        
        function printDetails(id) {
            alert('Printing receipt for collection #' + id);
        }
        
        function sendReceipt(id) {
            alert('Sending receipt to customer for collection #' + id);
        }
    </script>
</body>
</html>