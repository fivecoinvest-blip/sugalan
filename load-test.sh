#!/bin/bash

# =============================================================================
# Slot Integration Load Testing Script
# =============================================================================
# This script simulates realistic user behavior patterns to test system
# performance under various load conditions.
#
# Requirements:
# - Apache Bench (ab) - sudo apt install apache2-utils
# - curl
# - jq - sudo apt install jq
#
# Usage:
#   ./load-test.sh [test_type] [concurrent_users]
#
# Examples:
#   ./load-test.sh light 10       # Light load: 10 concurrent users
#   ./load-test.sh moderate 50    # Moderate load: 50 concurrent users
#   ./load-test.sh heavy 100      # Heavy load: 100 concurrent users
#   ./load-test.sh stress 500     # Stress test: 500 concurrent users
# =============================================================================

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
API_BASE_URL="${API_BASE_URL:-http://localhost:8000/api}"
TEST_TYPE="${1:-light}"
CONCURRENT_USERS="${2:-10}"
TOTAL_REQUESTS=$((CONCURRENT_USERS * 10))

# Test credentials (should match your test user)
TEST_EMAIL="test@example.com"
TEST_PASSWORD="password"

echo -e "${BLUE}╔════════════════════════════════════════════════════════════╗${NC}"
echo -e "${BLUE}║         Slot Integration Load Testing Suite               ║${NC}"
echo -e "${BLUE}╚════════════════════════════════════════════════════════════╝${NC}"
echo ""
echo -e "${YELLOW}Test Configuration:${NC}"
echo -e "  API Base URL: ${GREEN}${API_BASE_URL}${NC}"
echo -e "  Test Type: ${GREEN}${TEST_TYPE}${NC}"
echo -e "  Concurrent Users: ${GREEN}${CONCURRENT_USERS}${NC}"
echo -e "  Total Requests: ${GREEN}${TOTAL_REQUESTS}${NC}"
echo ""

# Check if server is running
echo -e "${BLUE}[1/5]${NC} Checking if server is running..."
if ! curl -s "${API_BASE_URL%/api}/health" > /dev/null 2>&1; then
    echo -e "${RED}✗ Server is not running at ${API_BASE_URL}${NC}"
    echo -e "${YELLOW}Start your Laravel server: php artisan serve${NC}"
    exit 1
fi
echo -e "${GREEN}✓ Server is running${NC}"
echo ""

# Get authentication token
echo -e "${BLUE}[2/5]${NC} Authenticating test user..."
AUTH_RESPONSE=$(curl -s -X POST "${API_BASE_URL}/auth/login" \
    -H "Content-Type: application/json" \
    -d "{\"email\":\"${TEST_EMAIL}\",\"password\":\"${TEST_PASSWORD}\"}")

if [ $? -ne 0 ]; then
    echo -e "${RED}✗ Failed to authenticate${NC}"
    exit 1
fi

TOKEN=$(echo "$AUTH_RESPONSE" | jq -r '.token // .data.token // empty')

if [ -z "$TOKEN" ] || [ "$TOKEN" == "null" ]; then
    echo -e "${RED}✗ Failed to get authentication token${NC}"
    echo -e "${YELLOW}Response: ${AUTH_RESPONSE}${NC}"
    exit 1
fi

echo -e "${GREEN}✓ Authentication successful${NC}"
echo ""

# Create temporary files for results
TEMP_DIR=$(mktemp -d)
RESULTS_FILE="${TEMP_DIR}/results.txt"
echo "Test Results" > "$RESULTS_FILE"
echo "============" >> "$RESULTS_FILE"
echo "" >> "$RESULTS_FILE"

# Test 1: Provider List Endpoint
echo -e "${BLUE}[3/5]${NC} Testing Provider List Endpoint..."
echo -e "${YELLOW}  → GET /api/slots/providers${NC}"

ab -n "$TOTAL_REQUESTS" \
   -c "$CONCURRENT_USERS" \
   -H "Authorization: Bearer ${TOKEN}" \
   -g "${TEMP_DIR}/provider_list.tsv" \
   "${API_BASE_URL}/slots/providers" 2>&1 | tee "${TEMP_DIR}/provider_list.txt" > /dev/null

# Extract key metrics
PROVIDER_TIME=$(grep "Time per request:" "${TEMP_DIR}/provider_list.txt" | head -1 | awk '{print $4}')
PROVIDER_RPS=$(grep "Requests per second:" "${TEMP_DIR}/provider_list.txt" | awk '{print $4}')
PROVIDER_FAILED=$(grep "Failed requests:" "${TEMP_DIR}/provider_list.txt" | awk '{print $3}')

echo -e "${GREEN}✓ Completed${NC}"
echo -e "  Avg Response Time: ${PROVIDER_TIME}ms"
echo -e "  Requests/sec: ${PROVIDER_RPS}"
echo -e "  Failed Requests: ${PROVIDER_FAILED}"
echo ""

