# TESDA Admin Dashboard

A comprehensive administration system for Technical Education and Skills Development Authority (TESDA) with real-time updates and modern UI.

## Features

- 📊 Real-time dashboard statistics
- 👥 Student enrollment management
- 📚 Course management
- 📝 Graduate tracking
- 💬 Communication system
- 🌙 Dark mode support
- 📱 Responsive design
- 📈 Interactive charts
- 🔔 Real-time notifications

## Tech Stack

- **Frontend**: PHP, HTML, TailwindCSS, Chart.js
- **Backend**: PHP, Node.js (WebSocket Server)
- **Database**: MySQL/MariaDB
- **Real-time**: Socket.IO
- **Other**: Express.js

## Prerequisites

- PHP 7.4 or higher
- Node.js 14.x or higher
- MySQL/MariaDB
- npm (Node Package Manager)
- Web server (Apache/Nginx)

## Installation

1. **Clone the repository**
```bash
git clone https://github.com/your-username/tesda-admin.git
cd tesda-admin
```

2. **Set up the PHP application**
- Configure your web server to point to the project directory
- Import the database schema from `setup_database.php`
- Update database credentials in `db.php`

3. **Install Node.js dependencies**
```bash
npm install
```

4. **Configure environment variables**
Create a `.env` file in the root directory:
```env
DB_HOST=localhost
DB_USER=your_username
DB_PASSWORD=your_password
DB_NAME=tesda_db
PORT=3000
```

5. **Start the WebSocket server**
```bash
# Development mode with auto-reload
npm run dev

# Production mode
npm start
```

## Project Structure

```
tesda-admin/
├── websocket-server.js    # Real-time server
├── home-page.php          # Main dashboard
├── manage-enrollment.php  # Enrollment management
├── communications.php     # Communication system
├── student_messages.php   # Student messaging
├── list-graduates.php     # Graduate listing
├── post-courses.php       # Course management
├── export.php            # Data export
├── db.php                # Database connection
└── setup_database.php    # Database setup
```

## Real-time Features

- Live enrollment statistics updates
- Instant message notifications
- Dynamic chart updates
- Real-time status changes
- Automatic counter animations

## Security

- Session-based authentication
- Prepared SQL statements
- CORS protection
- Input sanitization
- Error logging
- Secure WebSocket connections

## Browser Support

- Chrome (latest)
- Firefox (latest)
- Safari (latest)
- Edge (latest)

## Contributing

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit your changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to the branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request

## Troubleshooting

### WebSocket Connection Issues
- Verify the WebSocket server is running on port 3000
- Check browser console for connection errors
- Ensure proper CORS settings
- Verify firewall settings

### Database Issues
- Check database connection credentials
- Verify table permissions
- Ensure proper database schema
- Check error logs

### Real-time Updates Not Working
- Verify WebSocket server status
- Check client-side console for errors
- Verify database monitoring queries
- Check network connectivity

## License

This project is licensed under the MIT License - see the LICENSE file for details.

## Acknowledgments

- TESDA for the opportunity
- TailwindCSS for the UI framework
- Socket.IO for real-time capabilities
- Chart.js for data visualization 