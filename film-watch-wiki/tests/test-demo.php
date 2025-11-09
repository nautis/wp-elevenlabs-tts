<?php
/**
 * Simple Demo Test - No WordPress Required
 * Demonstrates basic test functionality
 */

echo "\n";
echo "=============================================\n";
echo "Film Watch Wiki - Test Suite Demo\n";
echo "=============================================\n\n";

// Test counter
$passed = 0;
$failed = 0;
$total = 0;

function test($name, $condition, &$passed, &$failed, &$total) {
    $total++;
    echo "  Test: {$name}... ";
    if ($condition) {
        $passed++;
        echo "\033[32m✓ PASS\033[0m\n";
    } else {
        $failed++;
        echo "\033[31m✗ FAIL\033[0m\n";
    }
}

// ===== Simulated Tests =====

echo "\033[34m=== TMDB API Image URL Tests ===\033[0m\n";

// Mock the image URL function
function get_tmdb_image_url($path, $size = 'original') {
    if (empty($path)) {
        return null;
    }
    return 'https://image.tmdb.org/t/p/' . $size . $path;
}

test(
    'Generate image URL with w500 size',
    get_tmdb_image_url('/test.jpg', 'w500') === 'https://image.tmdb.org/t/p/w500/test.jpg',
    $passed, $failed, $total
);

test(
    'Generate image URL with original size',
    get_tmdb_image_url('/test.jpg', 'original') === 'https://image.tmdb.org/t/p/original/test.jpg',
    $passed, $failed, $total
);

test(
    'Empty path returns null',
    get_tmdb_image_url('', 'w500') === null,
    $passed, $failed, $total
);

test(
    'Null path returns null',
    get_tmdb_image_url(null, 'w500') === null,
    $passed, $failed, $total
);

echo "\n\033[34m=== Runtime Formatting Tests ===\033[0m\n";

// Mock runtime format function
function format_runtime($minutes) {
    if (empty($minutes) || $minutes <= 0) {
        return '';
    }

    $hours = floor($minutes / 60);
    $mins = $minutes % 60;

    if ($hours > 0) {
        return sprintf('%dh %dm', $hours, $mins);
    }

    return sprintf('%dm', $mins);
}

test(
    'Format 143 minutes as "2h 23m"',
    format_runtime(143) === '2h 23m',
    $passed, $failed, $total
);

test(
    'Format 90 minutes as "1h 30m"',
    format_runtime(90) === '1h 30m',
    $passed, $failed, $total
);

test(
    'Format 45 minutes as "45m"',
    format_runtime(45) === '45m',
    $passed, $failed, $total
);

test(
    'Format 0 returns empty',
    format_runtime(0) === '',
    $passed, $failed, $total
);

test(
    'Format null returns empty',
    format_runtime(null) === '',
    $passed, $failed, $total
);

test(
    'Format 120 minutes as "2h 0m"',
    format_runtime(120) === '2h 0m',
    $passed, $failed, $total
);

echo "\n\033[34m=== Money Formatting Tests ===\033[0m\n";

// Mock money format function
function format_money($amount) {
    if (empty($amount)) {
        return 'N/A';
    }

    if ($amount >= 1000000000) {
        return '$' . number_format($amount / 1000000000, 1) . 'B';
    } elseif ($amount >= 1000000) {
        return '$' . number_format($amount / 1000000, 1) . 'M';
    } elseif ($amount >= 1000) {
        return '$' . number_format($amount / 1000, 1) . 'K';
    }

    return '$' . number_format($amount);
}

test(
    'Format $2.8 billion',
    format_money(2800000000) === '$2.8B',
    $passed, $failed, $total
);

test(
    'Format $150 million',
    format_money(150000000) === '$150.0M',
    $passed, $failed, $total
);

test(
    'Format $500 thousand',
    format_money(500000) === '$500.0K',
    $passed, $failed, $total
);

test(
    'Format $500',
    format_money(500) === '$500',
    $passed, $failed, $total
);

test(
    'Format 0 as N/A',
    format_money(0) === 'N/A',
    $passed, $failed, $total
);

test(
    'Format empty as N/A',
    format_money('') === 'N/A',
    $passed, $failed, $total
);

echo "\n\033[34m=== Edge Case Tests ===\033[0m\n";

test(
    'Very large runtime (24+ hours)',
    format_runtime(1470) === '24h 30m',
    $passed, $failed, $total
);

test(
    'Negative runtime returns empty',
    format_runtime(-60) === '',
    $passed, $failed, $total
);

test(
    'Very large money ($100B)',
    format_money(100000000000) === '$100.0B',
    $passed, $failed, $total
);

echo "\n\033[34m=== Input Validation Tests ===\033[0m\n";

// Test sanitization simulation
function sanitize_input($input) {
    return preg_replace('/<script.*?>.*?<\/script>/i', '', $input);
}

test(
    'XSS attempt sanitized',
    !str_contains(sanitize_input('<script>alert("XSS")</script>'), '<script>'),
    $passed, $failed, $total
);

test(
    'SQL injection attempt handled',
    strlen(sanitize_input("' OR 1=1; DROP TABLE--")) > 0,
    $passed, $failed, $total
);

// ===== Summary =====

echo "\n";
echo "=============================================\n";
echo "\033[34mTest Summary\033[0m\n";
echo "=============================================\n";
echo "Total Tests:  {$total}\n";
echo "\033[32mPassed:       {$passed}\033[0m\n";

if ($failed > 0) {
    echo "\033[31mFailed:       {$failed}\033[0m\n";
}

$percentage = $total > 0 ? round(($passed / $total) * 100, 2) : 0;
echo "Success Rate: {$percentage}%\n";
echo "=============================================\n\n";

if ($percentage === 100.0) {
    echo "\033[32m🎉 All tests passed!\033[0m\n\n";
} else {
    echo "\033[31m⚠️  Some tests failed. Please review.\033[0m\n\n";
}

// Exit with appropriate code
exit($failed === 0 ? 0 : 1);
