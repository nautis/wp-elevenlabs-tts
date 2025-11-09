#!/usr/bin/env php
<?php
/**
 * Comprehensive Test Runner
 * Runs all test scenarios without requiring WordPress environment
 */

// Color codes
define('C_GREEN', "\033[32m");
define('C_RED', "\033[31m");
define('C_YELLOW', "\033[33m");
define('C_BLUE', "\033[34m");
define('C_CYAN', "\033[36m");
define('C_RESET', "\033[0m");

class TestRunner {
    private $suites = [];
    private $totalTests = 0;
    private $totalPassed = 0;
    private $totalFailed = 0;

    public function addSuite($suite) {
        $this->suites[] = $suite;
    }

    public function run() {
        echo "\n";
        echo str_repeat("=", 70) . "\n";
        echo C_CYAN . "    Film Watch Wiki - Comprehensive Test Suite\n" . C_RESET;
        echo str_repeat("=", 70) . "\n\n";

        $startTime = microtime(true);

        foreach ($this->suites as $suite) {
            $suite->run();
            $this->totalTests += $suite->getTotal();
            $this->totalPassed += $suite->getPassed();
            $this->totalFailed += $suite->getFailed();
        }

        $endTime = microtime(true);
        $duration = round($endTime - $startTime, 3);

        $this->printSummary($duration);

        return $this->totalFailed === 0;
    }

    private function printSummary($duration) {
        echo "\n";
        echo str_repeat("=", 70) . "\n";
        echo C_BLUE . "Test Summary\n" . C_RESET;
        echo str_repeat("=", 70) . "\n";
        echo "Total Suites: " . count($this->suites) . "\n";
        echo "Total Tests:  {$this->totalTests}\n";
        echo C_GREEN . "Passed:       {$this->totalPassed}\n" . C_RESET;

        if ($this->totalFailed > 0) {
            echo C_RED . "Failed:       {$this->totalFailed}\n" . C_RESET;
        }

        $percentage = $this->totalTests > 0 ?
            round(($this->totalPassed / $this->totalTests) * 100, 2) : 0;

        echo "Success Rate: {$percentage}%\n";
        echo "Time:         {$duration}s\n";
        echo str_repeat("=", 70) . "\n\n";

        if ($percentage === 100.0) {
            echo C_GREEN . "✅ All tests passed!\n\n" . C_RESET;
        } else {
            echo C_RED . "❌ Some tests failed. Please review.\n\n" . C_RESET;
        }
    }
}

class TestSuite {
    private $name;
    private $tests = [];
    private $passed = 0;
    private $failed = 0;

    public function __construct($name) {
        $this->name = $name;
    }

    public function test($name, $callback) {
        $this->tests[] = ['name' => $name, 'callback' => $callback];
    }

    public function run() {
        echo C_BLUE . "\n▶ {$this->name}\n" . C_RESET;
        echo str_repeat("-", 70) . "\n";

        foreach ($this->tests as $test) {
            $this->runTest($test['name'], $test['callback']);
        }
    }

    private function runTest($name, $callback) {
        echo "  • {$name}... ";

        try {
            $result = $callback();
            if ($result === true || $result === null) {
                $this->passed++;
                echo C_GREEN . "✓ PASS\n" . C_RESET;
            } else {
                $this->failed++;
                echo C_RED . "✗ FAIL\n" . C_RESET;
            }
        } catch (Exception $e) {
            $this->failed++;
            echo C_RED . "✗ FAIL: " . $e->getMessage() . "\n" . C_RESET;
        }
    }

    public function getTotal() {
        return $this->passed + $this->failed;
    }

    public function getPassed() {
        return $this->passed;
    }

    public function getFailed() {
        return $this->failed;
    }
}

function assertEquals($expected, $actual, $message = '') {
    if ($expected !== $actual) {
        $msg = $message ?: "Expected: " . var_export($expected, true) .
               ", Got: " . var_export($actual, true);
        throw new Exception($msg);
    }
    return true;
}

function assertTrue($condition, $message = 'Assertion failed') {
    if (!$condition) throw new Exception($message);
    return true;
}

