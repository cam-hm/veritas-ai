# Sync vendor directory from Docker container to local for IDE indexing

Write-Host "Syncing vendor directory from container to local..." -ForegroundColor Cyan

# Remove local vendor if it exists (to avoid conflicts)
if (Test-Path "vendor") {
    Write-Host "Removing existing local vendor directory..." -ForegroundColor Yellow
    Remove-Item -Recurse -Force vendor
}

# Copy vendor from container to local
Write-Host "Copying vendor from container..." -ForegroundColor Cyan
docker compose cp app:/var/www/html/vendor ./vendor

Write-Host "Vendor directory synced successfully!" -ForegroundColor Green
Write-Host "Your IDE should now be able to index the vendor folder." -ForegroundColor Green


