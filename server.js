const express = require('express');
const app = express();
const port = 3000;

// Middleware to parse JSON
app.use(express.json());

// CORS middleware to allow requests from PHP frontend
app.use((req, res, next) => {
  res.header('Access-Control-Allow-Origin', '*');
  res.header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
  res.header('Access-Control-Allow-Headers', 'Origin, X-Requested-With, Content-Type, Accept, Authorization');
  next();
});

// Root endpoint
app.get('/', (req, res) => {
  res.json({
    message: 'TrashTrace Node.js API Server',
    status: 'Running',
    endpoints: {
      status: 'GET /api/status',
      collections: 'GET /api/collections/stats',
      schedule: 'GET /api/collections/schedule',
      drivers: 'GET /api/drivers/performance',
      notifications: 'GET /api/notifications/recent'
    },
    timestamp: new Date().toISOString()
  });
});

// Basic API endpoint
app.get('/api/status', (req, res) => {
  res.json({ status: 'Node.js API is running', timestamp: new Date().toISOString() });
});

// Trash collection statistics
app.get('/api/collections/stats', (req, res) => {
  res.json({
    totalCollections: 1250,
    completedToday: 45,
    pendingPickups: 12,
    efficiency: 87.5,
    lastUpdated: new Date().toISOString()
  });
});

// Collection schedule data
app.get('/api/collections/schedule', (req, res) => {
  res.json({
    message: 'Collection schedule from Node.js API',
    schedule: [
      { id: 1, barangay: 'Barangay A', zone: 'Zone 1', date: '2024-01-15', time: '08:00', status: 'Scheduled' },
      { id: 2, barangay: 'Barangay B', zone: 'Zone 2', date: '2024-01-15', time: '09:30', status: 'In Progress' },
      { id: 3, barangay: 'Barangay C', zone: 'Zone 1', date: '2024-01-16', time: '10:00', status: 'Completed' }
    ]
  });
});

// Driver performance data
app.get('/api/drivers/performance', (req, res) => {
  res.json({
    drivers: [
      { id: 1, name: 'John Doe', collections: 25, rating: 4.8, status: 'Active' },
      { id: 2, name: 'Jane Smith', collections: 22, rating: 4.9, status: 'Active' },
      { id: 3, name: 'Bob Johnson', collections: 18, rating: 4.6, status: 'On Break' }
    ]
  });
});

// Notifications endpoint
app.get('/api/notifications/recent', (req, res) => {
  res.json({
    notifications: [
      { id: 1, type: 'pickup_completed', title: 'Pickup Completed', message: 'Trash collection completed in Barangay A', timestamp: new Date().toISOString() },
      { id: 2, type: 'delay_alert', title: 'Delay Alert', message: 'Collection delayed in Barangay B due to traffic', timestamp: new Date().toISOString() }
    ]
  });
});

// Start server
app.listen(port, () => {
  console.log(`TrashTrace Node.js API server running at http://localhost:${port}`);
  console.log(`Available endpoints:`);
  console.log(`  GET /api/status`);
  console.log(`  GET /api/collections/stats`);
  console.log(`  GET /api/collections/schedule`);
  console.log(`  GET /api/drivers/performance`);
  console.log(`  GET /api/notifications/recent`);
});
