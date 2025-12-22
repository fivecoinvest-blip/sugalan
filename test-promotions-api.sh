#!/bin/bash

echo "======================================"
echo "Testing Promotions API Endpoints"
echo "======================================"
echo ""

# Colors
GREEN='\033[0;32m'
RED='\033[0;31m'
NC='\033[0m' # No Color

# Test 1: Get Active Campaigns (Public)
echo "1. Testing GET /api/promotions/campaigns"
RESPONSE=$(curl -s -w "\n%{http_code}" http://localhost:8000/api/promotions/campaigns)
HTTP_CODE=$(echo "$RESPONSE" | tail -n1)
if [ "$HTTP_CODE" = "200" ]; then
    echo -e "${GREEN}✓ Success${NC} (Status: $HTTP_CODE)"
    echo "$RESPONSE" | head -n -1 | jq '.data[0] | {title, code, type}' 2>/dev/null || echo "Response OK"
else
    echo -e "${RED}✗ Failed${NC} (Status: $HTTP_CODE)"
fi
echo ""

# Test 2: Get Campaign by Code
echo "2. Testing GET /api/promotions/campaigns/code/WELCOME100"
RESPONSE=$(curl -s -w "\n%{http_code}" http://localhost:8000/api/promotions/campaigns/code/WELCOME100)
HTTP_CODE=$(echo "$RESPONSE" | tail -n1)
if [ "$HTTP_CODE" = "200" ]; then
    echo -e "${GREEN}✓ Success${NC} (Status: $HTTP_CODE)"
    echo "$RESPONSE" | head -n -1 | jq '.data | {title, type, percentage}' 2>/dev/null || echo "Response OK"
else
    echo -e "${RED}✗ Failed${NC} (Status: $HTTP_CODE)"
fi
echo ""

# Test 3: Admin - Get All Campaigns (requires auth)
echo "3. Testing GET /api/admin/promotions/campaigns"
echo "(Requires admin authentication - skipping)"
echo ""

# Test 4: Health Check
echo "4. Testing Application Health"
RESPONSE=$(curl -s -w "\n%{http_code}" http://localhost:8000)
HTTP_CODE=$(echo "$RESPONSE" | tail -n1)
if [ "$HTTP_CODE" = "200" ]; then
    echo -e "${GREEN}✓ Application is running${NC} (Status: $HTTP_CODE)"
else
    echo -e "${RED}✗ Application issue${NC} (Status: $HTTP_CODE)"
fi
echo ""

echo "======================================"
echo "Test Summary"
echo "======================================"
echo "Note: Full testing requires authentication tokens"
echo "Run 'php artisan serve' if tests failed"
