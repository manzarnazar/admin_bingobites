# Local Network Setup Guide

## Quick Setup Steps

### 1. Update .env file
Make sure your `.env` file has:
```env
APP_URL=http://192.168.100.226:8000
```

If you don't have a `.env` file, create one or copy from `.env.example`:
```bash
cp .env.example .env
```

Then update APP_URL:
```bash
sed -i '' 's|APP_URL=.*|APP_URL=http://192.168.100.226:8000|' .env
```

### 2. Clear caches
```bash
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear
```

### 3. Set permissions
```bash
chmod -R 775 storage bootstrap/cache
```

### 4. Create storage symlink (if needed)
```bash
php artisan storage:link
```

### 5. Start the server
```bash
php artisan serve --host=0.0.0.0 --port=8000
```

### 6. Access the admin panel
Open your browser and go to:
- **Local Network**: http://192.168.100.226:8000
- **Localhost**: http://127.0.0.1:8000

## Notes

- The TrustHosts middleware has been updated to allow `192.168.100.226` and `127.0.0.1`
- Port 8000 is used for the admin panel (main app uses port 9000)
- The server binds to `0.0.0.0` to allow access from other devices on your network

## Troubleshooting

If you get a 400 Bad Request error:
1. Make sure APP_URL in .env matches the URL you're accessing
2. Clear all caches: `php artisan config:clear && php artisan cache:clear`
3. Check that TrustHosts middleware includes your IP address

If you get a 500 error:
1. Check file permissions: `chmod -R 775 storage bootstrap/cache`
2. Check Laravel logs: `storage/logs/laravel.log`
3. Make sure `.env` file exists and has APP_KEY set









