#!/bin/bash

# Setup script for running admin panel on local network
cd "$(dirname "$0")"

echo "Setting up admin panel for local network access..."

# Check if .env exists, if not create from .env.example
if [ ! -f .env ]; then
    if [ -f .env.example ]; then
        cp .env.example .env
        echo "Created .env from .env.example"
    else
        echo "Warning: No .env.example found. You may need to create .env manually."
    fi
fi

# Update APP_URL in .env
if [ -f .env ]; then
    # Update APP_URL to local network IP
    if grep -q "APP_URL=" .env; then
        sed -i '' 's|APP_URL=.*|APP_URL=http://192.168.100.226:8000|' .env
        echo "Updated APP_URL to http://192.168.100.226:8000"
    else
        echo "APP_URL=http://192.168.100.226:8000" >> .env
        echo "Added APP_URL to .env"
    fi
fi

# Clear caches
echo "Clearing caches..."
php artisan config:clear 2>/dev/null || echo "Could not clear config cache"
php artisan cache:clear 2>/dev/null || echo "Could not clear application cache"
php artisan route:clear 2>/dev/null || echo "Could not clear route cache"
php artisan view:clear 2>/dev/null || echo "Could not clear view cache"

# Set permissions
echo "Setting permissions..."
chmod -R 775 storage bootstrap/cache 2>/dev/null || echo "Could not set permissions"

# Create storage link if it doesn't exist
if [ ! -L public/storage ]; then
    php artisan storage:link 2>/dev/null && echo "Created storage symlink" || echo "Could not create storage symlink"
fi

echo ""
echo "Setup complete!"
echo "To start the server, run:"
echo "  php artisan serve --host=0.0.0.0 --port=8000"
echo ""
echo "Then access the admin panel at: http://192.168.100.226:8000"









