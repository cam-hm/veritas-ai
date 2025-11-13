# Docker Setup (Windows 11 + Docker Desktop)

This guide shows how to bring the VeritasAI Laravel stack up on Docker without installing PHP, Composer, Node, or PostgreSQL on the host.

## 1. Prerequisites
- Docker Desktop (with WSL2 backend enabled).
- (Optional) If you keep a local checkout of `camh/laravel-ollama`, place it as a sibling directory to this project (`../laravel-ollama`). Composer expects it there by default. Otherwise, update `composer.json` to point to a different repository.

## 2. First-time project setup
1. Copy the Docker-friendly environment file:
   ```powershell
   Copy-Item env.docker.example .env
   ```
   Adjust values (app URL, database credentials, Ollama models, etc.) as needed.
2. Ensure the storage directories are writeable:
   ```powershell
   New-Item -ItemType Directory -Force storage\app\documents | Out-Null
   ```
3. Build the PHP image and pull supporting services:
   ```powershell
   docker compose build
   ```
4. Install PHP dependencies inside the container:
   ```powershell
   docker compose run --rm app composer install
   ```
   If you see an error about `camh/laravel-ollama`, confirm the package path exists or edit the repository entry in `composer.json`.
5. Generate the application key:
   ```powershell
   docker compose run --rm app php artisan key:generate
   ```
6. Run database migrations:
   ```powershell
   docker compose run --rm app php artisan migrate
   ```

## 3. Starting the stack
Bring the core services (PHP-FPM, Nginx, PostgreSQL, Redis, Mailpit) online:
```powershell
docker compose up -d app nginx postgres redis mailpit
```

### Optional services
- Front-end/Vite dev server:
  ```powershell
  docker compose --profile frontend up -d node
  ```
- Queue worker & scheduler:
  ```powershell
  docker compose --profile workers up -d queue scheduler
  ```
- Ollama (GPU/CPU depending on your Docker Desktop resources):
  ```powershell
  docker compose --profile ai up -d ollama
  ```

## 4. Day-to-day commands
- Tail application logs:
  ```powershell
  docker compose logs -f app
  ```
- Run an Artisan command (e.g., run tests):
  ```powershell
  docker compose run --rm app php artisan test
  ```
- Install front-end packages/build assets:
  ```powershell
  docker compose run --rm node npm install
  docker compose run --rm node npm run build
  ```
  **Note:** After building assets, you can stop the Vite dev server (`node` service) if you're not actively developing front-end code. The built assets in `public/build` will be served by Nginx.
- Shut everything down:
  ```powershell
  docker compose down
  ```

## 5. Port summary
| Service  | URL/Port                     |
|----------|------------------------------|
| Laravel  | http://localhost:8080        |
| Vite dev | http://localhost:5173        |
| Mailpit  | http://localhost:8025        |
| Ollama   | http://localhost:11434       |

## 6. Troubleshooting
- **Composer cannot find `camh/laravel-ollama`:** clone the package next to this repo or replace the path repository with a Git/ZIP source you control.
- **Queue worker crashes on boot:** run `docker compose run --rm app composer install` again to ensure dependencies match the current codebase.
- **File change detection on Windows:** the Node container sets `CHOKIDAR_USEPOLLING=true` to improve reliability; expect slightly higher CPU usage.

With these pieces in place you can develop, run migrations, process documents, and chat through the Dockerized stack without installing the PHP toolchain locally.

