<?php

namespace Suman98\LaravelApiDebug;

use Dotenv\Dotenv;

class Bootstrap
{
    private static bool $booted = false;

    /**
     * Bootstrap the Laravel application from the path defined in .env
     *
     * @param string|null $envPath  Directory containing the .env file (auto-detected if null)
     * @return void
     */
    public static function init(?string $envPath = null): void
    {
        if (self::$booted) {
            return;
        }

        $envPath = $envPath ?: self::findEnvPath();

        $dotenv = Dotenv::createImmutable($envPath);
        $dotenv->load();
        $dotenv->required('LARAVEL_PROJECT_PATH');

        $laravelPath = $_ENV['LARAVEL_PROJECT_PATH'];

        if (!is_dir($laravelPath)) {
            throw new \RuntimeException("Laravel project path does not exist: {$laravelPath}");
        }

        require_once $laravelPath . '/vendor/autoload.php';

        $app = require_once $laravelPath . '/bootstrap/app.php';

        $kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
        $kernel->bootstrap();

        self::$booted = true;
    }

    /**
     * Search for .env file in common locations.
     *
     * @return string  Path to the directory containing .env
     * @throws \RuntimeException
     */
    private static function findEnvPath(): string
    {
        $packageRoot = dirname(__DIR__);

        $candidates = [
            $packageRoot,                       // Package root (standalone / cloned repo)
            getcwd(),                           // Current working directory
            realpath($packageRoot . '/../../..') ?: '', // Project root when installed as vendor package
        ];

        foreach ($candidates as $path) {
            if ($path && is_dir($path) && file_exists($path . '/.env')) {
                return $path;
            }
        }

        throw new \RuntimeException(
            "No .env file found. Create one with:\n  LARAVEL_PROJECT_PATH=/path/to/your/laravel/project"
        );
    }
}
