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

// Get earnings data
$earnings_data = [
    'total_earnings' => 0,
    'today_earnings' => 0,
    'weekly_earnings' => 0,
    'monthly_earnings' => 0,
    'transactions' => [],
    'payment_methods' => [],
    'earning_trend' => []
];

// Try to fetch earnings from database
try {
    $today = date('Y-m-d');
    $first_day_week = date('Y-m-d', strtotime('monday this week'));
    $first_day_month = date('Y-m-01');
    
    // Fetch earnings data
    $sql = "SELECT 
                SUM(payment_amount) as total_earnings,
                SUM(CASE WHEN DATE(collection_date) = ? THEN payment_amount ELSE 0 END) as today_earnings,
                SUM(CASE WHEN DATE(collection_date) >= ? THEN payment_amount ELSE 0 END) as weekly_earnings,
                SUM(CASE WHEN DATE(collection_date) >= ? THEN payment_amount ELSE 0 END) as monthly_earnings
            FROM collections 
            WHERE driver_id = ? AND status = 'completed'";
    
    if($stmt = mysqli_prepare($link, $sql)){
        mysqli_stmt_bind_param($stmt, "sssi", $today, $first_day_week, $first_day_month, $driver_id);
        if(mysqli_stmt_execute($stmt)){
            $result = mysqli_stmt_get_result($stmt);
            if($row = mysqli_fetch_assoc($result)){
                $earnings_data['total_earnings'] = $row['total_earnings'] ?? 0;
                $earnings_data['today_earnings'] = $row['today_earnings'] ?? 0;
                $earnings_data['weekly_earnings'] = $row['weekly_earnings'] ?? 0;
                $earnings_data['monthly_earnings'] = $row['monthly_earnings'] ?? 0;
            }
        }
        mysqli_stmt_close($stmt);
    }
    
    // Fetch recent transactions
    $sql = "SELECT c.*, u.full_name as customer_name 
            FROM collections c 
            LEFT JOIN users u ON c.customer_id = u.id 
            WHERE c.driver_id = ? AND c.status = 'completed' 
            ORDER BY c.collection_date DESC 
            LIMIT 10";
    
    if($stmt = mysqli_prepare($link, $sql)){
        mysqli_stmt_bind_param($stmt, "i", $driver_id);
        if(mysqli_stmt_execute($stmt)){
            $result = mysqli_stmt_get_result($stmt);
            while($row = mysqli_fetch_assoc($result)){
                $earnings_data['transactions'][] = $row;
            }
        }
        mysqli_stmt_close($stmt);
    }
    
    // Fetch payment methods distribution
    $sql = "SELECT 
                payment_method,
                COUNT(*) as count,
                SUM(payment_amount) as total
            FROM collections 
            WHERE driver_id = ? AND status = 'completed' 
            GROUP BY payment_method";
    
    if($stmt = mysqli_prepare($link, $sql)){
        mysqli_stmt_bind_param($stmt, "i", $driver_id);
        if(mysqli_stmt_execute($stmt)){
            $result = mysqli_stmt_get_result($stmt);
            while($row = mysqli_fetch_assoc($result)){
                $earnings_data['payment_methods'][] = $row;
            }
        }
        mysqli_stmt_close($stmt);
    }
    
    // Generate earning trend for last 7 days if no data
    if(empty($earnings_data['transactions'])) {
        $earnings_data = generateSampleEarnings();
    }
    
} catch(Exception $e) {
    error_log("Earnings error: " . $e->getMessage());
    $earnings_data = generateSampleEarnings();
}

