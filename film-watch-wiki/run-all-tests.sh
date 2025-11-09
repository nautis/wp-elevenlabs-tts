#!/bin/bash
#
# Comprehensive Test Runner for Film Watch Wiki
# Runs both PHPUnit and Puppeteer tests
#

set -e

echo "======================================"
echo "Film Watch Wiki - Comprehensive Tests"
echo "======================================"
echo ""

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Track results
PHPUNIT_PASSED=0
PUPPETEER_PASSED=0

echo "📋 Test Summary:"
echo "  - PHPUnit tests (sightings, migration, etc.)"
echo "  - Puppeteer visual tests (layout, styling, navigation)"
echo ""

# ====================
# 1. PHPUNIT TESTS
# ====================
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "🧪 Running PHPUnit Tests..."
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""

if php run-tests.php; then
    echo -e "${GREEN}✓ PHPUnit tests PASSED${NC}"
    PHPUNIT_PASSED=1
else
    echo -e "${RED}✗ PHPUnit tests FAILED${NC}"
    PHPUNIT_PASSED=0
fi

echo ""
echo ""

# ====================
# 2. PUPPETEER TESTS
# ====================
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "🎭 Running Puppeteer Visual Tests..."
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""

# Check if node_modules exists
if [ ! -d "tests/visual/node_modules" ]; then
    echo "📦 Installing Puppeteer dependencies..."
    cd tests/visual
    npm install
    cd ../..
    echo ""
fi

# Run Puppeteer tests
cd tests/visual
if npm test; then
    echo -e "${GREEN}✓ Puppeteer tests PASSED${NC}"
    PUPPETEER_PASSED=1
else
    echo -e "${RED}✗ Puppeteer tests FAILED${NC}"
    PUPPETEER_PASSED=0
fi
cd ../..

echo ""
echo ""

# ====================
# FINAL SUMMARY
# ====================
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "📊 TEST RESULTS SUMMARY"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""

if [ $PHPUNIT_PASSED -eq 1 ]; then
    echo -e "  PHPUnit Tests:    ${GREEN}✓ PASSED${NC}"
else
    echo -e "  PHPUnit Tests:    ${RED}✗ FAILED${NC}"
fi

if [ $PUPPETEER_PASSED -eq 1 ]; then
    echo -e "  Puppeteer Tests:  ${GREEN}✓ PASSED${NC}"
else
    echo -e "  Puppeteer Tests:  ${RED}✗ FAILED${NC}"
fi

echo ""

# Overall result
if [ $PHPUNIT_PASSED -eq 1 ] && [ $PUPPETEER_PASSED -eq 1 ]; then
    echo -e "${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo -e "${GREEN}  ALL TESTS PASSED! ✨${NC}"
    echo -e "${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    exit 0
else
    echo -e "${RED}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo -e "${RED}  SOME TESTS FAILED ❌${NC}"
    echo -e "${RED}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    exit 1
fi
