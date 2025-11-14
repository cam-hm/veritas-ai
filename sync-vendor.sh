#!/bin/bash
# Sync vendor directory from Docker container to local for IDE indexing

echo "Syncing vendor directory from container to local..."

# Remove local vendor if it exists (to avoid conflicts)
if [ -d "vendor" ]; then
    echo "Removing existing local vendor directory..."
    rm -rf vendor
fi

# Copy vendor from container to local
docker compose cp app:/var/www/html/vendor ./vendor

echo "Vendor directory synced successfully!"
echo "Your IDE should now be able to index the vendor folder."

