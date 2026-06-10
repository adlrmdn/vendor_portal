# Vendor Portal

The Vendor Portal is a Laravel-based web application facilitating interaction between the company and its vendors for Purchase Order handling and shipping logistics.

## Prerequisites

- **Docker** & **Docker Compose** (Recommended)
- OR **PHP 8.2+**, **Composer**, **Node.js/NPM** (for local without Docker)

## Installation & Setup

### Using Docker (Recommended)

1. **Clone the repository**:
   ```bash
   git clone <repository-url>
   cd webservice/vendor_portal
   ```

2. **Environment Configuration**:
   ```bash
   cp .env.example .env
   # Edit .env and set database credentials if not using default SQLite
   ```

3. **Start the Application**:
   ```bash
   docker-compose up -d --build
   ```
   *Note: This builds the app container and starts it on port 8080.*

4. **Initialize Application**:
   You may need to run migrations inside the container:
   ```bash
   docker-compose exec app php artisan migrate --seed
   ```

### Local Development (Manual)

1. **Install PHP Dependencies**:
   ```bash
   composer install
   ```

2. **Install Frontend Dependencies**:
   ```bash
   npm install
   npm run build
   ```

3. **Database Setup**:
   ```bash
   touch database/database.sqlite
   php artisan migrate --seed
   ```

4. **Serve Application**:
   ```bash
   php artisan serve
   ```

## Operational Management

### Database Migrations
To update the database schema:
```bash
docker-compose exec app php artisan migrate
```

### Clearing Caches
If you make changes to configuration or views (Blade files) that aren't reflecting:
```bash
docker-compose exec app php artisan optimize:clear
docker-compose exec app php artisan view:clear
```

### Docker Management
- **Rebuild Container**: Required if you change `Dockerfile` or add system dependencies.
  ```bash
  docker-compose up -d --build
  ```
- **View Logs**:
  ```bash
  docker-compose logs -f app
  ```

## Troubleshooting

### Pagination Styling Issues
If pagination icons appear incorrect (huge SVGs), ensure the views are using the custom pagination template. The application is configured to force Bootstrap 5 styles via `resources/views/custom-pagination.blade.php`.
If issues persist, clear the view cache: `php artisan view:clear`.

### Permission Issues
Ensure the `storage` and `bootstrap/cache` directories are writable by the web server user. The Dockerfile handles this automatically.

## Support
For issues, contact the system administrator.
