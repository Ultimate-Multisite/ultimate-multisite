<?php

/**
 * Simple Performance Test for Validation
 * 
 * This is a minimal version that doesn't require WordPress test environment
 * just to validate that our performance testing framework works.
 */

// Mock WordPress functions
function get_plugin_data($plugin_file) {
    return ['Version' => '2.4.7'];
}

function get_bloginfo($show) {
    return $show === 'version' ? '6.4.3' : 'Test Site';
}

function wu_get_dashboard_statistics() {
    return ['sites' => 10, 'users' => 50];
}

function wu_get_plans($args = []) {
    return array_fill(0, $args['number'] ?? 5, ['id' => 1, 'name' => 'Test Plan']);
}

function wu_get_signup_fields() {
    return array_fill(0, 10, ['id' => 1, 'type' => 'text']);
}

function wu_get_memberships($args = []) {
    return array_fill(0, $args['number'] ?? 5, ['id' => 1, 'status' => 'active']);
}

function wu_get_sites($args = []) {
    return array_fill(0, $args['number'] ?? 5, ['id' => 1, 'domain' => 'test.com']);
}

// Mock global $wpdb
$GLOBALS['wpdb'] = (object) ['num_queries' => 0];

class Simple_Performance_Benchmark {
    
    private $results = [];
    private $start_time;
    private $start_memory;
    
    public function __construct() {
        $this->results['test_run'] = date('Y-m-d H:i:s');
        $this->results['php_version'] = PHP_VERSION;
        $this->results['wordpress_version'] = get_bloginfo('version');
        $this->results['plugin_version'] = '2.4.7';
    }
    
    private function start_measurement() {
        $this->start_time = microtime(true);
        $this->start_memory = memory_get_usage(true);
        
        // Reset WordPress query count
        global $wpdb;
        $wpdb->num_queries = 0;
    }
    
    private function end_measurement($operation_name) {
        $end_time = microtime(true);
        $end_memory = memory_get_usage(true);
        
        global $wpdb;
        
        $this->results[$operation_name] = [
            'execution_time_ms' => round(($end_time - $this->start_time) * 1000, 2),
            'memory_usage_mb' => round(($end_memory - $this->start_memory) / 1024 / 1024, 2),
            'peak_memory_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
            'database_queries' => $wpdb->num_queries,
        ];
    }
    
    public function benchmark_dashboard_loading() {
        $this->start_measurement();
        
        // Simulate dashboard loading
        usleep(1000); // Simulate dashboard registration
        
        // Test dashboard data loading
        $stats = wu_get_dashboard_statistics();
        
        $this->end_measurement('dashboard_loading');
    }
    
    public function benchmark_checkout_process() {
        $this->start_measurement();
        
        // Simulate checkout initialization
        usleep(500); // Simulate checkout setup
        
        // Test pricing plans loading
        $plans = wu_get_plans(['number' => 10]);
        
        // Test form rendering preparation
        $fields = wu_get_signup_fields();
        
        $this->end_measurement('checkout_process');
    }
    
    public function benchmark_site_creation() {
        $this->start_measurement();
        
        // Test site creation data preparation
        $site_data = [
            'domain' => 'test-' . time() . '.example.com',
            'title' => 'Test Site ' . time(),
            'user_id' => 1,
            'plan_id' => 1,
        ];
        
        // Validate site data (without actually creating)
        usleep(500); // Simulate validation
        $is_valid = true;
        
        $this->end_measurement('site_creation_validation');
    }
    
    public function benchmark_membership_operations() {
        $this->start_measurement();
        
        // Test membership queries
        $memberships = wu_get_memberships(['number' => 10]);
        
        // Test membership status calculations
        foreach ($memberships as $membership) {
            $membership['status']; // Access status
        }
        
        $this->end_measurement('membership_operations');
    }
    
    public function benchmark_api_endpoints() {
        $this->start_measurement();
        
        // Test API endpoint registration
        usleep(800); // Simulate API registration
        
        // Test API data preparation
        $sites = wu_get_sites(['number' => 5]);
        $api_data = [];
        
        foreach ($sites as $site) {
            $api_data[] = [
                'id' => $site['id'],
                'domain' => $site['domain'],
                'title' => $site['domain'],
            ];
        }
        
        $this->end_measurement('api_endpoints');
    }
    
    public function benchmark_database_queries() {
        global $wpdb;
        
        $this->start_measurement();
        
        // Simulate database operations
        $wpdb->num_queries = 5; // Simulate 5 queries
        
        usleep(200); // Simulate query time
        
        $this->end_measurement('database_queries');
    }
    
    public function run_all_benchmarks() {
        echo "Starting simplified performance benchmarks...\n";
        
        try {
            $this->benchmark_dashboard_loading();
            echo "✓ Dashboard loading benchmark completed\n";
            
            $this->benchmark_checkout_process();
            echo "✓ Checkout process benchmark completed\n";
            
            $this->benchmark_site_creation();
            echo "✓ Site creation validation benchmark completed\n";
            
            $this->benchmark_membership_operations();
            echo "✓ Membership operations benchmark completed\n";
            
            $this->benchmark_api_endpoints();
            echo "✓ API endpoints benchmark completed\n";
            
            $this->benchmark_database_queries();
            echo "✓ Database queries benchmark completed\n";
            
        } catch (Exception $e) {
            $this->results['error'] = $e->getMessage();
            echo "✗ Benchmark failed: " . $e->getMessage() . "\n";
        }
        
        return $this->results;
    }
    
    public function save_results($filename = null) {
        $filename = $filename ?: 'simple-performance-results-' . date('Y-m-d-H-i-s') . '.json';
        $filepath = dirname(__FILE__) . '/' . $filename;
        
        file_put_contents($filepath, json_encode($this->results, JSON_PRETTY_PRINT));
        echo "Results saved to: $filepath\n";
        
        return $filepath;
    }
}

// Run benchmarks if this script is executed directly
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    $benchmark = new Simple_Performance_Benchmark();
    $results = $benchmark->run_all_benchmarks();
    
    // Output JSON for CI/CD consumption
    echo json_encode($results, JSON_PRETTY_PRINT);
    
    // Also save to file
    $benchmark->save_results();
}