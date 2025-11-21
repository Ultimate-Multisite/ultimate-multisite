# Performance Testing Guide

## Overview

This repository includes automated performance testing that runs on every pull request to detect performance regressions in the Ultimate Multisite plugin.

## How It Works

### Performance Benchmarks

The performance tests measure critical plugin operations:

1. **Dashboard Loading** - Time to load admin dashboard data
2. **Checkout Process** - Performance of checkout initialization and form preparation
3. **Site Creation Validation** - Speed of site creation data validation
4. **Membership Operations** - Performance of membership queries and status calculations
5. **API Endpoints** - Speed of API data preparation and endpoint registration
6. **Database Queries** - Performance of common database operations

### Metrics Tracked

- **Execution Time** (milliseconds) - How long operations take
- **Memory Usage** (MB) - Memory consumed during operations  
- **Database Queries** - Number of database queries performed
- **Peak Memory** (MB) - Maximum memory usage

### Regression Detection

The system compares performance between the base branch and PR branch:

- **Warning Threshold**: 15% increase in execution time, 20% increase in memory, 10% increase in queries
- **Critical Threshold**: 30% increase in execution time, 40% increase in memory, 25% increase in queries
- **Build Failure**: Critical regressions will block PR merging
- **PR Comments**: Automated performance reports posted to pull requests

## Running Tests Locally

### Prerequisites

```bash
# Install dependencies
composer install

# Setup WordPress test environment
bash bin/install-wp-tests.sh wordpress_test root root mysql latest
```

### Run Performance Tests

```bash
# Run all benchmarks
php tests/performance-benchmark.php

# Save results to specific file
php tests/performance-benchmark.php > my-results.json
```

### Compare Results

```bash
# Compare two performance result files
php tests/performance-comparator.php baseline.json current.json
```

## Understanding Results

### Performance Report Format

```
# Performance Test Results

## Summary

| Metric | Count |
|--------|-------|
| Total Tests | 6 |
| Critical Regressions | 0 |
| Regressions | 1 |
| Improvements | 2 |
| No Change | 3 |

## Detailed Results

### dashboard_loading ⚠️

**Issues:**
- Warning: execution_time_ms increased by 18.5% (threshold: 15%)

| Metric | Baseline | Current | Change |
|--------|----------|---------|--------|
| execution_time_ms | 45.2 | 53.6 | +18.5% |
| memory_usage_mb | 2.1 | 2.3 | +9.5% |
```

### Status Indicators

- 🚨 **Critical Regression** - Performance degradation exceeding critical thresholds
- ⚠️ **Regression** - Performance degradation exceeding warning thresholds  
- ✨ **Improvement** - Performance improvement of 5% or more
- ✅ **No Change** - Performance within acceptable range

## Performance Best Practices

### Code Optimization

1. **Database Queries**
   - Use `wu_get_*()` functions with proper caching
   - Avoid N+1 query problems
   - Use WordPress caching mechanisms

2. **Memory Management**
   - Free large objects when no longer needed
   - Use generators for large datasets
   - Monitor memory usage in loops

3. **Execution Time**
   - Cache expensive computations
   - Use efficient algorithms
   - Minimize external API calls

### Testing Guidelines

1. **Before Submitting PR**
   ```bash
   # Run performance tests locally
   php tests/performance-benchmark.php > current.json
   
   # Compare with main branch
   git checkout main
   php tests/performance-benchmark.php > baseline.json
   git checkout -
   
   # Analyze results
   php tests/performance-comparator.php baseline.json current.json
   ```

2. **When Performance Regressions Occur**
   - Review the specific operation showing regression
   - Check for new database queries or loops
   - Consider caching strategies
   - Profile with Xdebug or Blackfire if needed

## Configuration

### Threshold Adjustment

Edit `tests/performance-comparator.php` to modify thresholds:

```php
private $thresholds = [
    'execution_time_ms' => 15, // Adjust warning threshold
    'memory_usage_mb' => 20,   // Adjust warning threshold
    'database_queries' => 10,  // Adjust warning threshold
];

private $critical_thresholds = [
    'execution_time_ms' => 30, // Adjust critical threshold
    'memory_usage_mb' => 40,   // Adjust critical threshold
    'database_queries' => 25,  // Adjust critical threshold
];
```

### Adding New Benchmarks

1. Add benchmark method to `tests/performance-benchmark.php`:

```php
public function benchmark_new_feature() {
    $this->start_measurement();
    
    // Your performance test code here
    $this->do_something_expensive();
    
    $this->end_measurement('new_feature');
}
```

2. Add to `run_all_benchmarks()` method:

```php
public function run_all_benchmarks() {
    // ... existing benchmarks ...
    
    $this->benchmark_new_feature();
    echo "✓ New feature benchmark completed\n";
}
```

3. Update benchmark list in `tests/performance-comparator.php`:

```php
$benchmarks = [
    'dashboard_loading',
    'checkout_process',
    // ... existing benchmarks ...
    'new_feature',  // Add your new benchmark
];
```

## Troubleshooting

### Common Issues

1. **"WordPress test environment not found"**
   - Ensure WordPress test environment is properly installed
   - Run `bash bin/install-wp-tests.sh wordpress_test root root mysql latest`

2. **"Database connection failed"**
   - Check MySQL service is running
   - Verify database credentials in test configuration

3. **"Memory limit exceeded"**
   - Increase PHP memory limit in `php.ini`
   - Check for memory leaks in benchmark code

### Debug Mode

Enable debug output by setting environment variable:

```bash
DEBUG=1 php tests/performance-benchmark.php
```

This will provide additional information about each benchmark step.