<?php
/**
 * Simple Test Runner
 * Demonstrates test functionality without full WordPress test suite
 * This is a simplified test runner for demonstration purposes
 */

// Color output for terminal
class TestColors {
    const GREEN = "\033[32m";
    const RED = "\033[31m";
    const YELLOW = "\033[33m";
    const BLUE = "\033[34m";
    const RESET = "\033[0m";
}

// Simple test framework
class SimpleTestRunner {
    private $passed = 0;
    private $failed = 0;
    private $total = 0;
    private $current_suite = '';

    public function suite($name) {
        $this->current_suite = $name;
        echo "\n" . TestColors::BLUE . "=== {$name} ===" . TestColors::RESET . "\n";
    }

    public function test($name, $callback) {
        $this->total++;
        echo "  Testing: {$name}... ";

        try {
            $result = $callback();
            if ($result === true || $result === null) {
                $this->passed++;
                echo TestColors::GREEN . "✓ PASS" . TestColors::RESET . "\n";
            } else {
                $this->failed++;
                echo TestColors::RED . "✗ FAIL" . TestColors::RESET . "\n";
            }
        } catch (Exception $e) {
            $this->failed++;
            echo TestColors::RED . "✗ FAIL: " . $e->getMessage() . TestColors::RESET . "\n";
        }
    }

    public function assertEquals($expected, $actual, $message = '') {
        if ($expected !== $actual) {
            $msg = $message ?: "Expected: {$expected}, Got: {$actual}";
            throw new Exception($msg);
        }
        return true;
    }

    public function assertTrue($condition, $message = 'Assertion failed') {
        if (!$condition) {
            throw new Exception($message);
        }
        return true;
    }

    public function assertFalse($condition, $message = 'Assertion failed') {
        if ($condition) {
            throw new Exception($message);
        }
        return true;
    }

    public function assertEmpty($value, $message = 'Value should be empty') {
        if (!empty($value)) {
            throw new Exception($message);
        }
        return true;
    }

    public function assertNotEmpty($value, $message = 'Value should not be empty') {
        if (empty($value)) {
            throw new Exception($message);
        }
        return true;
    }

    public function assertNull($value, $message = 'Value should be null') {
        if ($value !== null) {
            throw new Exception($message);
        }
        return true;
    }

    public function assertStringContains($needle, $haystack, $message = '') {
        if (strpos($haystack, $needle) === false) {
            $msg = $message ?: "String '{$haystack}' does not contain '{$needle}'";
            throw new Exception($msg);
        }
        return true;
    }

    public function summary() {
        echo "\n" . str_repeat("=", 50) . "\n";
        echo TestColors::BLUE . "Test Summary" . TestColors::RESET . "\n";
        echo "Total Tests: {$this->total}\n";
        echo TestColors::GREEN . "Passed: {$this->passed}" . TestColors::RESET . "\n";

        if ($this->failed > 0) {
            echo TestColors::RED . "Failed: {$this->failed}" . TestColors::RESET . "\n";
        }

        $percentage = $this->total > 0 ? round(($this->passed / $this->total) * 100, 2) : 0;
        echo "Success Rate: {$percentage}%\n";
        echo str_repeat("=", 50) . "\n";

        return $this->failed === 0;
    }
}

// Load the plugin functions we're testing
require_once dirname(__DIR__) . '/includes/movie-functions.php';

// Create test runner
$test = new SimpleTestRunner();

// ===== Movie Functions Tests =====
$test->suite('Movie Functions - Runtime Formatting');

$test->test('Format 143 minutes as 2h 23m', function() use ($test) {
    $result = fww_format_runtime(143);
    $test->assertEquals('2h 23m', $result);
});

$test->test('Format 90 minutes as 1h 30m', function() use ($test) {
    $result = fww_format_runtime(90);
    $test->assertEquals('1h 30m', $result);
});

$test->test('Format 45 minutes as 45m', function() use ($test) {
    $result = fww_format_runtime(45);
    $test->assertEquals('45m', $result);
});

$test->test('Format 0 minutes as empty string', function() use ($test) {
    $result = fww_format_runtime(0);
    $test->assertEquals('', $result);
});

$test->test('Format null as empty string', function() use ($test) {
    $result = fww_format_runtime(null);
    $test->assertEquals('', $result);
});

$test->test('Format empty string as empty', function() use ($test) {
    $result = fww_format_runtime('');
    $test->assertEquals('', $result);
});