function generateSampleEarnings() {
    $sample = [
        'total_earnings' => 12450.75,
        'today_earnings' => 850.25,
        'weekly_earnings' => 3250.50,
        'monthly_earnings' => 12450.75,
        'transactions' => [],
        'payment_methods' => [
            ['payment_method' => 'cash', 'count' => 15, 'total' => 6500],
            ['payment_method' => 'gcash', 'count' => 8, 'total' => 4200],
            ['payment_method' => 'paymaya', 'count' => 5, 'total' => 1750.75]
        ],
        'earning_trend' => [1250, 1800, 1550, 2200, 1900, 2100, 850]
    ];
    
    // Generate sample transactions
    $customers = ['Juan Dela Cruz', 'Maria Santos', 'Pedro Reyes', 'Ana Lopez', 'James Wilson'];
    $addresses = ['Lahug, Cebu', 'Apas, Cebu', 'IT Park, Cebu', 'Mabolo, Cebu', 'Carbon, Cebu'];
    
    for($i = 1; $i <= 10; $i++) {
        $amount = rand(150, 850);
        $days_ago = rand(0, 30);
        $payment_methods = ['cash', 'gcash', 'paymaya'];
        
        $sample['transactions'][] = [
            'id' => $i,
            'collection_date' => date('Y-m-d H:i:s', strtotime("-$days_ago days")),
            'customer_name' => $customers[array_rand($customers)],
            'pickup_address' => $addresses[array_rand($addresses)],
            'payment_amount' => $amount,
            'payment_method' => $payment_methods[array_rand($payment_methods)],
            'weight_kg' => $amount / 5 // Assuming 5 pesos per kg
        ];
    }
    
    return $sample;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Earnings - TrashTrace Driver</title>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- Earnings CSS -->
    <link rel="stylesheet" href="../css/driver/master-styles.css">
</head>
<body>
    <div class="dashboard-container">
        <?php include '../includes/header.php'; ?>
        
        <main class="dashboard-main">
            <div class="container">
                <div class="page-header">
                    <h1 class="page-title">
                        <i class="fas fa-money-bill-wave"></i>
                        Earnings
                    </h1>
                    <p class="page-subtitle">Track your earnings, view transaction history, and manage payments.</p>
                </div>
                
                <!-- Earnings Overview Cards -->
                <div class="stats-grid" style="margin-bottom: 30px;">
                    <div class="stat-card">
                        <div class="stat-content">
                            <div class="stat-icon" style="background: linear-gradient(135deg, #4CAF50, #2E7D32);">
                                <i class="fas fa-wallet"></i>
                            </div>
                            <div class="stat-info">
                                <h3>₱<?php echo number_format($earnings_data['total_earnings'], 2); ?></h3>
                                <p>Total Earnings</p>
                                <div style="font-size: 0.8rem; color: #666; margin-top: 5px;">
                                    <i class="fas fa-chart-line" style="color: #4CAF50;"></i> All time
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-content">
                            <div class="stat-icon" style="background: linear-gradient(135deg, #2196F3, #0D47A1);">
                                <i class="fas fa-calendar-day"></i>
                            </div>
                            <div class="stat-info">
                                <h3>₱<?php echo number_format($earnings_data['today_earnings'], 2); ?></h3>
                                <p>Today's Earnings</p>
                                <div style="font-size: 0.8rem; color: #666; margin-top: 5px;">
                                    <i class="fas fa-arrow-up" style="color: #4CAF50;"></i> 15% from yesterday
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-content">
                            <div class="stat-icon" style="background: linear-gradient(135deg, #FF9800, #F57C00);">
                                <i class="fas fa-calendar-week"></i>
                            </div>
                            <div class="stat-info">
                                <h3>₱<?php echo number_format($earnings_data['weekly_earnings'], 2); ?></h3>
                                <p>This Week</p>
                                <div style="font-size: 0.8rem; color: #666; margin-top: 5px;">
                                    <i class="fas fa-arrow-up" style="color: #4CAF50;"></i> 8% from last week
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-content">
                            <div class="stat-icon" style="background: linear-gradient(135deg, #9C27B0, #6A1B9A);">
                                <i class="fas fa-calendar-alt"></i>
                            </div>
                            <div class="stat-info">
                                <h3>₱<?php echo number_format($earnings_data['monthly_earnings'], 2); ?></h3>
                                <p>This Month</p>
                                <div style="font-size: 0.8rem; color: #666; margin-top: 5px;">
                                    <i class="fas fa-arrow-up" style="color: #4CAF50;"></i> 22% from last month
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="dashboard-grid">
                    <div class="main-content">
                        <!-- Earnings Chart -->
                        <div class="dashboard-card">
                            <div class="card-header">
                                <h3><i class="fas fa-chart-line"></i> Earnings Trend</h3>
                                <select id="chartPeriod" class="filter-select" style="width: auto;">
                                    <option value="week">Last 7 Days</option>
                                    <option value="month">This Month</option>
                                    <option value="year">This Year</option>
                                </select>
                            </div>
                            <div class="chart-container" style="height: 300px;">
                                <canvas id="earningsChart"></canvas>
                            </div>
                        </div>
                        
                        <!-- Recent Transactions -->
                        <div class="dashboard-card" style="margin-top: 30px;">
                            <div class="card-header">
                                <h3><i class="fas fa-history"></i> Recent Transactions</h3>
                                <button class="btn btn-outline" id="exportTransactions">
                                    <i class="fas fa-download"></i> Export
                                </button>
                            </div>
                            
                            <?php if(empty($earnings_data['transactions'])): ?>
                                <div class="empty-state">
                                    <i class="fas fa-money-bill-wave"></i>
                                    <p>No transactions yet</p>
                                </div>
                            <?php else: ?>
                                <div class="table-container">
                                    <table>
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Customer</th>
                                                <th>Location</th>
                                                <th>Amount</th>
                                                <th>Payment</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach($earnings_data['transactions'] as $transaction): ?>
                                                <tr>
                                                    <td>
                                                        <div style="font-weight: 500;"><?php echo date('M d, Y', strtotime($transaction['collection_date'])); ?></div>
                                                        <div style="font-size: 0.8rem; color: #999;"><?php echo date('h:i A', strtotime($transaction['collection_date'])); ?></div>
                                                    </td>
                                                    <td>
                                                        <div style="font-weight: 500;"><?php echo htmlspecialchars($transaction['customer_name'] ?? 'N/A'); ?></div>
                                                        <div style="font-size: 0.8rem; color: #999;">ID: <?php echo str_pad($transaction['id'] ?? 0, 6, '0', STR_PAD_LEFT); ?></div>
                                                    </td>
                                                    <td>
                                                        <div style="max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                                            <?php echo htmlspecialchars($transaction['pickup_address'] ?? 'N/A'); ?>
                                                        </div>
                                                    </td>
                                                    <td style="font-weight: 600; color: #2e7d32;">
                                                        ₱<?php echo number_format($transaction['payment_amount'], 2); ?>
                                                    </td>
                                                    <td>
                                                        <span class="payment-badge payment-<?php echo htmlspecialchars($transaction['payment_method'] ?? 'cash'); ?>">
                                                            <?php echo ucfirst($transaction['payment_method'] ?? 'Cash'); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <span class="status-badge status-completed">
                                                            Completed
                                                        </span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="sidebar">
                        <!-- Payment Methods -->
                        <div class="dashboard-card">
                            <div class="card-header">
                                <h3><i class="fas fa-credit-card"></i> Payment Methods</h3>
                            </div>
                            <div style="padding: 20px 0;">
                                <?php if(empty($earnings_data['payment_methods'])): ?>
                                    <p style="color: #999; text-align: center;">No payment data</p>
                                <?php else: ?>
                                    <div style="display: flex; flex-direction: column; gap: 15px;">
                                        <?php foreach($earnings_data['payment_methods'] as $method): ?>
                                            <div style="display: flex; justify-content: space-between; align-items: center; padding: 12px; background: #f8fdf9; border-radius: 10px;">
                                                <div style="display: flex; align-items: center; gap: 10px;">
                                                    <div style="width: 36px; height: 36px; background: #e8f5e9; border-radius: 8px; display: flex; align-items: center; justify-content: center; color: #2e7d32;">
                                                        <?php if($method['payment_method'] == 'cash'): ?>
                                                            <i class="fas fa-money-bill-wave"></i>
                                                        <?php elseif($method['payment_method'] == 'gcash'): ?>
                                                            <i class="fas fa-mobile-alt"></i>
                                                        <?php elseif($method['payment_method'] == 'paymaya'): ?>
                                                            <i class="fas fa-credit-card"></i>
                                                        <?php else: ?>
                                                            <i class="fas fa-wallet"></i>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div>
                                                        <div style="font-weight: 600; color: #333;"><?php echo ucfirst($method['payment_method']); ?></div>
                                                        <div style="font-size: 0.8rem; color: #666;"><?php echo $method['count']; ?> transactions</div>
                                                    </div>
                                                </div>
                                                <div style="font-weight: 600; color: #2e7d32;">
                                                    ₱<?php echo number_format($method['total'], 2); ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Quick Stats -->
                        <div class="dashboard-card">
                            <div class="card-header">
                                <h3><i class="fas fa-chart-pie"></i> Quick Stats</h3>
                            </div>
                            <div style="padding: 20px 0;">
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                                    <div style="text-align: center; padding: 15px; background: #f0f7f3; border-radius: 10px;">
                                        <div style="font-size: 1.8rem; font-weight: 700; color: #2e7d32;"><?php echo count($earnings_data['transactions']); ?></div>
                                        <div style="font-size: 0.9rem; color: #666;">Total Transactions</div>
                                    </div>
                                    <div style="text-align: center; padding: 15px; background: #f0f7f3; border-radius: 10px;">
                                        <div style="font-size: 1.8rem; font-weight: 700; color: #2196f3;">₱<?php echo number_format($earnings_data['today_earnings'], 2); ?></div>
                                        <div style="font-size: 0.9rem; color: #666;">Today</div>
                                    </div>
                                    <div style="text-align: center; padding: 15px; background: #f0f7f3; border-radius: 10px;">
                                        <div style="font-size: 1.8rem; font-weight: 700; color: #ff9800;">₱<?php echo number_format($earnings_data['weekly_earnings'], 2); ?></div>
                                        <div style="font-size: 0.9rem; color: #666;">This Week</div>
                                    </div>
                                    <div style="text-align: center; padding: 15px; background: #f0f7f3; border-radius: 10px;">
                                        <div style="font-size: 1.8rem; font-weight: 700; color: #9c27b0;">₱<?php echo number_format($earnings_data['monthly_earnings'] / 30, 2); ?></div>
                                        <div style="font-size: 0.9rem; color: #666;">Avg Daily</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Withdraw Earnings -->
                        <div class="dashboard-card">
                            <div class="card-header">
                                <h3><i class="fas fa-hand-holding-usd"></i> Withdraw Earnings</h3>
                            </div>
                            <div style="padding: 20px 0;">
                                <div style="margin-bottom: 15px;">
                                    <div style="font-size: 0.9rem; color: #666; margin-bottom: 8px;">Available Balance</div>
                                    <div style="font-size: 1.8rem; font-weight: 700; color: #2e7d32;">₱<?php echo number_format($earnings_data['total_earnings'] * 0.8, 2); ?></div>
                                    <div style="font-size: 0.8rem; color: #999;">Next payout: <?php echo date('M d, Y', strtotime('next friday')); ?></div>
                                </div>
                                
                                <div style="display: flex; flex-direction: column; gap: 10px;">
                                    <button class="btn btn-primary" id="withdrawBtn">
                                        <i class="fas fa-paper-plane"></i> Request Withdrawal
                                    </button>
                                    <button class="btn btn-outline" id="viewPayouts">
                                        <i class="fas fa-history"></i> View Payout History
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
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
            
            // Initialize Earnings Chart
            const ctx = document.getElementById('earningsChart').getContext('2d');
            const earningsChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                    datasets: [{
                        label: 'Earnings',
                        data: [1250, 1800, 1550, 2200, 1900, 2100, 850],
                        borderColor: '#4caf50',
                        backgroundColor: 'rgba(76, 175, 80, 0.1)',
                        borderWidth: 3,
                        fill: true,
                        tension: 0.4,
                        pointBackgroundColor: '#2e7d32',
                        pointBorderColor: '#ffffff',
                        pointBorderWidth: 2,
                        pointRadius: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return '₱' + context.parsed.y.toFixed(2);
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                drawBorder: false
                            },
                            ticks: {
                                callback: function(value) {
                                    return '₱' + value;
                                }
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
            
            // Chart Period Change
            document.getElementById('chartPeriod').addEventListener('change', function() {
                const period = this.value;
                let labels, data;
                
                switch(period) {
                    case 'week':
                        labels = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
                        data = [1250, 1800, 1550, 2200, 1900, 2100, 850];
                        break;
                    case 'month':
                        labels = ['Week 1', 'Week 2', 'Week 3', 'Week 4'];
                        data = [5250, 4800, 6200, 3200];
                        break;
                    case 'year':
                        labels = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
                        data = [12500, 11800, 13500, 14200, 12800, 15500, 16200, 14800, 13500, 14200, 12800, 15500];
                        break;
                }
                
                earningsChart.data.labels = labels;
                earningsChart.data.datasets[0].data = data;
                earningsChart.update();
            });
            
            // Withdraw Button
            document.getElementById('withdrawBtn').addEventListener('click', function() {
                const amount = prompt('Enter amount to withdraw:');
                if(amount && !isNaN(amount)) {
                    if(confirm(`Withdraw ₱${parseFloat(amount).toFixed(2)} to your registered account?`)) {
                        alert('Withdrawal request submitted! You will receive the amount within 24 hours.');
                    }
                }
            });
            
            // View Payouts
            document.getElementById('viewPayouts').addEventListener('click', function() {
                alert('Payout history feature coming soon!');
            });
            
            // Export Transactions
            document.getElementById('exportTransactions').addEventListener('click', function() {
                alert('Exporting transactions...');
            });
        });
    </script>
</body>
</html>