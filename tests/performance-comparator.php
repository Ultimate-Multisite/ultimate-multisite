<?php

/**
 * Performance Comparison Utility
 * 
 * Compares performance results between baseline and current branch
 * and detects regressions based on configurable thresholds.
 */

class Performance_Comparator {
    
    private $baseline_results;
    private $current_results;
    private $thresholds = [
        'execution_time_ms' => 15, // 15% increase threshold
        'memory_usage_mb' => 20,   // 20% increase threshold
        'database_queries' => 10,  // 10% increase threshold
    ];
    
    private $critical_thresholds = [
        'execution_time_ms' => 30, // 30% increase for critical
        'memory_usage_mb' => 40,   // 40% increase for critical
        'database_queries' => 25,  // 25% increase for critical
    ];
    
    public function __construct($baseline_file, $current_file) {
        $this->baseline_results = $this->load_results($baseline_file);
        $this->current_results = $this->load_results($current_file);
    }
    
    private function load_results($file) {
        if (!file_exists($file)) {
            throw new Exception("Performance results file not found: $file");
        }
        
        $content = file_get_contents($file);
        $results = json_decode($content, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Invalid JSON in results file: $file");
        }
        
        return $results;
    }
    
    public function compare() {
        $comparison = [
            'summary' => [
                'total_tests' => 0,
                'regressions' => 0,
                'critical_regressions' => 0,
                'improvements' => 0,
                'no_change' => 0,
            ],
            'details' => [],
            'status' => 'pass'
        ];
        
        // Compare each benchmark
        $benchmarks = [
            'dashboard_loading',
            'checkout_process', 
            'site_creation_validation',
            'membership_operations',
            'api_endpoints',
            'database_queries'
        ];
        
        foreach ($benchmarks as $benchmark) {
            if (!isset($this->baseline_results[$benchmark]) || !isset($this->current_results[$benchmark])) {
                continue;
            }
            
            $comparison['summary']['total_tests']++;
            
            $result = $this->compare_benchmark($benchmark);
            $comparison['details'][$benchmark] = $result;
            
            // Update summary counters
            if ($result['status'] === 'critical_regression') {
                $comparison['summary']['critical_regressions']++;
                $comparison['status'] = 'fail';
            } elseif ($result['status'] === 'regression') {
                $comparison['summary']['regressions']++;
            } elseif ($result['status'] === 'improvement') {
                $comparison['summary']['improvements']++;
            } else {
                $comparison['summary']['no_change']++;
            }
        }
        
        return $comparison;
    }
    
    private function compare_benchmark($benchmark) {
        $baseline = $this->baseline_results[$benchmark];
        $current = $this->current_results[$benchmark];
        
        $result = [
            'baseline' => $baseline,
            'current' => $current,
            'changes' => [],
            'status' => 'no_change',
            'issues' => []
        ];
        
        foreach ($this->thresholds as $metric => $threshold) {
            if (!isset($baseline[$metric]) || !isset($current[$metric])) {
                continue;
            }
            
            $baseline_value = $baseline[$metric];
            $current_value = $current[$metric];
            
            if ($baseline_value == 0) {
                continue; // Avoid division by zero
            }
            
            $change_percent = (($current_value - $baseline_value) / $baseline_value) * 100;
            
            $result['changes'][$metric] = [
                'baseline' => $baseline_value,
                'current' => $current_value,
                'change_percent' => round($change_percent, 2),
                'change_absolute' => $current_value - $baseline_value
            ];
            
            // Check for critical regression
            if ($change_percent > $this->critical_thresholds[$metric]) {
                $result['status'] = 'critical_regression';
                $result['issues'][] = "Critical: {$metric} increased by {$change_percent}% (threshold: {$this->critical_thresholds[$metric]}%)";
            }
            // Check for normal regression
            elseif ($change_percent > $this->thresholds[$metric]) {
                if ($result['status'] !== 'critical_regression') {
                    $result['status'] = 'regression';
                }
                $result['issues'][] = "Warning: {$metric} increased by {$change_percent}% (threshold: {$this->thresholds[$metric]}%)";
            }
            // Check for improvement
            elseif ($change_percent < -5) { // 5% improvement threshold
                if ($result['status'] === 'no_change') {
                    $result['status'] = 'improvement';
                }
            }
        }
        
        return $result;
    }
    
    public function generate_markdown_report() {
        $comparison = $this->compare();
        
        $markdown = "# Performance Test Results\n\n";
        
        // Summary section
        $markdown .= "## Summary\n\n";
        $markdown .= "| Metric | Count |\n";
        $markdown .= "|--------|-------|\n";
        $markdown .= "| Total Tests | {$comparison['summary']['total_tests']} |\n";
        $markdown .= "| Critical Regressions | {$comparison['summary']['critical_regressions']} |\n";
        $markdown .= "| Regressions | {$comparison['summary']['regressions']} |\n";
        $markdown .= "| Improvements | {$comparison['summary']['improvements']} |\n";
        $markdown .= "| No Change | {$comparison['summary']['no_change']} |\n\n";
        
        // Overall status
        $status_emoji = $comparison['status'] === 'pass' ? '✅' : '❌';
        $markdown .= "**Overall Status: {$status_emoji} {$comparison['status']}**\n\n";
        
        // Detailed results
        $markdown .= "## Detailed Results\n\n";
        
        foreach ($comparison['details'] as $benchmark => $result) {
            $status_emoji = $this->get_status_emoji($result['status']);
            $markdown .= "### {$benchmark} {$status_emoji}\n\n";
            
            if (!empty($result['issues'])) {
                $markdown .= "**Issues:**\n";
                foreach ($result['issues'] as $issue) {
                    $markdown .= "- {$issue}\n";
                }
                $markdown .= "\n";
            }
            
            $markdown .= "| Metric | Baseline | Current | Change |\n";
            $markdown .= "|--------|----------|---------|--------|\n";
            
            foreach ($result['changes'] as $metric => $change) {
                $change_display = $change['change_percent'] > 0 
                    ? "+{$change['change_percent']}%" 
                    : "{$change['change_percent']}%";
                    
                $markdown .= "| {$metric} | {$change['baseline']} | {$change['current']} | {$change_display} |\n";
            }
            
            $markdown .= "\n";
        }
        
        return $markdown;
    }
    
    private function get_status_emoji($status) {
        switch ($status) {
            case 'critical_regression':
                return '🚨';
            case 'regression':
                return '⚠️';
            case 'improvement':
                return '✨';
            default:
                return '✅';
        }
    }
    
    public function save_comparison_report($filename = null) {
        $comparison = $this->compare();
        $filename = $filename ?: 'performance-comparison-' . date('Y-m-d-H-i-s') . '.json';
        $filepath = dirname(__FILE__) . '/' . $filename;
        
        file_put_contents($filepath, json_encode($comparison, JSON_PRETTY_PRINT));
        return $filepath;
    }
}

// Run comparison if this script is executed directly
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    $baseline_file = $argv[1] ?? null;
    $current_file = $argv[2] ?? null;
    
    if (!$baseline_file || !$current_file) {
        die("Usage: php performance-comparator.php <baseline_results.json> <current_results.json>\n");
    }
    
    try {
        $comparator = new Performance_Comparator($baseline_file, $current_file);
        
        // Save detailed comparison
        $comparator->save_comparison_report();
        
        // Output markdown report for PR comment
        echo $comparator->generate_markdown_report();
        
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
        exit(1);
    }
}