# Black White Denim - Automated Blog Platform

A custom content operations platform integrating Claude AI with WordPress/WooCommerce for automated blog publishing.

## Features

- 🤖 **AI-Powered Content Generation** - Claude AI writes in your brand voice
- 📅 **Seasonal Content Calendar** - Auto-scheduling around key retail events
- 🛍️ **WooCommerce Integration** - Intelligent product suggestions
- 📝 **WordPress Publishing** - Direct API integration with Impreza theme
- 📊 **Visual Roadmap** - Plan and track content across the year
- 📧 **Monthly Reports** - Automated email summaries

## Tech Stack

- **Backend:** PHP 8.2+
- **Database:** MySQL 8.0
- **Frontend:** HTML5, CSS3, Vanilla JavaScript
- **AI:** Claude API (Anthropic)
- **Hosting:** Kinsta Application Hosting

## Deployment

This application is configured for automatic deployment via Kinsta:

1. Push to `main` branch
2. Kinsta automatically detects changes
3. Application deploys in ~60 seconds

## Environment Variables

Set these in Kinsta Dashboard → Applications → Your App → Environment Variables:

```
# Database (auto-provided by Kinsta)
DB_HOST=
DB_NAME=
DB_USER=
DB_PASSWORD=
DB_PORT=3306

# Application
APP_ENV=production
APP_URL=https://your-app.kinsta.app
APP_SECRET=generate-a-random-64-char-string

# WordPress API
WP_API_URL=https://blackwhitedenim.com/wp-json
WP_API_USER=your-username
WP_API_PASSWORD=your-application-password

# Claude API
CLAUDE_API_KEY=your-claude-api-key

# Email
SMTP_HOST=
SMTP_PORT=587
SMTP_USER=
SMTP_PASSWORD=
NOTIFICATION_EMAIL=your@email.com
```

## Local Development

### Prerequisites

- PHP 8.2+
- MySQL 8.0+
- Composer

### Setup

```bash
# Clone the repository
git clone https://github.com/banksdigital/automated-blogging-bwd.git
cd automated-blogging-bwd

# Install dependencies
composer install

# Copy environment file
cp .env.example .env

# Edit .env with your local settings
nano .env

# Run database migrations
php migrations/migrate.php

# Start local server
php -S localhost:8000 -t public
```

Visit `http://localhost:8000` in your browser.

## Project Structure

```
automated-blogging-bwd/
├── public/                 # Web root
│   ├── index.php          # Main entry point & router
│   ├── api/               # API endpoints
│   └── assets/            # CSS, JS, images
├── src/
│   ├── Controllers/       # Request handlers
│   ├── Models/            # Database models
│   ├── Services/          # Business logic
│   ├── Middleware/        # Auth, rate limiting
│   └── Helpers/           # Utilities
├── config/                # Configuration files
├── migrations/            # Database migrations
├── templates/             # HTML templates
└── storage/               # Logs, cache (gitignored)
```

## Initial Setup After Deployment

1. Access `/setup` to create your admin account
2. Configure WordPress API credentials in Settings
3. Run initial sync to pull categories, authors, products
4. Configure brand voice settings
5. Set up seasonal events calendar

## Security

- Session-based authentication with CSRF protection
- Bcrypt password hashing
- Parameterized SQL queries
- Input validation and output encoding
- Rate limiting on sensitive endpoints

## License

Proprietary - Black White Denim © 2025