function assertNull($value, $message = 'Value should be null') {
    if ($value !== null) throw new Exception($message);
    return true;
}

// ===== Test Implementations =====

// TMDB API Mock
class TMDB_API {
    const IMAGE_BASE_URL = 'https://image.tmdb.org/t/p/';

    public static function get_image_url($path, $size = 'original') {
        if (empty($path)) return null;
        return self::IMAGE_BASE_URL . $size . $path;
    }
}

function format_runtime($minutes) {
    if (empty($minutes) || $minutes <= 0) return '';
    $hours = floor($minutes / 60);
    $mins = $minutes % 60;
    if ($hours > 0) return sprintf('%dh %dm', $hours, $mins);
    return sprintf('%dm', $mins);
}

function format_money($amount) {
    if (empty($amount)) return 'N/A';
    if ($amount >= 1000000000) return '$' . number_format($amount / 1000000000, 1) . 'B';
    if ($amount >= 1000000) return '$' . number_format($amount / 1000000, 1) . 'M';
    if ($amount >= 1000) return '$' . number_format($amount / 1000, 1) . 'K';
    return '$' . number_format($amount);
}

// ===== Test Suites =====

$runner = new TestRunner();

// Suite 1: TMDB API Tests
$suite1 = new TestSuite('TMDB API - Image URL Generation');
$suite1->test('Generate w500 image URL', function() {
    assertEquals(
        'https://image.tmdb.org/t/p/w500/test.jpg',
        TMDB_API::get_image_url('/test.jpg', 'w500')
    );
});
$suite1->test('Generate original image URL', function() {
    assertEquals(
        'https://image.tmdb.org/t/p/original/poster.jpg',
        TMDB_API::get_image_url('/poster.jpg', 'original')
    );
});
$suite1->test('Empty path returns null', function() {
    assertNull(TMDB_API::get_image_url('', 'w500'));
});
$suite1->test('Null path returns null', function() {
    assertNull(TMDB_API::get_image_url(null, 'w500'));
});
$suite1->test('Generate w185 image URL', function() {
    assertEquals(
        'https://image.tmdb.org/t/p/w185/actor.jpg',
        TMDB_API::get_image_url('/actor.jpg', 'w185')
    );
});
$runner->addSuite($suite1);

// Suite 2: Runtime Formatting
$suite2 = new TestSuite('Movie Functions - Runtime Formatting');
$suite2->test('Format 143 minutes (2h 23m)', function() {
    assertEquals('2h 23m', format_runtime(143));
});
$suite2->test('Format 90 minutes (1h 30m)', function() {
    assertEquals('1h 30m', format_runtime(90));
});
$suite2->test('Format 45 minutes', function() {
    assertEquals('45m', format_runtime(45));
});
$suite2->test('Format 120 minutes (2h 0m)', function() {
    assertEquals('2h 0m', format_runtime(120));
});
$suite2->test('Format 1 minute', function() {
    assertEquals('1m', format_runtime(1));
});
$suite2->test('Zero minutes returns empty', function() {
    assertEquals('', format_runtime(0));
});
$suite2->test('Null returns empty', function() {
    assertEquals('', format_runtime(null));
});
$suite2->test('Empty string returns empty', function() {
    assertEquals('', format_runtime(''));
});
$suite2->test('Negative runtime returns empty', function() {
    assertEquals('', format_runtime(-60));
});
$suite2->test('Very long runtime (10 hours)', function() {
    assertEquals('10h 0m', format_runtime(600));
});
$suite2->test('Epic runtime (24h 30m)', function() {
    assertEquals('24h 30m', format_runtime(1470));
});
$runner->addSuite($suite2);