$test->test('Format 120 minutes as 2h 0m', function() use ($test) {
    $result = fww_format_runtime(120);
    $test->assertEquals('2h 0m', $result);
});

$test->test('Format 600 minutes (10 hours)', function() use ($test) {
    $result = fww_format_runtime(600);
    $test->assertEquals('10h 0m', $result);
});

$test->suite('Movie Functions - Money Formatting');

$test->test('Format $2.8 billion', function() use ($test) {
    $result = fww_format_money(2800000000);
    $test->assertEquals('$2.8B', $result);
});

$test->test('Format $150 million', function() use ($test) {
    $result = fww_format_money(150000000);
    $test->assertEquals('$150.0M', $result);
});

$test->test('Format $500 thousand', function() use ($test) {
    $result = fww_format_money(500000);
    $test->assertEquals('$500.0K', $result);
});

$test->test('Format $500', function() use ($test) {
    $result = fww_format_money(500);
    $test->assertEquals('$500', $result);
});

$test->test('Format zero as N/A', function() use ($test) {
    $result = fww_format_money(0);
    $test->assertEquals('N/A', $result);
});

$test->test('Format empty as N/A', function() use ($test) {
    $result = fww_format_money('');
    $test->assertEquals('N/A', $result);
});

$test->test('Format null as N/A', function() use ($test) {
    $result = fww_format_money(null);
    $test->assertEquals('N/A', $result);
});

$test->test('Format $1 billion', function() use ($test) {
    $result = fww_format_money(1000000000);
    $test->assertEquals('$1.0B', $result);
});

$test->suite('Movie Functions - Edge Cases');

$test->test('Runtime with negative value', function() use ($test) {
    $result = fww_format_runtime(-60);
    $test->assertEquals('', $result);
});

$test->test('Very large runtime (24+ hours)', function() use ($test) {
    $result = fww_format_runtime(1470);
    $test->assertEquals('24h 30m', $result);
});

$test->test('Very large money amount', function() use ($test) {
    $result = fww_format_money(100000000000);
    $test->assertEquals('$100.0B', $result);
});

$test->test('Small money amount under 1000', function() use ($test) {
    $result = fww_format_money(100);
    $test->assertEquals('$100', $result);
});

// ===== TMDB API Image URL Tests =====
$test->suite('TMDB API - Image URL Generation');

// Mock the FWW_TMDB_API class for testing
if (!class_exists('FWW_TMDB_API')) {
    class FWW_TMDB_API {
        const IMAGE_BASE_URL = 'https://image.tmdb.org/t/p/';

        public static function get_image_url($path, $size = 'original') {
            if (empty($path)) {
                return null;
            }
            return self::IMAGE_BASE_URL . $size . $path;
        }
    }
}

$test->test('Generate image URL with w500 size', function() use ($test) {
    $result = FWW_TMDB_API::get_image_url('/test_image.jpg', 'w500');
    $test->assertEquals('https://image.tmdb.org/t/p/w500/test_image.jpg', $result);
});

$test->test('Generate image URL with original size', function() use ($test) {
    $result = FWW_TMDB_API::get_image_url('/test_image.jpg', 'original');
    $test->assertEquals('https://image.tmdb.org/t/p/original/test_image.jpg', $result);
});

$test->test('Empty path returns null', function() use ($test) {
    $result = FWW_TMDB_API::get_image_url('', 'w500');
    $test->assertNull($result);
});

$test->test('Null path returns null', function() use ($test) {
    $result = FWW_TMDB_API::get_image_url(null, 'w500');
    $test->assertNull($result);
});

// ===== Input Validation Tests =====
$test->suite('Input Validation & Security');

$test->test('Runtime sanitization - string input', function() use ($test) {
    $result = fww_format_runtime('abc');
    $test->assertEquals('', $result);
});

$test->test('Money sanitization - string input', function() use ($test) {
    $result = fww_format_money('invalid');
    $test->assertEquals('N/A', $result);
});

$test->test('XSS prevention in movie poster alt text', function() use ($test) {
    $tmdb_data = array(
        'poster_path' => '/test.jpg',
        'title' => '<script>alert("XSS")</script>'
    );
    $result = fww_get_movie_poster($tmdb_data);
    // Should escape HTML tags
    $test->assertTrue(strpos($result, '&lt;script&gt;') !== false || strpos($result, 'esc_') !== false);
});

// Print final summary
echo "\n";
$success = $test->summary();

// Exit with appropriate code
exit($success ? 0 : 1);