echo "Provider List Endpoint" >> "$RESULTS_FILE"
echo "---------------------" >> "$RESULTS_FILE"
echo "Average Response Time: ${PROVIDER_TIME}ms" >> "$RESULTS_FILE"
echo "Requests per Second: ${PROVIDER_RPS}" >> "$RESULTS_FILE"
echo "Failed Requests: ${PROVIDER_FAILED}" >> "$RESULTS_FILE"
echo "" >> "$RESULTS_FILE"

# Test 2: Game List Endpoint
echo -e "${BLUE}[4/5]${NC} Testing Game List Endpoint..."
echo -e "${YELLOW}  → GET /api/slots/games${NC}"

ab -n "$TOTAL_REQUESTS" \
   -c "$CONCURRENT_USERS" \
   -H "Authorization: Bearer ${TOKEN}" \
   -g "${TEMP_DIR}/game_list.tsv" \
   "${API_BASE_URL}/slots/games" 2>&1 | tee "${TEMP_DIR}/game_list.txt" > /dev/null

GAME_TIME=$(grep "Time per request:" "${TEMP_DIR}/game_list.txt" | head -1 | awk '{print $4}')
GAME_RPS=$(grep "Requests per second:" "${TEMP_DIR}/game_list.txt" | awk '{print $4}')
GAME_FAILED=$(grep "Failed requests:" "${TEMP_DIR}/game_list.txt" | awk '{print $3}')

echo -e "${GREEN}✓ Completed${NC}"
echo -e "  Avg Response Time: ${GAME_TIME}ms"
echo -e "  Requests/sec: ${GAME_RPS}"
echo -e "  Failed Requests: ${GAME_FAILED}"
echo ""

echo "Game List Endpoint" >> "$RESULTS_FILE"
echo "------------------" >> "$RESULTS_FILE"
echo "Average Response Time: ${GAME_TIME}ms" >> "$RESULTS_FILE"
echo "Requests per Second: ${GAME_RPS}" >> "$RESULTS_FILE"
echo "Failed Requests: ${GAME_FAILED}" >> "$RESULTS_FILE"
echo "" >> "$RESULTS_FILE"

# Test 3: Popular Games Endpoint
echo -e "${BLUE}[5/5]${NC} Testing Popular Games Endpoint..."
echo -e "${YELLOW}  → GET /api/slots/games/popular${NC}"

ab -n "$TOTAL_REQUESTS" \
   -c "$CONCURRENT_USERS" \
   -H "Authorization: Bearer ${TOKEN}" \
   -g "${TEMP_DIR}/popular_games.tsv" \
   "${API_BASE_URL}/slots/games/popular?limit=12" 2>&1 | tee "${TEMP_DIR}/popular_games.txt" > /dev/null

POPULAR_TIME=$(grep "Time per request:" "${TEMP_DIR}/popular_games.txt" | head -1 | awk '{print $4}')
POPULAR_RPS=$(grep "Requests per second:" "${TEMP_DIR}/popular_games.txt" | awk '{print $4}')
POPULAR_FAILED=$(grep "Failed requests:" "${TEMP_DIR}/popular_games.txt" | awk '{print $3}')

echo -e "${GREEN}✓ Completed${NC}"
echo -e "  Avg Response Time: ${POPULAR_TIME}ms"
echo -e "  Requests/sec: ${POPULAR_RPS}"
echo -e "  Failed Requests: ${POPULAR_FAILED}"
echo ""

echo "Popular Games Endpoint" >> "$RESULTS_FILE"
echo "----------------------" >> "$RESULTS_FILE"
echo "Average Response Time: ${POPULAR_TIME}ms" >> "$RESULTS_FILE"
echo "Requests per Second: ${POPULAR_RPS}" >> "$RESULTS_FILE"
echo "Failed Requests: ${POPULAR_FAILED}" >> "$RESULTS_FILE"
echo "" >> "$RESULTS_FILE"

# Summary
echo -e "${BLUE}╔════════════════════════════════════════════════════════════╗${NC}"
echo -e "${BLUE}║                    Test Summary                            ║${NC}"
echo -e "${BLUE}╚════════════════════════════════════════════════════════════╝${NC}"
echo ""

TOTAL_FAILED=$((PROVIDER_FAILED + GAME_FAILED + POPULAR_FAILED))
TOTAL_REQUESTS_SENT=$((TOTAL_REQUESTS * 3))

echo -e "${YELLOW}Overall Results:${NC}"
echo -e "  Total Requests Sent: ${GREEN}${TOTAL_REQUESTS_SENT}${NC}"
echo -e "  Total Failed: ${RED}${TOTAL_FAILED}${NC}"
echo -e "  Success Rate: ${GREEN}$(awk "BEGIN {printf \"%.2f\", (1 - ${TOTAL_FAILED}/${TOTAL_REQUESTS_SENT}) * 100}")%${NC}"
echo ""

