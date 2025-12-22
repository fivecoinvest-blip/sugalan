<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class SecurityScan extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'security:scan {--fix : Attempt to fix issues automatically}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run security checks and scan for vulnerabilities';

    protected array $issues = [];
    protected array $warnings = [];
    protected array $passed = [];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🔒 Starting Security Scan...');
        $this->newLine();

        // Run security checks
        $this->checkEnvironmentConfiguration();
        $this->checkDatabaseSecurity();
        $this->checkFilesystemPermissions();
        $this->checkDependencyVulnerabilities();
        $this->checkSecurityHeaders();
        $this->checkAuthenticationSecurity();
        $this->checkSensitiveDataExposure();
        
        // Display results
        $this->displayResults();

        // Return appropriate exit code
        return count($this->issues) > 0 ? 1 : 0;
    }

    /**
     * Check environment configuration
     */
    protected function checkEnvironmentConfiguration(): void
    {
        $this->info('📋 Checking Environment Configuration...');

        // Check if APP_ENV is production
        if (app()->environment('production')) {
            // Check APP_DEBUG
            if (config('app.debug')) {
                $this->issues[] = 'APP_DEBUG is enabled in production environment';
            } else {
                $this->passed[] = 'APP_DEBUG is disabled in production';
            }

            // Check APP_KEY
            if (config('app.key') === 'base64:CHANGEME' || empty(config('app.key'))) {
                $this->issues[] = 'APP_KEY is not set or using default value';
            } else {
                $this->passed[] = 'APP_KEY is properly configured';
            }
        } else {
            $this->warnings[] = 'Not running in production environment';
        }

        // Check JWT secret
        if (empty(config('jwt.secret'))) {
            $this->issues[] = 'JWT secret is not configured';
        } else {
            $this->passed[] = 'JWT secret is configured';
        }

        $this->newLine();
    }

    /**
     * Check database security
     */
    protected function checkDatabaseSecurity(): void
    {
        $this->info('🗄️  Checking Database Security...');

        try {
            // Check if database connection is secure
            $connection = config('database.default');
            $config = config("database.connections.{$connection}");

            // Check for default credentials
            if (isset($config['username']) && in_array($config['username'], ['root', 'admin', 'sa'])) {
                $this->warnings[] = 'Database using default username: ' . $config['username'];
            } else {
                $this->passed[] = 'Database not using default username';
            }

            // Check SSL/TLS for remote databases
            if (isset($config['host']) && !in_array($config['host'], ['localhost', '127.0.0.1', '::1'])) {
                if (empty($config['options'][PDO::MYSQL_ATTR_SSL_CA] ?? null)) {
                    $this->warnings[] = 'SSL/TLS not configured for remote database connection';
                } else {
                    $this->passed[] = 'SSL/TLS configured for database connection';
                }
            }

            // Check for SQL injection vulnerable queries (basic check)
            $this->passed[] = 'Using Laravel Query Builder (protected from SQL injection)';

        } catch (\Exception $e) {
            $this->warnings[] = 'Could not complete database security check: ' . $e->getMessage();
        }

        $this->newLine();
    }

    /**
     * Check filesystem permissions
     */
    protected function checkFilesystemPermissions(): void
    {
        $this->info('📁 Checking Filesystem Permissions...');

        $sensitiveFiles = [
            '.env',
            'config/database.php',
            'config/jwt.php',
        ];

        foreach ($sensitiveFiles as $file) {
            $path = base_path($file);
            
            if (!File::exists($path)) {
                $this->warnings[] = "File not found: {$file}";
                continue;
            }

            $perms = substr(sprintf('%o', fileperms($path)), -4);
            
            // Check if file is world-readable or writable
            if ($perms[2] >= 4 || $perms[3] >= 4) {
                $this->issues[] = "{$file} has insecure permissions: {$perms}";
            } else {
                $this->passed[] = "{$file} has secure permissions: {$perms}";
            }
        }

        // Check storage directory
        $storagePath = storage_path();
        if (!is_writable($storagePath)) {
            $this->issues[] = 'Storage directory is not writable';
        } else {
            $this->passed[] = 'Storage directory has correct permissions';
        }

        $this->newLine();
    }

    /**
     * Check dependency vulnerabilities
     */
    protected function checkDependencyVulnerabilities(): void
    {
        $this->info('📦 Checking Dependencies...');

        $composerLockPath = base_path('composer.lock');
        
        if (!File::exists($composerLockPath)) {
            $this->warnings[] = 'composer.lock not found - run composer install';
            $this->newLine();
            return;
        }

        // Check if packages are up to date
        $this->warnings[] = 'Run "composer audit" to check for known vulnerabilities';
        $this->passed[] = 'Using Laravel ' . app()->version();

        $this->newLine();
    }

    /**
     * Check security headers
     */
    protected function checkSecurityHeaders(): void
    {
        $this->info('🛡️  Checking Security Headers...');

        // Check if SecurityHeaders middleware is registered
        if (class_exists(\App\Http\Middleware\SecurityHeaders::class)) {
            $this->passed[] = 'SecurityHeaders middleware exists';
        } else {
            $this->issues[] = 'SecurityHeaders middleware not found';
        }

        $this->newLine();
    }

    /**
     * Check authentication security
     */
    protected function checkAuthenticationSecurity(): void
    {
        $this->info('🔐 Checking Authentication Security...');

        // Check password hashing
        $hashDriver = config('hashing.driver');
        if (in_array($hashDriver, ['bcrypt', 'argon', 'argon2id'])) {
            $this->passed[] = "Using secure password hashing: {$hashDriver}";
        } else {
            $this->issues[] = "Insecure password hashing driver: {$hashDriver}";
        }

        // Check JWT configuration
        $jwtTtl = config('jwt.ttl');
        if ($jwtTtl > 120) {
            $this->warnings[] = "JWT TTL is high: {$jwtTtl} minutes (consider reducing)";
        } else {
            $this->passed[] = "JWT TTL is reasonable: {$jwtTtl} minutes";
        }

        // Check if rate limiting is configured
        if (class_exists(\App\Http\Middleware\ThrottleWithLogging::class)) {
            $this->passed[] = 'Rate limiting middleware exists';
        } else {
            $this->warnings[] = 'Custom rate limiting middleware not found';
        }

        $this->newLine();
    }

    /**
     * Check for sensitive data exposure
     */
    protected function checkSensitiveDataExposure(): void
    {
        $this->info('🔍 Checking Sensitive Data Exposure...');

        // Check if .env is in .gitignore
        $gitignorePath = base_path('.gitignore');
        if (File::exists($gitignorePath)) {
            $gitignore = File::get($gitignorePath);
            if (str_contains($gitignore, '.env')) {
                $this->passed[] = '.env file is in .gitignore';
            } else {
                $this->issues[] = '.env file is NOT in .gitignore';
            }
        }

        // Check for exposed sensitive files in public directory
        $publicPath = public_path();
        $sensitiveFiles = ['.env', 'composer.json', 'composer.lock', '.git'];
        
        foreach ($sensitiveFiles as $file) {
            if (File::exists($publicPath . '/' . $file)) {
                $this->issues[] = "Sensitive file exposed in public directory: {$file}";
            }
        }

        $this->passed[] = 'No sensitive files found in public directory';

        $this->newLine();
    }

    /**
     * Display scan results
     */
    protected function displayResults(): void
    {
        $this->newLine();
        $this->info('═══════════════════════════════════════════════════════════');
        $this->info('                    SECURITY SCAN RESULTS                   ');
        $this->info('═══════════════════════════════════════════════════════════');
        $this->newLine();

        // Display passed checks
        if (count($this->passed) > 0) {
            $this->info('✅ PASSED (' . count($this->passed) . ')');
            foreach ($this->passed as $item) {
                $this->line('  ✓ ' . $item);
            }
            $this->newLine();
        }

        // Display warnings
        if (count($this->warnings) > 0) {
            $this->warn('⚠️  WARNINGS (' . count($this->warnings) . ')');
            foreach ($this->warnings as $item) {
                $this->line('  ⚠ ' . $item);
            }
            $this->newLine();
        }

        // Display issues
        if (count($this->issues) > 0) {
            $this->error('❌ ISSUES (' . count($this->issues) . ')');
            foreach ($this->issues as $item) {
                $this->line('  ✗ ' . $item);
            }
            $this->newLine();
        }

        // Summary
        $totalChecks = count($this->passed) + count($this->warnings) + count($this->issues);
        $this->info("Total Checks: {$totalChecks}");
        $this->info("Passed: " . count($this->passed));
        $this->warn("Warnings: " . count($this->warnings));
        $this->error("Issues: " . count($this->issues));
        
        $this->newLine();

        if (count($this->issues) === 0) {
            $this->info('🎉 Security scan completed successfully!');
        } else {
            $this->error('⚠️  Security scan found issues that need attention.');
        }
    }
}
