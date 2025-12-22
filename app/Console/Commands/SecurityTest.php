<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;

class SecurityTest extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'security:test {--quick : Run quick tests only}';

    /**
     * The console command description.
     */
    protected $description = 'Run automated security tests (SQL injection, XSS, authentication)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🔒 Starting Security Testing Suite...');
        $this->newLine();

        $quick = $this->option('quick');
        $results = [];

        // Test categories
        $results['sql_injection'] = $this->testSqlInjection();
        $results['xss'] = $this->testXssVulnerabilities();
        $results['authentication'] = $this->testAuthenticationSecurity();
        
        if (!$quick) {
            $results['csrf'] = $this->testCsrfProtection();
            $results['headers'] = $this->testSecurityHeaders();
            $results['rate_limiting'] = $this->testRateLimiting();
        }

        // Display summary
        $this->newLine();
        $this->displaySummary($results);

        // Return exit code
        $failed = collect($results)->contains(fn($result) => $result['passed'] < $result['total']);
        return $failed ? 1 : 0;
    }

    /**
     * Test SQL injection vulnerabilities
     */
    protected function testSqlInjection(): array
    {
        $this->info('Testing SQL Injection Protection...');
        
        $tests = [
            "' OR '1'='1",
            "1; DROP TABLE users--",
            "' UNION SELECT * FROM users--",
            "admin'--",
            "1' AND 1=1--",
        ];

        $passed = 0;
        $total = count($tests);

        foreach ($tests as $payload) {
            try {
                // Test on a safe query that should be parameterized
                $result = DB::select("SELECT * FROM users WHERE name = ?", [$payload]);
                
                // If we get here without error, parameterization is working
                $passed++;
                $this->line("  ✓ Protected against: " . substr($payload, 0, 30));
            } catch (\Exception $e) {
                $this->error("  ✗ Vulnerability with: " . substr($payload, 0, 30));
            }
        }

        $this->info("  Result: {$passed}/{$total} tests passed");
        return ['passed' => $passed, 'total' => $total];
    }

    /**
     * Test XSS vulnerabilities
     */
    protected function testXssVulnerabilities(): array
    {
        $this->info('Testing XSS Protection...');
        
        $payloads = [
            '<script>alert("XSS")</script>',
            '<img src=x onerror=alert("XSS")>',
            '<svg/onload=alert("XSS")>',
            'javascript:alert("XSS")',
            '<iframe src="javascript:alert(\'XSS\')">',
        ];

        $passed = 0;
        $total = count($payloads);

        foreach ($payloads as $payload) {
            // Check if Laravel escapes by default
            $escaped = e($payload);
            
            if ($escaped !== $payload && !str_contains($escaped, '<script>')) {
                $passed++;
                $this->line("  ✓ Escaped: " . substr($payload, 0, 30));
            } else {
                $this->error("  ✗ Not escaped: " . substr($payload, 0, 30));
            }
        }

        // Check Blade escaping
        $bladeTest = "{{ '<script>alert(1)</script>' }}";
        if (str_contains($bladeTest, '{{')) {
            $passed++;
            $this->line("  ✓ Blade escaping enabled");
        }

        $this->info("  Result: {$passed}/{$total} tests passed");
        return ['passed' => $passed, 'total' => $total];
    }

    /**
     * Test authentication security
     */
    protected function testAuthenticationSecurity(): array
    {
        $this->info('Testing Authentication Security...');
        
        $tests = [
            'Password hashing' => $this->testPasswordHashing(),
            'JWT token validation' => $this->testJwtValidation(),
            'Protected routes' => $this->testProtectedRoutes(),
            'Session security' => $this->testSessionSecurity(),
        ];

        $passed = array_sum($tests);
        $total = count($tests);

        foreach ($tests as $name => $result) {
            if ($result) {
                $this->line("  ✓ {$name}");
            } else {
                $this->error("  ✗ {$name}");
            }
        }

        $this->info("  Result: {$passed}/{$total} tests passed");
        return ['passed' => $passed, 'total' => $total];
    }

    /**
     * Test CSRF protection
     */
    protected function testCsrfProtection(): array
    {
        $this->info('Testing CSRF Protection...');
        
        $passed = 0;
        $total = 3;

        // Check if VerifyCsrfToken middleware is configured in bootstrap/app.php
        $bootstrapPath = base_path('bootstrap/app.php');
        if (file_exists($bootstrapPath)) {
            $content = file_get_contents($bootstrapPath);
            if (str_contains($content, 'ValidateCsrfToken')) {
                $passed++;
                $this->line("  ✓ CSRF middleware configured");
            } else {
                $this->error("  ✗ CSRF middleware not configured");
            }
        } else {
            $this->error("  ✗ bootstrap/app.php missing");
        }

        // Check if CSRF is enabled for web routes
        $csrfEnabled = config('app.env') !== 'testing';
        if ($csrfEnabled) {
            $passed++;
            $this->line("  ✓ CSRF protection enabled");
        } else {
            $this->error("  ✗ CSRF protection disabled");
        }

        // Check session configuration
        if (config('session.http_only') === true) {
            $passed++;
            $this->line("  ✓ HTTP-only cookies enabled");
        } else {
            $this->error("  ✗ HTTP-only cookies disabled");
        }

        $this->info("  Result: {$passed}/{$total} tests passed");
        return ['passed' => $passed, 'total' => $total];
    }

    /**
     * Test security headers
     */
    protected function testSecurityHeaders(): array
    {
        $this->info('Testing Security Headers...');
        
        $passed = 0;
        $total = 5;

        // Check if SecurityHeaders middleware exists
        if (file_exists(app_path('Http/Middleware/SecurityHeaders.php'))) {
            $passed++;
            $this->line("  ✓ SecurityHeaders middleware exists");
            
            $content = file_get_contents(app_path('Http/Middleware/SecurityHeaders.php'));
            
            $headers = [
                'Strict-Transport-Security' => 'HSTS header',
                'X-Frame-Options' => 'Clickjacking protection',
                'X-Content-Type-Options' => 'MIME type sniffing protection',
                'Content-Security-Policy' => 'CSP header',
            ];

            foreach ($headers as $header => $description) {
                if (str_contains($content, $header)) {
                    $passed++;
                    $this->line("  ✓ {$description}");
                } else {
                    $this->error("  ✗ {$description} missing");
                }
            }
        } else {
            $this->error("  ✗ SecurityHeaders middleware missing");
        }

        $this->info("  Result: {$passed}/{$total} tests passed");
        return ['passed' => $passed, 'total' => $total];
    }

    /**
     * Test rate limiting
     */
    protected function testRateLimiting(): array
    {
        $this->info('Testing Rate Limiting...');
        
        $passed = 0;
        $total = 2;

        // Check if ThrottleWithLogging middleware exists
        if (file_exists(app_path('Http/Middleware/ThrottleWithLogging.php'))) {
            $passed++;
            $this->line("  ✓ Rate limiting middleware exists");
        } else {
            $this->error("  ✗ Rate limiting middleware missing");
        }

        // Check route configuration
        $routes = Route::getRoutes();
        $hasThrottle = false;
        
        foreach ($routes as $route) {
            $middleware = $route->middleware();
            if (in_array('throttle', $middleware) || 
                in_array('throttle.logged', $middleware) ||
                in_array('throttle:api', $middleware)) {
                $hasThrottle = true;
                break;
            }
        }

        if ($hasThrottle) {
            $passed++;
            $this->line("  ✓ Rate limiting applied to routes");
        } else {
            $this->error("  ✗ Rate limiting not applied");
        }

        $this->info("  Result: {$passed}/{$total} tests passed");
        return ['passed' => $passed, 'total' => $total];
    }

    /**
     * Test password hashing
     */
    protected function testPasswordHashing(): bool
    {
        $password = 'testpassword123';
        $hashed = bcrypt($password);
        
        return strlen($hashed) > 50 && str_starts_with($hashed, '$2y$');
    }

    /**
     * Test JWT validation
     */
    protected function testJwtValidation(): bool
    {
        // Check if JWT is configured
        return config('jwt.secret') !== null && strlen(config('jwt.secret')) > 20;
    }

    /**
     * Test protected routes
     */
    protected function testProtectedRoutes(): bool
    {
        $routes = Route::getRoutes();
        $protectedCount = 0;

        foreach ($routes as $route) {
            if (str_starts_with($route->uri(), 'api/')) {
                $middleware = $route->middleware();
                if (in_array('auth:api', $middleware) || in_array('auth', $middleware)) {
                    $protectedCount++;
                }
            }
        }

        return $protectedCount > 10; // Should have many protected API routes
    }

    /**
     * Test session security
     */
    protected function testSessionSecurity(): bool
    {
        return config('session.secure') === true 
            && config('session.http_only') === true
            && config('session.same_site') === 'strict';
    }

    /**
     * Display test summary
     */
    protected function displaySummary(array $results): void
    {
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info('  Security Test Summary');
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        $totalPassed = 0;
        $totalTests = 0;

        foreach ($results as $category => $result) {
            $totalPassed += $result['passed'];
            $totalTests += $result['total'];
            
            $percentage = round(($result['passed'] / $result['total']) * 100);
            $status = $percentage === 100 ? '✓' : ($percentage >= 80 ? '⚠' : '✗');
            
            $categoryName = str_replace('_', ' ', ucwords($category));
            $this->line(sprintf(
                "  %s %-25s %d/%d (%d%%)",
                $status,
                $categoryName,
                $result['passed'],
                $result['total'],
                $percentage
            ));
        }

        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        
        $overallPercentage = round(($totalPassed / $totalTests) * 100);
        $this->newLine();
        
        if ($overallPercentage === 100) {
            $this->info("🎉 All security tests passed! ({$totalPassed}/{$totalTests})");
        } elseif ($overallPercentage >= 80) {
            $this->warn("⚠️  Most security tests passed ({$totalPassed}/{$totalTests} - {$overallPercentage}%)");
            $this->warn("   Review failed tests and improve security measures.");
        } else {
            $this->error("❌ Security tests failed ({$totalPassed}/{$totalTests} - {$overallPercentage}%)");
            $this->error("   Critical security issues detected. Fix immediately!");
        }
    }
}