echo -e "${YELLOW}Response Time Comparison:${NC}"
printf "  %-25s %10s %15s\n" "Endpoint" "Avg Time" "Req/sec"
printf "  %-25s %10s %15s\n" "-------------------------" "----------" "---------------"
printf "  %-25s %10s %15s\n" "Provider List" "${PROVIDER_TIME}ms" "${PROVIDER_RPS}"
printf "  %-25s %10s %15s\n" "Game List" "${GAME_TIME}ms" "${GAME_RPS}"
printf "  %-25s %10s %15s\n" "Popular Games" "${POPULAR_TIME}ms" "${POPULAR_RPS}"
echo ""

# Performance evaluation
AVG_TIME=$(awk "BEGIN {printf \"%.2f\", (${PROVIDER_TIME} + ${GAME_TIME} + ${POPULAR_TIME}) / 3}")

echo -e "${YELLOW}Performance Rating:${NC}"
if (( $(echo "$AVG_TIME < 100" | bc -l) )); then
    echo -e "  ${GREEN}✓ EXCELLENT${NC} - Average response time: ${AVG_TIME}ms"
    echo -e "  System is performing exceptionally well!"
elif (( $(echo "$AVG_TIME < 200" | bc -l) )); then
    echo -e "  ${GREEN}✓ GOOD${NC} - Average response time: ${AVG_TIME}ms"
    echo -e "  System is performing well."
elif (( $(echo "$AVG_TIME < 500" | bc -l) )); then
    echo -e "  ${YELLOW}⚠ ACCEPTABLE${NC} - Average response time: ${AVG_TIME}ms"
    echo -e "  Consider optimization if load increases."
else
    echo -e "  ${RED}✗ POOR${NC} - Average response time: ${AVG_TIME}ms"
    echo -e "  System needs optimization!"
fi
echo ""

# Recommendations based on test type
echo -e "${YELLOW}Recommendations:${NC}"
if [ "$TEST_TYPE" == "light" ]; then
    echo -e "  • System handles light load (${CONCURRENT_USERS} users) well"
    echo -e "  • Consider running moderate load test next"
elif [ "$TEST_TYPE" == "moderate" ]; then
    echo -e "  • System handles moderate load (${CONCURRENT_USERS} users)"
    if [ "$TOTAL_FAILED" -gt 0 ]; then
        echo -e "  • ${RED}Warning:${NC} Some requests failed. Check logs."
    else
        echo -e "  • Ready for heavier load testing"
    fi
elif [ "$TEST_TYPE" == "heavy" ]; then
    echo -e "  • System under heavy load (${CONCURRENT_USERS} users)"
    if [ "$TOTAL_FAILED" -gt "$((TOTAL_REQUESTS_SENT / 20))" ]; then
        echo -e "  • ${RED}Warning:${NC} >5% failure rate. System may be at capacity."
    else
        echo -e "  • Consider stress testing next"
    fi
elif [ "$TEST_TYPE" == "stress" ]; then
    echo -e "  • Stress test with ${CONCURRENT_USERS} concurrent users"
    if [ "$TOTAL_FAILED" -gt "$((TOTAL_REQUESTS_SENT / 10))" ]; then
        echo -e "  • ${RED}Alert:${NC} >10% failure rate. System at/beyond capacity."
        echo -e "  • Scaling required for this load level."
    else
        echo -e "  • System handles stress test well!"
    fi
fi
echo ""

# Save summary
echo "Overall Summary" >> "$RESULTS_FILE"
echo "===============" >> "$RESULTS_FILE"
echo "Test Type: ${TEST_TYPE}" >> "$RESULTS_FILE"
echo "Concurrent Users: ${CONCURRENT_USERS}" >> "$RESULTS_FILE"
echo "Total Requests: ${TOTAL_REQUESTS_SENT}" >> "$RESULTS_FILE"
echo "Failed Requests: ${TOTAL_FAILED}" >> "$RESULTS_FILE"
echo "Success Rate: $(awk "BEGIN {printf \"%.2f\", (1 - ${TOTAL_FAILED}/${TOTAL_REQUESTS_SENT}) * 100}")%" >> "$RESULTS_FILE"
echo "Average Response Time: ${AVG_TIME}ms" >> "$RESULTS_FILE"
echo "" >> "$RESULTS_FILE"
echo "Test completed at: $(date)" >> "$RESULTS_FILE"

# Save results
OUTPUT_DIR="./storage/load-tests"
mkdir -p "$OUTPUT_DIR"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
OUTPUT_FILE="${OUTPUT_DIR}/load_test_${TEST_TYPE}_${CONCURRENT_USERS}users_${TIMESTAMP}.txt"

cp "$RESULTS_FILE" "$OUTPUT_FILE"
echo -e "${GREEN}Results saved to: ${OUTPUT_FILE}${NC}"
echo ""

# Cleanup
rm -rf "$TEMP_DIR"

echo -e "${BLUE}╔════════════════════════════════════════════════════════════╗${NC}"
echo -e "${BLUE}║                  Load Test Complete!                      ║${NC}"
echo -e "${BLUE}╚════════════════════════════════════════════════════════════╝${NC}"
