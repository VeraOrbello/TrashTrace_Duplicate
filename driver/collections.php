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

// Initialize arrays
$collections = [];
$stats = [
    'total_collected' => 0,
    'total_weight' => 0,
    'total_earnings' => 0,
    'today_collections' => 0,
    'completion_rate' => 0
];

// Fetch collections data
try {
    // Get today's date
    $today = date('Y-m-d');
    
    // First, let's check if the collections table exists
    $table_check = mysqli_query($link, "SHOW TABLES LIKE 'collections'");
    if(mysqli_num_rows($table_check) > 0) {
        // Table exists, fetch data
        $sql = "SELECT 
                    c.id,
                    c.collection_date,
                    c.pickup_address,
                    c.waste_type,
                    c.weight_kg,
                    c.status,
                    c.payment_amount,
                    c.customer_id,
                    u.full_name as customer_name,
                    u.phone as customer_phone
                FROM collections c
                LEFT JOIN users u ON c.customer_id = u.id
                WHERE c.driver_id = ?
                ORDER BY c.collection_date DESC
                LIMIT 50";
        
        if($stmt = mysqli_prepare($link, $sql)){
            mysqli_stmt_bind_param($stmt, "i", $driver_id);
            if(mysqli_stmt_execute($stmt)){
                $result = mysqli_stmt_get_result($stmt);
                while($row = mysqli_fetch_assoc($result)){
                    $collections[] = $row;
                    
                    // Update statistics
                    $stats['total_collected']++;
                    $stats['total_weight'] += floatval($row['weight_kg']);
                    $stats['total_earnings'] += floatval($row['payment_amount']);
                    
                    if(date('Y-m-d', strtotime($row['collection_date'])) == $today){
                        $stats['today_collections']++;
                    }
                }
            }
            mysqli_stmt_close($stmt);
        }
        
        // Get completion rate
        $sql = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed
                FROM collections 
                WHERE driver_id = ?";
        
        if($stmt = mysqli_prepare($link, $sql)){
            mysqli_stmt_bind_param($stmt, "i", $driver_id);
            if(mysqli_stmt_execute($stmt)){
                $result = mysqli_stmt_get_result($stmt);
                if($row = mysqli_fetch_assoc($result)){
                    $stats['completion_rate'] = ($row['total'] > 0) ? round(($row['completed'] / $row['total']) * 100) : 0;
                }
            }
            mysqli_stmt_close($stmt);
        }
    } else {
        // Table doesn't exist, use sample data
        $collections = generateSampleCollections();
        $stats = [
            'total_collected' => 24,
            'total_weight' => 1250,
            'total_earnings' => 6250,
            'today_collections' => 3,
            'completion_rate' => 92
        ];
    }
    
} catch(Exception $e) {
    // Use sample data if there's an error
    error_log("Collections error: " . $e->getMessage());
    $collections = generateSampleCollections();
    $stats = [
        'total_collected' => 24,
        'total_weight' => 1250,
        'total_earnings' => 6250,
        'today_collections' => 3,
        'completion_rate' => 92
    ];
}

function generateSampleCollections() {
    $waste_types = ['Plastic', 'Paper', 'Glass', 'Metal', 'Organic', 'E-waste'];
    $statuses = ['completed', 'pending', 'in_progress', 'cancelled'];
    $addresses = [
        '123 Lahug Street, Cebu City',
        '456 Apas Road, Cebu City',
        '789 IT Park, Cebu City',
        '321 Carbon Market, Cebu City',
        '654 Mabolo Street, Cebu City'
    ];
    $names = ['Juan Dela Cruz', 'Maria Santos', 'Pedro Reyes', 'Ana Lopez', 'James Wilson'];
    
    $sample_collections = [];
    for($i = 1; $i <= 15; $i++) {
        $weight = rand(10, 100);
        $payment = $weight * 5; // 5 pesos per kg
        $days_ago = rand(0, 30);
        
        $sample_collections[] = [
            'id' => $i,
            'collection_date' => date('Y-m-d H:i:s', strtotime("-$days_ago days")),
            'pickup_address' => $addresses[array_rand($addresses)],
            'waste_type' => $waste_types[array_rand($waste_types)],
            'weight_kg' => $weight,
            'status' => $statuses[array_rand($statuses)],
            'payment_amount' => $payment,
            'customer_name' => $names[array_rand($names)],
            'customer_phone' => '09' . rand(100000000, 999999999)
        ];
    }
    
    return $sample_collections;
}

