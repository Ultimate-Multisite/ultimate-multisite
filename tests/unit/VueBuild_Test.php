<?php
use PHPUnit\Framework\TestCase;

final class VueBuild_Test extends TestCase {
	public function test_vue_min_is_production_flags(): void {
		$path = __DIR__ . '/../../assets/js/lib/vue.min.js';
		$this->assertFileExists($path, 'vue.min.js does not exist');
		$contents = file_get_contents($path);
		$this->assertNotFalse($contents);
		// Production build should set productionTip:false and devtools:false
		$this->assertStringContainsString('productionTip:!1', $contents, 'Expected productionTip disabled in production build');
		$this->assertStringContainsString('devtools:!1', $contents, 'Expected devtools disabled in production build');
	}

	public function test_vue_min_has_wu_vue_wrapper(): void {
		$path     = __DIR__ . '/../../assets/js/lib/vue.min.js';
		$contents = file_get_contents($path);
		$this->assertNotFalse($contents);
		$this->assertStringContainsString('window.wu_vue', $contents, 'Expected window.wu_vue wrapper present');
		$this->assertMatchesRegularExpression('/defineComponent/', $contents, 'Expected defineComponent to be exposed via wrapper');
	}
}