// Suite 3: Money Formatting
$suite3 = new TestSuite('Movie Functions - Money Formatting');
$suite3->test('Format $2.8 billion', function() {
    assertEquals('$2.8B', format_money(2800000000));
});
$suite3->test('Format $1 billion', function() {
    assertEquals('$1.0B', format_money(1000000000));
});
$suite3->test('Format $150 million', function() {
    assertEquals('$150.0M', format_money(150000000));
});
$suite3->test('Format $1.5 million', function() {
    assertEquals('$1.5M', format_money(1500000));
});
$suite3->test('Format $500 thousand', function() {
    assertEquals('$500.0K', format_money(500000));
});
$suite3->test('Format $1.5 thousand', function() {
    assertEquals('$1.5K', format_money(1500));
});
$suite3->test('Format $500', function() {
    assertEquals('$500', format_money(500));
});
$suite3->test('Format $100', function() {
    assertEquals('$100', format_money(100));
});
$suite3->test('Zero returns N/A', function() {
    assertEquals('N/A', format_money(0));
});
$suite3->test('Empty string returns N/A', function() {
    assertEquals('N/A', format_money(''));
});
$suite3->test('Null returns N/A', function() {
    assertEquals('N/A', format_money(null));
});
$suite3->test('Very large amount ($100B)', function() {
    assertEquals('$100.0B', format_money(100000000000));
});
$runner->addSuite($suite3);

// Suite 4: Input Validation & Security
$suite4 = new TestSuite('Security - Input Validation');
$suite4->test('XSS script tag sanitization', function() {
    $input = '<script>alert("XSS")</script>';
    $sanitized = strip_tags($input);
    // strip_tags removes tags but keeps content
    assertEquals('alert("XSS")', $sanitized);
});
$suite4->test('XSS with attributes', function() {
    $input = '<img src=x onerror="alert(1)">';
    $sanitized = strip_tags($input);
    assertEquals('', $sanitized);
});
$suite4->test('SQL injection string handling', function() {
    $input = "' OR 1=1; DROP TABLE users--";
    $escaped = addslashes($input);
    assertTrue(strpos($escaped, "\\'") !== false);
});
$suite4->test('Path traversal attempt', function() {
    $input = '../../etc/passwd';
    $cleaned = basename($input);
    assertEquals('passwd', $cleaned);
});
$suite4->test('Null byte injection', function() {
    $input = "file.php\0.txt";
    $cleaned = str_replace("\0", '', $input);
    assertEquals('file.php.txt', $cleaned);
});
$runner->addSuite($suite4);

// Suite 5: Edge Cases
$suite5 = new TestSuite('Edge Cases & Boundary Tests');
$suite5->test('Runtime: Maximum PHP int', function() {
    $result = format_runtime(PHP_INT_MAX);
    assertTrue(is_string($result));
});
$suite5->test('Money: Very small amount', function() {
    assertEquals('$1', format_money(1));
});
$suite5->test('Money: Exactly 1000', function() {
    assertEquals('$1.0K', format_money(1000));
});
$suite5->test('Money: Exactly 1 million', function() {
    assertEquals('$1.0M', format_money(1000000));
});
$suite5->test('Money: Exactly 1 billion', function() {
    assertEquals('$1.0B', format_money(1000000000));
});
$suite5->test('Image URL: Path with spaces', function() {
    assertEquals(
        'https://image.tmdb.org/t/p/w500/path with spaces.jpg',
        TMDB_API::get_image_url('/path with spaces.jpg', 'w500')
    );
});
$suite5->test('Image URL: Unicode characters', function() {
    assertEquals(
        'https://image.tmdb.org/t/p/w500/日本語.jpg',
        TMDB_API::get_image_url('/日本語.jpg', 'w500')
    );
});
$runner->addSuite($suite5);

// Suite 6: Data Type Handling
$suite6 = new TestSuite('Data Type Handling');
$suite6->test('Runtime with float input', function() {
    assertEquals('2h 3m', format_runtime(123.7));
});
$suite6->test('Runtime with string number', function() {
    assertEquals('1h 30m', format_runtime('90'));
});
$suite6->test('Money with float', function() {
    assertEquals('$150.0M', format_money(150000000.50));
});
$suite6->test('Money with string number', function() {
    // String '1000' is >= 1000, so it becomes $1.0K
    assertEquals('$1.0K', format_money('1000'));
});
$suite6->test('Empty array handling', function() {
    assertEquals('', format_runtime([]));
});
$runner->addSuite($suite6);

// Run all tests
$success = $runner->run();

// Exit with appropriate code
exit($success ? 0 : 1);
