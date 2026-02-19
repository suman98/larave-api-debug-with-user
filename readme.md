# Laravel API Debug with User

A standalone web UI to test **internal Laravel API routes** as any authenticated user — without Postman, curl, or writing test code.

![PHP](https://img.shields.io/badge/PHP-%3E%3D7.4-blue)
![License](https://img.shields.io/badge/license-MIT-green)

## Features

- 🚀 Call any internal Laravel route (GET, POST, PUT, PATCH, DELETE)
- 🔐 Authenticate as any user by ID
- 📝 JSON or Key-Value payload editor
- 🎨 Dark / Light theme with persistence
- 📋 Multiple request cards with localStorage persistence
- 🖥️ Fullscreen response viewer with JSON syntax highlighting
- 📄 HTML error page rendering (iframe preview)

## Installation

### Option 1 — Clone & Run (standalone)

```bash
git clone https://github.com/suman98/laravel-api-debug-with-user.git
cd laravel-api-debug-with-user
composer install
cp .env.example .env
```

Edit `.env` and set the path to your Laravel project:

```
LARAVEL_PROJECT_PATH=/path/to/your/laravel/project
```

Start the server:

```bash
php bin/serve
# or with custom host/port:
php bin/serve 0.0.0.0 9000
```

Open **http://localhost:8089** in your browser.

### Option 2 — Install via Composer

```bash
composer require suman98/laravel-api-debug-with-user --dev
```

Create a `.env` in your project root (or wherever you run the command from):

```
LARAVEL_PROJECT_PATH=/path/to/your/laravel/project
```

Run the built-in server:

```bash
./vendor/bin/serve
```

## Project Structure

```
├── bin/
│   └── serve                  # CLI dev server script
├── public/
│   └── index.php              # Web UI entry point
├── src/
│   ├── Bootstrap.php          # Laravel bootstrapper (loads .env + app)
│   └── InternalApiCaller.php  # API caller class
├── .env.example
├── composer.json
└── readme.md
```

## How It Works

1. The tool bootstraps your **external Laravel application** using the path from `LARAVEL_PROJECT_PATH`.
2. It authenticates as any user via `Auth::setUser()`.
3. It dispatches an internal `Request` through Laravel's HTTP kernel.
4. The response (JSON or HTML) is rendered in the browser.

The User model is resolved automatically from your Laravel project's `config('auth.providers.users.model')` — no hardcoded model references.

## Requirements

- PHP >= 7.4
- A Laravel project (any version with HTTP Kernel)
- Composer

## License

MIT