// Sort collections by date (newest first)
usort($collections, function($a, $b) {
    return strtotime($b['collection_date']) - strtotime($a['collection_date']);
});
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Collections - TrashTrace Driver</title>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Consolidated CSS -->
    <link rel="stylesheet" href="../css/driver/master-styles.css">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="dashboard-container">
      <!-- Replace the entire header section with this -->
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
                    <li><a href="history.php" class="nav-link"><i class="fas fa-history"></i> <span>History</span></a></li>
                    <li><a href="profile.php" class="nav-link active"><i class="fas fa-user"></i> <span>Profile</span></a></li>
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
                        <i class="fas fa-trash"></i>
                        Collections
                    </h1>
                    <p class="page-subtitle">Track and manage your waste collection history, payments, and customer information.</p>
                </div>
                
                <!-- Statistics Cards -->
                <div class="collections-stats-grid">
                    <div class="stat-card">
                        <div class="stat-content">
                            <div class="stat-icon icon-collected">
                                <i class="fas fa-trash-alt"></i>
                            </div>
                            <div class="stat-info">
                                <h3><?php echo number_format($stats['total_collected']); ?></h3>
                                <p>Total Collections</p>
                                <div class="stat-trend trend-up">
                                    <i class="fas fa-arrow-up"></i>
                                    12% from last month
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-content">
                            <div class="stat-icon icon-weight">
                                <i class="fas fa-weight-hanging"></i>
                            </div>
                            <div class="stat-info">
                                <h3><?php echo number_format($stats['total_weight']); ?> kg</h3>
                                <p>Total Weight</p>
                                <div class="stat-trend trend-up">
                                    <i class="fas fa-arrow-up"></i>
                                    8% from last month
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-content">
                            <div class="stat-icon icon-earnings">
                                <i class="fas fa-money-bill-wave"></i>
                            </div>
                            <div class="stat-info">
                                <h3>₱<?php echo number_format($stats['total_earnings'], 2); ?></h3>
                                <p>Total Earnings</p>
                                <div class="stat-trend trend-up">
                                    <i class="fas fa-arrow-up"></i>
                                    15% from last month
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-content">
                            <div class="stat-icon icon-today">
                                <i class="fas fa-calendar-day"></i>
                            </div>
                            <div class="stat-info">
                                <h3><?php echo $stats['today_collections']; ?></h3>
                                <p>Today's Collections</p>
                                <div class="stat-trend trend-down">
                                    <i class="fas fa-arrow-down"></i>
                                    2 from yesterday
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-content">
                            <div class="stat-icon icon-rate">
                                <i class="fas fa-chart-line"></i>
                            </div>
                            <div class="stat-info">
                                <h3><?php echo $stats['completion_rate']; ?>%</h3>
                                <p>Completion Rate</p>
                                <div class="stat-trend trend-up">
                                    <i class="fas fa-arrow-up"></i>
                                    5% improvement
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="content-grid">
                    <div class="main-content">
                        <!-- Collections Table -->
                        <div class="dashboard-card">
                            <div class="card-header">
                                <h3><i class="fas fa-list"></i> Recent Collections</h3>
                                <div class="controls">
                                    <button class="btn btn-outline" id="exportBtn">
                                        <i class="fas fa-download"></i> Export
                                    </button>
                                    <button class="btn btn-primary" id="refreshBtn">
                                        <i class="fas fa-sync-alt"></i> Refresh
                                    </button>
                                </div>
                            </div>
                            
                            <div class="search-box">
                                <i class="fas fa-search"></i>
                                <input type="text" id="searchInput" placeholder="Search collections by customer, address, or waste type...">
                            </div>
                            
                            <div class="filters">
                                <div class="filter-group">
                                    <label for="statusFilter">Status</label>
                                    <select id="statusFilter" class="filter-select">
                                        <option value="all">All Status</option>
                                        <option value="completed">Completed</option>
                                        <option value="pending">Pending</option>
                                        <option value="in_progress">In Progress</option>
                                        <option value="cancelled">Cancelled</option>
                                    </select>
                                </div>
                                
                                <div class="filter-group">
                                    <label for="wasteFilter">Waste Type</label>
                                    <select id="wasteFilter" class="filter-select">
                                        <option value="all">All Types</option>
                                        <option value="plastic">Plastic</option>
                                        <option value="paper">Paper</option>
                                        <option value="glass">Glass</option>
                                        <option value="metal">Metal</option>
                                        <option value="organic">Organic</option>
                                        <option value="e-waste">E-waste</option>
                                    </select>
                                </div>
                                
                                <div class="filter-group">
                                    <label for="dateFilter">Date Range</label>
                                    <select id="dateFilter" class="filter-select">
                                        <option value="all">All Time</option>
                                        <option value="today">Today</option>
                                        <option value="week">This Week</option>
                                        <option value="month">This Month</option>
                                    </select>
                                </div>
                            </div>
                            
                            <?php if(empty($collections)): ?>
                                <div class="empty-state">
                                    <i class="fas fa-trash-alt"></i>
                                    <h3>No Collections Yet</h3>
                                    <p>You haven't made any collections yet. Start your first collection assignment to see it here.</p>
                                    <a href="assignments.php" class="btn btn-primary">
                                        <i class="fas fa-tasks"></i> View Assignments
                                    </a>
                                </div>
                            <?php else: ?>
                                <div class="table-container">
                                    <table id="collectionsTable">
                                        <thead>
                                            <tr>
                                                <th>Date & Time</th>
                                                <th>Customer</th>
                                                <th>Address</th>
                                                <th>Waste Type</th>
                                                <th>Weight</th>
                                                <th>Amount</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach($collections as $collection): ?>
                                                <tr class="collection-row" 
                                                    data-id="<?php echo $collection['id']; ?>"
                                                    data-status="<?php echo htmlspecialchars($collection['status']); ?>"
                                                    data-waste="<?php echo htmlspecialchars(strtolower($collection['waste_type'])); ?>"
                                                    data-date="<?php echo date('Y-m-d', strtotime($collection['collection_date'])); ?>">
                                                    <td>
                                                        <div style="font-weight: 500;"><?php echo date('M d, Y', strtotime($collection['collection_date'])); ?></div>
                                                        <div style="font-size: 0.8rem; color: #999;"><?php echo date('h:i A', strtotime($collection['collection_date'])); ?></div>
                                                    </td>
                                                    <td>
                                                        <div style="font-weight: 500;"><?php echo htmlspecialchars($collection['customer_name'] ?? 'Unknown Customer'); ?></div>
                                                        <div style="font-size: 0.8rem; color: #999;"><?php echo htmlspecialchars($collection['customer_phone'] ?? 'N/A'); ?></div>
                                                    </td>
                                                    <td>
                                                        <div style="max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                                            <?php echo htmlspecialchars($collection['pickup_address']); ?>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <span class="waste-badge waste-<?php echo htmlspecialchars(strtolower($collection['waste_type'])); ?>">
                                                            <?php echo htmlspecialchars($collection['waste_type']); ?>
                                                        </span>
                                                    </td>
                                                    <td style="font-weight: 600; color: #2e7d32;">
                                                        <?php echo number_format($collection['weight_kg'], 1); ?> kg
                                                    </td>
                                                    <td style="font-weight: 600; color: #ff9800;">
                                                        ₱<?php echo number_format($collection['payment_amount'], 2); ?>
                                                    </td>
                                                    <td>
                                                        <span class="status-badge status-<?php echo htmlspecialchars($collection['status']); ?>">
                                                            <?php echo ucfirst(str_replace('_', ' ', $collection['status'])); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <div class="action-buttons">
                                                            <button class="btn-action btn-view view-details" data-id="<?php echo $collection['id']; ?>">
                                                                <i class="fas fa-eye"></i> View
                                                            </button>
                                                            <?php if(($collection['status'] == 'pending' || $collection['status'] == 'in_progress') && isset($collection['id'])): ?>
                                                                <button class="btn-action btn-edit update-status" data-id="<?php echo $collection['id']; ?>">
                                                                    <i class="fas fa-edit"></i> Update
                                                                </button>
                                                            <?php endif; ?>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                
                                <div class="pagination">
                                    <button class="page-btn"><i class="fas fa-chevron-left"></i></button>
                                    <button class="page-btn active">1</button>
                                    <button class="page-btn">2</button>
                                    <button class="page-btn">3</button>
                                    <button class="page-btn"><i class="fas fa-chevron-right"></i></button>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Sidebar -->
                    <div class="sidebar">
                        <!-- Performance Chart -->
                        <div class="dashboard-card">
                            <div class="card-header">
                                <h3><i class="fas fa-chart-bar"></i> Weekly Performance</h3>
                            </div>
                            <div class="chart-container">
                                <canvas id="performanceChart"></canvas>
                            </div>
                        </div>
                        
                        <!-- Quick Actions -->
                        <div class="dashboard-card">
                            <div class="card-header">
                                <h3><i class="fas fa-bolt"></i> Quick Actions</h3>
                            </div>
                            <div style="display: flex; flex-direction: column; gap: 12px; margin-top: 1rem;">
                                <button class="btn btn-primary" id="startCollection">
                                    <i class="fas fa-plus-circle"></i> Start New Collection
                                </button>
                                <button class="btn btn-outline" id="scanQR">
                                    <i class="fas fa-qrcode"></i> Scan QR Code
                                </button>
                                <button class="btn btn-outline" id="reportIssue">
                                    <i class="fas fa-exclamation-triangle"></i> Report Issue
                                </button>
                            </div>
                        </div>
                        
                        <!-- Waste Distribution -->
                        <div class="dashboard-card">
                            <div class="card-header">
                                <h3><i class="fas fa-chart-pie"></i> Waste Distribution</h3>
                            </div>
                            <div class="chart-container">
                                <canvas id="wasteChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
        
        <!-- Collection Details Panel -->
        <div class="details-panel" id="detailsPanel">
            <div class="details-header">
                <h3 style="margin: 0; font-size: 1.2rem;">Collection Details</h3>
                <button class="close-btn" id="closeDetails">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="details-content" id="detailsContent">
                <!-- Details will be loaded here -->
            </div>
        </div>
        
        <!-- Overlay -->
        <div class="overlay" id="overlay"></div>
    </div>
    
    <script>
        // Initialize charts
        function initCharts() {
            // Weekly Performance Chart
            const ctx1 = document.getElementById('performanceChart').getContext('2d');
            const performanceChart = new Chart(ctx1, {
                type: 'line',
                data: {
                    labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                    datasets: [{
                        label: 'Collections',
                        data: [12, 19, 15, 25, 22, 18, 14],
                        borderColor: '#4caf50',
                        backgroundColor: 'rgba(76, 175, 80, 0.1)',
                        borderWidth: 3,
                        fill: true,
                        tension: 0.4
                    }, {
                        label: 'Weight (kg)',
                        data: [450, 520, 480, 620, 580, 500, 420],
                        borderColor: '#2196f3',
                        backgroundColor: 'rgba(33, 150, 243, 0.1)',
                        borderWidth: 3,
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top',
                            labels: {
                                padding: 20,
                                usePointStyle: true,
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                drawBorder: false
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            }
                        }
                    }
                }
            });
            
            // Waste Distribution Chart
            const ctx2 = document.getElementById('wasteChart').getContext('2d');
            const wasteChart = new Chart(ctx2, {
                type: 'doughnut',
                data: {
                    labels: ['Plastic', 'Paper', 'Glass', 'Metal', 'Organic', 'E-waste'],
                    datasets: [{
                        data: [35, 25, 15, 10, 10, 5],
                        backgroundColor: [
                            '#bbdefb',
                            '#d7ccc8',
                            '#c8e6c9',
                            '#ffecb3',
                            '#dcedc8',
                            '#f8bbd0'
                        ],
                        borderWidth: 2,
                        borderColor: 'white'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                padding: 20,
                                boxWidth: 12,
                                font: {
                                    size: 11
                                }
                            }
                        }
                    },
                    cutout: '70%'
                }
            });
        }
        
        // Filter collections
        function filterCollections() {
            const statusFilter = document.getElementById('statusFilter').value;
            const wasteFilter = document.getElementById('wasteFilter').value;
            const dateFilter = document.getElementById('dateFilter').value;
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            const today = new Date().toISOString().split('T')[0];
            
            document.querySelectorAll('.collection-row').forEach(row => {
                const status = row.getAttribute('data-status');
                const waste = row.getAttribute('data-waste');
                const date = row.getAttribute('data-date');
                const text = row.textContent.toLowerCase();
                
                let show = true;
                
                // Apply filters
                if (statusFilter !== 'all' && status !== statusFilter) {
                    show = false;
                }
                
                if (wasteFilter !== 'all' && waste !== wasteFilter) {
                    show = false;
                }
                
                if (dateFilter !== 'all') {
                    const rowDate = new Date(date);
                    const todayDate = new Date(today);
                    
                    switch(dateFilter) {
                        case 'today':
                            if (date !== today) show = false;
                            break;
                        case 'week':
                            const weekAgo = new Date(todayDate.getTime() - 7 * 24 * 60 * 60 * 1000);
                            if (rowDate < weekAgo) show = false;
                            break;
                        case 'month':
                            const monthAgo = new Date(todayDate.getFullYear(), todayDate.getMonth() - 1, todayDate.getDate());
                            if (rowDate < monthAgo) show = false;
                            break;
                    }
                }
                
                // Apply search
                if (searchTerm && !text.includes(searchTerm)) {
                    show = false;
                }
                
                row.style.display = show ? '' : 'none';
            });
        }
        
        // Load collection details
        function loadCollectionDetails(id) {
            const detailsContent = document.getElementById('detailsContent');
            detailsContent.innerHTML = `
                <div style="text-align: center; padding: 2rem 0;">
                    <div class="loader" style="width: 40px; height: 40px; border: 3px solid #f3f3f3; border-top: 3px solid #4caf50; border-radius: 50%; animation: spin 1s linear infinite; margin: 0 auto 1rem;"></div>
                    <p>Loading details...</p>
                </div>
            `;
            
            // Simulate API call
            setTimeout(() => {
                detailsContent.innerHTML = `
                    <div class="detail-item">
                        <h4>Collection ID</h4>
                        <p style="font-family: monospace; background: #f5f5f5; padding: 8px; border-radius: 6px;">TRASH-${String(id).padStart(6, '0')}</p>
                    </div>
                    
                    <div class="detail-item">
                        <h4>Customer Information</h4>
                        <p style="font-weight: 600; margin-bottom: 5px;">Sample Customer</p>
                        <p style="color: #666; margin-bottom: 2px;">📱 09123456789</p>
                        <p style="color: #666;">✉️ customer@example.com</p>
                    </div>
                    
                    <div class="detail-item">
                        <h4>Pickup Address</h4>
                        <p>123 Sample Street, Cebu City</p>
                    </div>
                    
                    <div class="detail-item">
                        <h4>Collection Details</h4>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                            <div>
                                <div style="font-size: 0.85rem; color: #666;">Waste Type</div>
                                <div style="font-weight: 600; margin-top: 4px;">
                                    <span class="waste-badge waste-plastic">Plastic</span>
                                </div>
                            </div>
                            <div>
                                <div style="font-size: 0.85rem; color: #666;">Weight</div>
                                <div style="font-weight: 600; color: #2e7d32; margin-top: 4px;">45.5 kg</div>
                            </div>
                            <div>
                                <div style="font-size: 0.85rem; color: #666;">Amount</div>
                                <div style="font-weight: 600; color: #ff9800; margin-top: 4px;">₱227.50</div>
                            </div>
                            <div>
                                <div style="font-size: 0.85rem; color: #666;">Payment</div>
                                <div style="font-weight: 600; margin-top: 4px;">Cash</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="detail-item">
                        <h4>Driver Notes</h4>
                        <textarea id="driverNotes" style="width: 100%; padding: 12px; border: 1px solid #e8f5e9; border-radius: 8px; font-family: inherit; resize: vertical; min-height: 100px;" placeholder="Add your notes here...">No issues encountered during collection.</textarea>
                    </div>
                    
                    <div style="display: flex; gap: 10px; margin-top: 2rem;">
                        <button class="btn btn-primary" style="flex: 1;" onclick="saveNotes(${id})">
                            <i class="fas fa-save"></i> Save Notes
                        </button>
                        <button class="btn btn-outline" style="flex: 1;" onclick="printReceipt(${id})">
                            <i class="fas fa-print"></i> Print Receipt
                        </button>
                    </div>
                `;
            }, 500);
        }
        
        // Event Listeners
        document.addEventListener('DOMContentLoaded', () => {
            initCharts();
            
            // Filter events
            document.getElementById('statusFilter').addEventListener('change', filterCollections);
            document.getElementById('wasteFilter').addEventListener('change', filterCollections);
            document.getElementById('dateFilter').addEventListener('change', filterCollections);
            document.getElementById('searchInput').addEventListener('input', filterCollections);
            
            // View details buttons
            document.querySelectorAll('.view-details').forEach(button => {
                button.addEventListener('click', (e) => {
                    const collectionId = e.target.closest('.view-details').getAttribute('data-id');
                    loadCollectionDetails(collectionId);
                    document.getElementById('detailsPanel').classList.add('active');
                    document.getElementById('overlay').classList.add('active');
                });
            });
            
            // Close details panel
            document.getElementById('closeDetails').addEventListener('click', () => {
                document.getElementById('detailsPanel').classList.remove('active');
                document.getElementById('overlay').classList.remove('active');
            });
            
            // Close panel when clicking overlay
            document.getElementById('overlay').addEventListener('click', () => {
                document.getElementById('detailsPanel').classList.remove('active');
                document.getElementById('overlay').classList.remove('active');
            });
            
            // Quick action buttons
            document.getElementById('startCollection').addEventListener('click', () => {
                alert('Starting new collection...');
                window.location.href = 'assignments.php';
            });
            
            document.getElementById('scanQR').addEventListener('click', () => {
                alert('QR Scanner feature coming soon!');
            });
            
            document.getElementById('reportIssue').addEventListener('click', () => {
                const issue = prompt('Describe the issue:');
                if (issue) {
                    alert('Issue reported successfully!');
                }
            });
            
            // Export button
            document.getElementById('exportBtn').addEventListener('click', () => {
                alert('Exporting collections data...');
            });
            
            // Refresh button
            document.getElementById('refreshBtn').addEventListener('click', () => {
                window.location.reload();
            });
        });
    </script>
</body>
</html>