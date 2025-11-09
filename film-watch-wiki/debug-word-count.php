#!/usr/bin/env php
<?php
/**
 * Debug script to test word count logic
 */

// Get biography from stdin (piped from wp post meta get)
$stdin = file_get_contents('php://stdin');
$data = json_decode($stdin, true);

if (empty($data['biography'])) {
    echo "No biography found\n";
    exit(1);
}

$biography = $data['biography'];

echo "Total words in biography: " . str_word_count($biography) . "\n";
echo "Total characters: " . strlen($biography) . "\n";
echo "\n";

// Test the truncation logic
$words_with_positions = str_word_count($biography, 2);
$positions = array_keys($words_with_positions);

echo "Total words found by str_word_count: " . count($words_with_positions) . "\n";

if (count($words_with_positions) >= 200) {
    $word_200_pos = $positions[199]; // 0-indexed
    $word_200_length = strlen($words_with_positions[$word_200_pos]);
    $cutoff = $word_200_pos + $word_200_length;
    $short_bio = substr($biography, 0, $cutoff);

    echo "Position of 200th word: $word_200_pos\n";
    echo "200th word: '" . $words_with_positions[$word_200_pos] . "'\n";
    echo "Length of 200th word: $word_200_length\n";
    echo "Cutoff position: $cutoff\n";
    echo "Short bio length: " . strlen($short_bio) . "\n";
    echo "Words in short bio: " . str_word_count($short_bio) . "\n";
    echo "\n";
    echo "Last 100 chars of short bio:\n";
    echo substr($short_bio, -100) . "\n";
}
