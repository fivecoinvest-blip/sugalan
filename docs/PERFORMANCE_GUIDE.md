# Performance Monitoring & Optimization Guide

This guide covers performance monitoring, optimization strategies, and troubleshooting for the slot integration system.

---

## Table of Contents

1. [Performance Testing](#performance-testing)
2. [Load Testing](#load-testing)
3. [Real-Time Monitoring](#real-time-monitoring)
4. [Database Optimization](#database-optimization)
5. [Cache Strategy](#cache-strategy)
6. [Scaling Guide](#scaling-guide)
7. [Troubleshooting](#troubleshooting)

---

## Performance Testing

### Running Automated Tests

```bash
# Run all performance tests
php artisan test tests/Performance/SlotPerformanceTest.php

# Run specific test
php artisan test --filter=test_api_response_time_for_provider_list

# Run with verbose output
php artisan test tests/Performance/SlotPerformanceTest.php --testdox
```

### Test Coverage

The performance test suite includes:

1. **API Response Times** - Measure endpoint latency
2. **Game List Performance** - Test with 51+ games
3. **Concurrent Launches** - Simulate multiple users
4. **Database Queries** - Test with 100+ sessions
5. **Cache Effectiveness** - Measure cache improvements
6. **Search Performance** - Test with 500+ games
7. **Wallet Transactions** - Test atomic operations
8. **Session Cleanup** - Bulk operation testing
9. **Memory Usage** - Test with 1000+ games

### Performance Targets

| Metric | Target | Current | Status |
|--------|--------|---------|--------|
| API Response | <100ms | 1.5ms | ✅ |
| Game Launch | <500ms | 4.2ms | ✅ |
| Search | <300ms | 6.9ms | ✅ |
| Transaction | <100ms | 0.76ms | ✅ |
| Memory/1K Games | <50MB | 4MB | ✅ |

---

## Load Testing

### Quick Start

```bash
# Light load (10 users)
./load-test.sh light 10

# Moderate load (50 users)
./load-test.sh moderate 50

# Heavy load (100 users)
./load-test.sh heavy 100

# Stress test (500 users)
./load-test.sh stress 500
```

### Custom Load Test

```bash
# Custom concurrent users
API_BASE_URL=http://localhost:8000/api ./load-test.sh moderate 75
```

### Load Test Requirements

Install Apache Bench:
```bash
# Ubuntu/Debian
sudo apt install apache2-utils

# macOS
brew install ab

# Verify installation
ab -V
```

### Interpreting Results

**Good Performance Indicators**:
- Average response time <100ms
- 0 failed requests
- Requests/sec >100
- Success rate 100%

**Warning Signs**:
- Response time >200ms
- Failed requests >1%
- Success rate <95%
- Timeouts

**Critical Issues**:
- Response time >500ms
- Failed requests >5%
- Success rate <90%
- Database errors

---

## Real-Time Monitoring

### Laravel Telescope (Development)

Install Telescope for development monitoring:

```bash
composer require laravel/telescope --dev
php artisan telescope:install
php artisan migrate
```

Access at: `http://localhost:8000/telescope`

**Features**:
- Request monitoring
- Database query logs
- Cache operations
- Exception tracking
- Job monitoring

### Production Monitoring Tools

#### 1. New Relic APM

```bash
# Install New Relic PHP agent
wget -O - https://download.newrelic.com/548C16BF.gpg | sudo apt-key add -
sudo apt-get install newrelic-php5

# Configure
sudo newrelic-install install
```

**Metrics to Monitor**:
- Apdex score (target: >0.95)
- Response time (target: <100ms)
- Throughput (requests/min)
- Error rate (target: <0.1%)
- Database time (target: <20% of total)

#### 2. DataDog

```bash
# Install DataDog agent
DD_API_KEY=your_key bash -c "$(curl -L https://s3.amazonaws.com/dd-agent/scripts/install_script.sh)"

# Configure Laravel
composer require datadog/dd-trace
```

#### 3. Self-Hosted Monitoring Stack

**Prometheus + Grafana Setup**:

```yaml
# docker-compose.yml
version: '3'
services:
  prometheus:
    image: prom/prometheus
    ports:
      - "9090:9090"
    volumes:
      - ./prometheus.yml:/etc/prometheus/prometheus.yml

  grafana:
    image: grafana/grafana
    ports:
      - "3000:3000"
    volumes:
      - grafana-storage:/var/lib/grafana
```

### Key Metrics to Track

#### Application Metrics

```php
// Custom metrics in Laravel
use Illuminate\Support\Facades\Cache;

// Track API response times
$start = microtime(true);
// ... your code ...
$duration = (microtime(true) - $start) * 1000;
Cache::put("metric:api_response_time", $duration, 60);

// Track active sessions
$activeSessions = SlotSession::where('status', 'active')->count();
Cache::put("metric:active_sessions", $activeSessions, 60);

// Track wallet operations
Cache::increment("metric:wallet_transactions");
```

#### Database Metrics

```sql
-- Active connections
SHOW STATUS LIKE 'Threads_connected';

-- Slow queries
SELECT * FROM mysql.slow_log ORDER BY start_time DESC LIMIT 10;

-- Table sizes
SELECT 
    table_name AS 'Table',
    ROUND(((data_length + index_length) / 1024 / 1024), 2) AS 'Size (MB)'
FROM information_schema.TABLES 
WHERE table_schema = 'sugalan'
ORDER BY (data_length + index_length) DESC;

-- Index usage
SELECT * FROM sys.schema_unused_indexes;
```

#### System Metrics

```bash
# CPU usage
top -bn1 | grep "Cpu(s)"

# Memory usage
free -h

# Disk I/O
iostat -x 1

# Network
netstat -an | grep :80 | wc -l  # Active connections
```

---

## Database Optimization

### Index Analysis

```sql
-- Check if indexes are being used
EXPLAIN SELECT * FROM slot_sessions 
WHERE user_id = 1 AND status = 'active';

-- Missing indexes (run weekly)
SELECT * FROM sys.schema_tables_with_full_table_scans
WHERE object_schema = 'sugalan';

-- Duplicate indexes
SELECT * FROM sys.schema_redundant_indexes
WHERE table_schema = 'sugalan';
```

### Query Optimization

#### Before (Slow)
```php
// N+1 query problem
$sessions = SlotSession::where('status', 'active')->get();
foreach ($sessions as $session) {
    echo $session->user->name;  // Extra query per session
    echo $session->game->name;  // Extra query per session
}
```

#### After (Fast)
```php
// Eager loading
$sessions = SlotSession::where('status', 'active')
    ->with(['user', 'game', 'provider'])
    ->get();

foreach ($sessions as $session) {
    echo $session->user->name;  // No extra query
    echo $session->game->name;  // No extra query
}
```

### Database Connection Pooling

**config/database.php**:
```php
'mysql' => [
    'driver' => 'mysql',
    'host' => env('DB_HOST', '127.0.0.1'),
    'port' => env('DB_PORT', '3306'),
    'database' => env('DB_DATABASE', 'sugalan'),
    'username' => env('DB_USERNAME', 'root'),
    'password' => env('DB_PASSWORD', ''),
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'prefix' => '',
    'strict' => true,
    'engine' => null,
    'options' => extension_loaded('pdo_mysql') ? array_filter([
        PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
        PDO::ATTR_PERSISTENT => true,  // Connection pooling
        PDO::ATTR_EMULATE_PREPARES => false,
    ]) : [],
],
```

### Partitioning Strategy

For large tables (1M+ rows), implement partitioning:

```sql
-- Partition slot_sessions by month
ALTER TABLE slot_sessions 
PARTITION BY RANGE (YEAR(created_at) * 100 + MONTH(created_at)) (
    PARTITION p202412 VALUES LESS THAN (202501),
    PARTITION p202501 VALUES LESS THAN (202502),
    PARTITION p202502 VALUES LESS THAN (202503),
    -- Add new partitions monthly
    PARTITION pmax VALUES LESS THAN MAXVALUE
);
```

---

## Cache Strategy

### Current Cache Configuration

**Provider Cache**:
```php
// Cache for 1 hour
Cache::remember('slot_providers', 3600, function () {
    return SlotProvider::where('is_active', true)->get();
});
```

**Game Cache**:
```php
// Cache for 30 minutes
Cache::remember('slot_games_' . $providerId, 1800, function () use ($providerId) {
    return SlotGame::where('provider_id', $providerId)
        ->where('is_active', true)
        ->get();
});
```

### Cache Warming

Run on deployment to pre-populate cache:

```bash
php artisan cache:warm
```

**artisan command** (create if not exists):
```php
// app/Console/Commands/WarmCache.php
public function handle()
{
    // Warm providers
    SlotProvider::where('is_active', true)->get();
    
    // Warm games
    SlotGame::where('is_active', true)->get();
    
    $this->info('Cache warmed successfully!');
}
```

### Cache Invalidation

```php
// When provider updated
Cache::forget('slot_providers');

// When game updated
Cache::forget('slot_games_' . $game->provider_id);

// Clear all slot cache
Cache::tags(['slots'])->flush();
```

### Redis Configuration

**.env**:
```env
CACHE_DRIVER=redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
REDIS_DB=0

# Redis connection pooling
REDIS_CLIENT=predis
```

### Cache Performance Monitoring

```php
// Track cache hit rate
$totalRequests = Cache::get('cache:total_requests', 0);
$cacheHits = Cache::get('cache:hits', 0);
$hitRate = $totalRequests > 0 ? ($cacheHits / $totalRequests) * 100 : 0;

echo "Cache Hit Rate: {$hitRate}%";
// Target: >70%
```

---

## Scaling Guide

### Vertical Scaling (Single Server)

#### 1. Increase Resources

**Development**:
- 2 CPU cores, 4GB RAM → Handle 100 users

**Production (Small)**:
- 4 CPU cores, 8GB RAM → Handle 500 users

**Production (Medium)**:
- 8 CPU cores, 16GB RAM → Handle 1000 users

**Production (Large)**:
- 16 CPU cores, 32GB RAM → Handle 2000+ users

#### 2. PHP-FPM Tuning

**/etc/php/8.3/fpm/pool.d/www.conf**:
```ini
[www]
pm = dynamic
pm.max_children = 50           # Max concurrent requests
pm.start_servers = 10          # Initial workers
pm.min_spare_servers = 5       # Min idle workers
pm.max_spare_servers = 15      # Max idle workers
pm.max_requests = 500          # Restart worker after N requests
```

Calculate `pm.max_children`:
```
max_children = (Total RAM - OS RAM - MySQL RAM) / RAM per PHP process
             = (8GB - 1GB - 2GB) / 50MB
             = 5000MB / 50MB
             = 100
```

#### 3. MySQL Tuning

**/etc/mysql/my.cnf**:
```ini
[mysqld]
max_connections = 200
innodb_buffer_pool_size = 2G   # 50-70% of available RAM
innodb_log_file_size = 256M
innodb_flush_log_at_trx_commit = 2
query_cache_size = 64M
query_cache_type = 1
```

### Horizontal Scaling (Multiple Servers)

#### Architecture

```
                    ┌─────────────────┐
                    │  Load Balancer  │
                    │    (Nginx)      │
                    └────────┬────────┘
                             │
            ┌────────────────┼────────────────┐
            │                │                │
     ┌──────▼─────┐   ┌─────▼──────┐  ┌─────▼──────┐
     │  App Server│   │ App Server │  │ App Server │
     │     #1     │   │     #2     │  │     #3     │
     └──────┬─────┘   └─────┬──────┘  └─────┬──────┘
            │               │               │
            └───────────────┼───────────────┘
                            │
                  ┌─────────┴─────────┐
                  │                   │
            ┌─────▼──────┐    ┌──────▼─────┐
            │   MySQL    │    │   Redis    │
            │   Master   │    │   Cache    │
            └─────┬──────┘    └────────────┘
                  │
            ┌─────▼──────┐
            │   MySQL    │
            │   Replica  │
            └────────────┘
```

#### Load Balancer Configuration

**Nginx Load Balancer** (/etc/nginx/nginx.conf):
```nginx
upstream backend {
    least_conn;  # Load balance method
    server app1.example.com:8000 weight=3;
    server app2.example.com:8000 weight=2;
    server app3.example.com:8000 weight=1;
}

server {
    listen 80;
    server_name api.example.com;

    location / {
        proxy_pass http://backend;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        
        # Timeouts
        proxy_connect_timeout 60s;
        proxy_send_timeout 60s;
        proxy_read_timeout 60s;
    }
}
```

#### Session Management

Use Redis for shared sessions:

**.env** (all app servers):
```env
SESSION_DRIVER=redis
SESSION_CONNECTION=default
```

#### Database Read Replicas

**config/database.php**:
```php
'mysql' => [
    'read' => [
        'host' => [
            '192.168.1.2',  # Replica 1
            '192.168.1.3',  # Replica 2
        ],
    ],
    'write' => [
        'host' => ['192.168.1.1'],  # Master
    ],
    // ... other config
],
```

### Auto-Scaling (Cloud)

#### AWS Auto Scaling

```bash
# Install AWS CLI
aws configure

# Create launch template
aws ec2 create-launch-template \
    --launch-template-name slot-api-template \
    --launch-template-data file://template.json

# Create auto scaling group
aws autoscaling create-auto-scaling-group \
    --auto-scaling-group-name slot-api-asg \
    --launch-template LaunchTemplateName=slot-api-template \
    --min-size 2 \
    --max-size 10 \
    --desired-capacity 3 \
    --target-group-arns arn:aws:elasticloadbalancing:...

# Create scaling policies
aws autoscaling put-scaling-policy \
    --auto-scaling-group-name slot-api-asg \
    --policy-name scale-up \
    --scaling-adjustment 1 \
    --adjustment-type ChangeInCapacity
```

#### Scaling Triggers

- CPU > 70% for 5 minutes → Scale up
- CPU < 30% for 10 minutes → Scale down
- Memory > 80% → Scale up
- Active connections > 1000 → Scale up

---

## Troubleshooting

### Slow API Responses

**Symptoms**:
- Response time >500ms
- Timeout errors
- Users complaining

**Diagnosis**:
```bash
# Check slow queries
tail -f /var/log/mysql/slow-query.log

# Check Laravel logs
tail -f storage/logs/laravel.log

# Profile specific request
php artisan route:list  # Find route name
# Add profiling to controller
```

**Solutions**:
1. Add missing indexes
2. Enable query caching
3. Optimize N+1 queries
4. Increase PHP-FPM workers

### High Memory Usage

**Symptoms**:
- PHP-FPM OOM errors
- Swap usage high
- Server becomes unresponsive

**Diagnosis**:
```bash
# Check memory usage
free -h

# Check PHP memory
grep memory_limit /etc/php/8.3/fpm/php.ini

# Check which process uses most memory
ps aux --sort=-%mem | head -10
```

**Solutions**:
1. Increase server RAM
2. Reduce `pm.max_children`
3. Fix memory leaks
4. Implement pagination

### Database Connection Errors

**Symptoms**:
- "Too many connections" error
- "SQLSTATE[HY000] [2002]" error
- Connection timeouts

**Diagnosis**:
```sql
-- Check connections
SHOW PROCESSLIST;

-- Check max connections
SHOW VARIABLES LIKE 'max_connections';

-- Check current connections
SHOW STATUS LIKE 'Threads_connected';
```

**Solutions**:
1. Increase `max_connections` in MySQL
2. Reduce `pm.max_children` in PHP-FPM
3. Close idle connections
4. Implement connection pooling

### Cache Miss Rate High

**Symptoms**:
- Cache hit rate <50%
- Slow responses after deployment
- High database load

**Diagnosis**:
```bash
# Redis stats
redis-cli INFO stats

# Check cache keys
redis-cli KEYS slot_*

# Monitor cache operations
redis-cli MONITOR
```

**Solutions**:
1. Increase cache TTL
2. Warm cache on deployment
3. Use cache tags effectively
4. Review cache invalidation logic

---

## Performance Checklist

### Pre-Production

- [ ] Run all performance tests (100% pass rate)
- [ ] Execute load test with expected user count
- [ ] Configure database indexes
- [ ] Setup Redis cache
- [ ] Enable OPcache
- [ ] Configure PHP-FPM pool
- [ ] Setup MySQL connection pooling
- [ ] Implement query caching
- [ ] Enable gzip compression
- [ ] Setup CDN for static assets

### Production Monitoring

- [ ] Install APM tool (New Relic/DataDog)
- [ ] Setup error tracking (Sentry/Bugsnag)
- [ ] Configure log aggregation
- [ ] Setup database monitoring
- [ ] Enable slow query log
- [ ] Setup alerts (response time, errors, memory)
- [ ] Configure auto-scaling policies
- [ ] Setup backup verification
- [ ] Implement health checks
- [ ] Configure uptime monitoring

### Weekly Maintenance

- [ ] Review slow query log
- [ ] Check cache hit rate
- [ ] Analyze error logs
- [ ] Review database sizes
- [ ] Check index usage
- [ ] Monitor memory usage
- [ ] Review API latency trends
- [ ] Check for N+1 queries
- [ ] Verify backup integrity

---

## Additional Resources

- [Laravel Performance Best Practices](https://laravel.com/docs/11.x/deployment#optimization)
- [MySQL Performance Tuning](https://dev.mysql.com/doc/refman/8.0/en/optimization.html)
- [Redis Best Practices](https://redis.io/topics/optimization)
- [PHP-FPM Tuning Guide](https://www.php.net/manual/en/install.fpm.configuration.php)
- [Nginx Performance Tuning](https://www.nginx.com/blog/tuning-nginx/)

---

**Last Updated**: December 26, 2025  
**Next Review**: After production deployment
