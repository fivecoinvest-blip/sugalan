#!/bin/bash

# Sugalan Casino API Test Script
# Tests all major endpoints to verify functionality

BASE_URL="http://localhost:8000/api"
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo "========================================="
echo "  Sugalan Casino API Test Suite"
echo "========================================="
echo ""

# Function to test endpoint
test_endpoint() {
    local method=$1
    local endpoint=$2
    local data=$3
    local token=$4
    local description=$5
    
    echo -n "Testing: $description... "
    
    if [ -z "$token" ]; then
        response=$(curl -s -X $method "$BASE_URL$endpoint" \
            -H "Content-Type: application/json" \
            -d "$data" \
            -w "\n%{http_code}")
    else
        response=$(curl -s -X $method "$BASE_URL$endpoint" \
            -H "Content-Type: application/json" \
            -H "Authorization: Bearer $token" \
            -d "$data" \
            -w "\n%{http_code}")
    fi
    
    http_code=$(echo "$response" | tail -n1)
    body=$(echo "$response" | sed '$d')
    
    if [ "$http_code" -ge 200 ] && [ "$http_code" -lt 300 ]; then
        echo -e "${GREEN}✓ PASS${NC} ($http_code)"
    else
        echo -e "${RED}✗ FAIL${NC} ($http_code)"
        echo "Response: $body"
    fi
}

echo "=== Testing Admin Authentication ==="
echo ""

# Admin Login
admin_response=$(curl -s -X POST "$BASE_URL/admin/auth/login" \
    -H "Content-Type: application/json" \
    -d '{
        "email": "admin@sugalan.com",
        "password": "Admin123!@#"
    }')

admin_token=$(echo $admin_response | grep -o '"access_token":"[^"]*' | cut -d'"' -f4)

if [ -z "$admin_token" ]; then
    echo -e "${RED}✗ Admin login failed${NC}"
    echo "Response: $admin_response"
    exit 1
else
    echo -e "${GREEN}✓ Admin login successful${NC}"
    echo "Token: ${admin_token:0:20}..."
fi

echo ""

# Test Admin Endpoints
test_endpoint "GET" "/admin/auth/me" "" "$admin_token" "Get admin profile"
test_endpoint "GET" "/admin/dashboard/overview?period=today" "" "$admin_token" "Get dashboard overview"
test_endpoint "GET" "/admin/dashboard/activity" "" "$admin_token" "Get recent activity"
test_endpoint "GET" "/admin/users?per_page=10" "" "$admin_token" "Get users list"
test_endpoint "GET" "/admin/deposits/pending" "" "$admin_token" "Get pending deposits"
test_endpoint "GET" "/admin/withdrawals/pending" "" "$admin_token" "Get pending withdrawals"
test_endpoint "GET" "/admin/deposits/stats?period=today" "" "$admin_token" "Get deposit stats"
test_endpoint "GET" "/admin/withdrawals/stats?period=today" "" "$admin_token" "Get withdrawal stats"

echo ""
echo "=== Testing User Authentication ==="
echo ""

# Create Guest Account
guest_response=$(curl -s -X POST "$BASE_URL/auth/guest" \
    -H "Content-Type: application/json" \
    -d '{}')

user_token=$(echo $guest_response | grep -o '"access_token":"[^"]*' | cut -d'"' -f4)

if [ -z "$user_token" ]; then
    echo -e "${RED}✗ Guest account creation failed${NC}"
    echo "Response: $guest_response"
else
    echo -e "${GREEN}✓ Guest account created${NC}"
    echo "Token: ${user_token:0:20}..."
fi

echo ""

# Test User Endpoints
test_endpoint "GET" "/auth/me" "" "$user_token" "Get user profile"
test_endpoint "GET" "/wallet/balance" "" "$user_token" "Get wallet balance"
test_endpoint "GET" "/wallet/transactions" "" "$user_token" "Get transactions"
test_endpoint "GET" "/payments/gcash-accounts" "" "$user_token" "Get GCash accounts"
test_endpoint "GET" "/payments/deposits" "" "$user_token" "Get deposit history"
test_endpoint "GET" "/payments/withdrawals" "" "$user_token" "Get withdrawal history"
test_endpoint "GET" "/payments/stats" "" "$user_token" "Get payment stats"
test_endpoint "GET" "/notifications" "" "$user_token" "Get notifications"
test_endpoint "GET" "/notifications/unread-count" "" "$user_token" "Get unread count"

echo ""
echo "=== Testing Game Endpoints ==="
echo ""

# Note: Games will fail with insufficient balance, but should return proper error
test_endpoint "POST" "/games/dice/play" '{"bet_amount":10,"target":50.5,"prediction":"over"}' "$user_token" "Dice game"
test_endpoint "POST" "/games/plinko/play" '{"bet_amount":10,"risk_level":"medium"}' "$user_token" "Plinko game"
test_endpoint "POST" "/games/keno/play" '{"bet_amount":10,"selected_numbers":[1,5,10,15,20]}' "$user_token" "Keno game"
test_endpoint "GET" "/games/wheel/config" "" "" "Get wheel config (public)"
test_endpoint "GET" "/games/crash/current" "" "" "Get crash round (public)"

echo ""
echo "=== Testing Public Endpoints ==="
echo ""

test_endpoint "GET" "/games/wheel/config" "" "" "Wheel config"
test_endpoint "GET" "/games/crash/current" "" "" "Crash current round"

echo ""
echo "========================================="
echo "  Test Suite Complete"
echo "========================================="
echo ""
echo "Admin Token: ${admin_token:0:30}..."
echo "User Token: ${user_token:0:30}..."
echo ""
