require('dotenv').config();
const express = require('express');
const app = express();
const http = require('http').createServer(app);
const io = require('socket.io')(http, {
  cors: {
    origin: process.env.CORS_ORIGIN || "*",
    methods: ["GET", "POST"]
  }
});
const mysql = require('mysql2/promise');
const winston = require('winston');

// Configure logger
const logger = winston.createLogger({
  level: 'info',
  format: winston.format.combine(
    winston.format.timestamp(),
    winston.format.json()
  ),
  transports: [
    new winston.transports.File({ filename: 'logs/error.log', level: 'error' }),
    new winston.transports.File({ filename: 'logs/combined.log' })
  ]
});

if (process.env.NODE_ENV !== 'production') {
  logger.add(new winston.transports.Console({
    format: winston.format.simple()
  }));
}

// Database configuration
const dbConfig = {
  host: process.env.DB_HOST || '127.0.0.1',
  user: process.env.DB_USER || 'root',
  password: process.env.DB_PASSWORD || '',
  database: process.env.DB_NAME || 'tesda_admin',
  waitForConnections: true,
  connectionLimit: 10,
  queueLimit: 0
};

// Create MySQL connection pool
const pool = mysql.createPool(dbConfig);

// Track connected clients
const connectedClients = new Map();

// Socket.IO connection handling
io.on('connection', (socket) => {
  logger.info(`Client connected: ${socket.id}`);
  connectedClients.set(socket.id, { 
    connectedAt: new Date(),
    lastActivity: new Date()
  });

  // Handle client authentication
  socket.on('authenticate', async (data) => {
    try {
      const connection = await pool.getConnection();
      const [user] = await connection.query('SELECT id, role FROM users WHERE id = ?', [data.userId]);
      connection.release();

      if (user.length > 0) {
        socket.userId = data.userId;
        socket.userRole = user[0].role;
        socket.join(`role_${user[0].role}`);
        socket.emit('authenticated', { status: 'success' });
        logger.info(`User ${data.userId} authenticated successfully`);
      } else {
        socket.emit('authenticated', { status: 'error', message: 'Authentication failed' });
        logger.warn(`Authentication failed for user ${data.userId}`);
      }
    } catch (error) {
      logger.error('Authentication error:', error);
      socket.emit('authenticated', { status: 'error', message: 'Internal server error' });
    }
  });

  socket.on('disconnect', () => {
    logger.info(`Client disconnected: ${socket.id}`);
    connectedClients.delete(socket.id);
  });

  // Handle errors
  socket.on('error', (error) => {
    logger.error(`Socket error for ${socket.id}:`, error);
  });
});

// Database monitoring with error handling and retry mechanism
async function monitorDatabaseChanges() {
  const maxRetries = 3;
  let retryCount = 0;

  while (retryCount < maxRetries) {
    try {
      const connection = await pool.getConnection();
      logger.info('Database monitoring started');
      
      // Monitor enrollees table
      setInterval(async () => {
        try {
          const [enrollments] = await connection.query(`
            SELECT 
              COUNT(*) as total_students,
              SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count,
              DATE_FORMAT(created_at, '%Y-%m') as month,
              COUNT(*) as count
            FROM enrollees
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
            GROUP BY DATE_FORMAT(created_at, '%Y-%m')
            ORDER BY month DESC
          `);

          const [courseDistribution] = await connection.query(`
            SELECT course, COUNT(*) as count
            FROM enrollees
            WHERE status = 'approved'
            GROUP BY course
          `);

          io.emit('enrollment_update', {
            total_students: enrollments[0]?.total_students || 0,
            pending_count: enrollments[0]?.pending_count || 0,
            enrollment_trend: enrollments.map(row => ({
              month: row.month,
              count: row.count
            })),
            course_distribution: courseDistribution.map(row => ({
              course: row.course,
              count: row.count
            }))
          });
        } catch (error) {
          logger.error('Enrollment monitoring error:', error);
        }
      }, 5000);

      // Monitor messages table
      setInterval(async () => {
        try {
          const [messages] = await connection.query(`
            SELECT receiver_id, COUNT(*) as unread_count
            FROM messages
            WHERE is_read = 0 AND receiver_type = 'admin'
            GROUP BY receiver_id
          `);

          messages.forEach(row => {
            io.to(`user_${row.receiver_id}`).emit('message_received', {
              receiver_id: row.receiver_id,
              count: row.unread_count
            });
          });
        } catch (error) {
          logger.error('Message monitoring error:', error);
        }
      }, 3000);

      break; // Exit the retry loop if successful
    } catch (error) {
      retryCount++;
      logger.error(`Database monitoring error (attempt ${retryCount}/${maxRetries}):`, error);
      
      if (retryCount === maxRetries) {
        logger.error('Max retries reached. Database monitoring failed to start.');
        process.exit(1);
      }
      
      // Wait before retrying
      await new Promise(resolve => setTimeout(resolve, 5000));
    }
  }
}

// Health check endpoint
app.get('/health', (req, res) => {
  res.json({
    status: 'healthy',
    connections: connectedClients.size,
    uptime: process.uptime()
  });
});

// Start the server
const PORT = process.env.PORT || 3000;
http.listen(PORT, () => {
  logger.info(`WebSocket server running on port ${PORT}`);
  monitorDatabaseChanges().catch(error => {
    logger.error('Failed to start database monitoring:', error);
    process.exit(1);
  });
}); 