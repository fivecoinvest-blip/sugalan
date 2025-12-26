# Performance Testing Quick Reference

## Running Tests

### Automated Performance Tests
```bash
# Run all performance tests
php artisan test tests/Performance/SlotPerformanceTest.php

# Run with detailed output
php artisan test tests/Performance/SlotPerformanceTest.php --testdox

# Run specific test
php artisan test --filter=test_concurrent_game_launches
```

### Load Testing
```bash
# Light load (10 concurrent users)
./load-test.sh light 10

# Moderate load (50 concurrent users)
./load-test.sh moderate 50

# Heavy load (100 concurrent users)
./load-test.sh heavy 100

# Stress test (500 concurrent users)
./load-test.sh stress 500
```

## Performance Metrics (Current)

| Test | Result | Target | Status |
|------|--------|--------|--------|
| API Response (Provider) | 1.51ms | <100ms | ✅ 66x faster |
| API Response (Games) | 1.83ms | <200ms | ✅ 109x faster |
| Game Launch | 4.21ms | <500ms | ✅ 119x faster |
| DB Query (100 sessions) | 3.58ms | <50ms | ✅ 93% under |
| Cache Improvement | 63.8% | >30% | ✅ 213% of target |
| Search (500 games) | 6.89ms | <300ms | ✅ 43x faster |
| Wallet Transaction | 0.76ms | <100ms | ✅ 131x faster |
| Cleanup (1000 sessions) | 3.07ms | <1000ms | ✅ 99.7% under |
| Memory (1000 games) | 4MB | <50MB | ✅ 92% under |

## Capacity Ratings

| Concurrent Users | Performance | Status |
|------------------|-------------|--------|
| 0-100 | Excellent (<10ms) | ✅ |
| 100-500 | Good (<20ms) | ✅ |
| 500-1000 | Acceptable (~40ms) | ⚠️ |
| 1000+ | Needs scaling | 🔴 |

## Quick Monitoring

### Check Performance
```bash
# API health
curl http://localhost:8000/api/health

# Database connections
mysql -e "SHOW STATUS LIKE 'Threads_connected';"

# Cache stats
redis-cli INFO stats

# Memory usage
free -h

# CPU usage
top -bn1 | grep "Cpu(s)"
```

### Key Files
- **Test Suite**: `tests/Performance/SlotPerformanceTest.php`
- **Load Script**: `./load-test.sh`
- **Results**: `PERFORMANCE_TEST_RESULTS.md`
- **Guide**: `docs/PERFORMANCE_GUIDE.md`
- **Summary**: `PERFORMANCE_TESTING_SUMMARY.md`

## Scaling Triggers

**Scale when**:
- Average response time >50ms
- 95th percentile >200ms
- Database CPU >70%
- Cache hit rate <50%
- Active sessions >10,000

## Emergency Procedures

### If slow responses detected:
1. Check database: `SHOW PROCESSLIST;`
2. Check cache: `redis-cli INFO stats`
3. Check logs: `tail -f storage/logs/laravel.log`
4. Restart PHP-FPM: `sudo systemctl restart php8.3-fpm`

### If memory issues:
1. Check usage: `free -h`
2. Check PHP processes: `ps aux --sort=-%mem | head`
3. Reduce max_children in PHP-FPM
4. Clear cache: `php artisan cache:clear`

## Production Checklist

- [ ] All 9 performance tests passing
- [ ] Load tested with expected user count
- [ ] Monitoring tools installed
- [ ] Alerts configured
- [ ] Database optimized
- [ ] Cache strategy implemented
- [ ] Auto-scaling policies set
- [ ] Backup verified
- [ ] Health checks working

## Support

- Full documentation: `docs/PERFORMANCE_GUIDE.md`
- Test results: `PERFORMANCE_TEST_RESULTS.md`
- Summary: `PERFORMANCE_TESTING_SUMMARY.md`
