# AYUT API Response Status Codes

## Overview
This document lists all API response status codes returned by the AYUT slot provider API.

## Response Format
All AYUT API responses follow this structure:
```json
{
  "code": 0,
  "msg": "Success description",
  "payload": {...}
}
```

## Status Codes

| Code  | Description                                                    | Category        |
|-------|----------------------------------------------------------------|-----------------|
| 0     | Success                                                        | Success         |
| 10002 | Agency not exist                                               | Agency Error    |
| 10004 | Payload error                                                  | Request Error   |
| 10005 | System error                                                   | System Error    |
| 10008 | The game does not exist                                        | Game Error      |
| 10011 | Player currencies do not match                                 | Currency Error  |
| 10012 | Player name already exists, please change player name          | Player Error    |
| 10013 | Currency is not supported                                      | Currency Error  |
| 10014 | PlayerName is incorrect                                        | Player Error    |
| 10015 | Player account, limited to a-z and 0-9                         | Player Error    |
| 10016 | The account has been frozen. Please contact the administrator  | Player Error    |
| 10017 | Manufacturer does not exist                                    | Provider Error  |
| 10018 | This line does not support the current currency                | Currency Error  |
| 10020 | The carrier does not configure a currency                      | Currency Error  |
| 10022 | Incorrect parameters                                           | Request Error   |
| 10023 | The player name must be at least 3 characters long             | Player Error    |
| 10024 | Wallet mode does not match                                     | Wallet Error    |
| 10025 | Insufficient wallet balance                                    | Wallet Error    |
| 10026 | Transfer failed                                                | Transfer Error  |
| 10027 | The transfer order already exists                              | Transfer Error  |
| 10028 | Start and end date cannot be empty                             | Query Error     |
| 10029 | The start and end dates must be the same day                   | Query Error     |
| 10030 | Too many requests, please try again later                      | Rate Limit      |
| 10031 | Only data within the last 60 days can be queried               | Query Error     |
| 10032 | End date must be greater than start date                       | Query Error     |
| 10033 | home_url cannot contain ?                                      | Request Error   |
| 10034 | System Scheduled Maintenance                                   | System Error    |

## Error Handling Guide

### Success Response (Code 0)
```php
if ($response['code'] === 0) {
    $payload = $response['payload'];
    // Process successful response
}
```

### Agency Errors (10002)
**Cause**: Invalid or non-existent agency_uid
**Solution**: 
- Verify agency_uid in provider configuration
- Contact AYUT support to confirm account status

### Request Errors (10004, 10022, 10033)
**Cause**: Invalid request format or parameters
**Solution**:
- Verify payload encryption is correct
- Check all required parameters are included
- Validate parameter formats
- Ensure home_url doesn't contain query parameters

### System Errors (10005, 10034)
**Cause**: Provider system issues or maintenance
**Solution**:
- Retry request after delay
- Check AYUT status page
- Notify administrators if persistent

### Game Errors (10008)
**Cause**: Invalid game_uid
**Solution**:
- Verify game exists in synced games list
- Sync games from provider if outdated

### Currency Errors (10011, 10013, 10018, 10020)
**Cause**: Currency mismatch or unsupported currency
**Solution**:
- Verify player currency matches provider supported currencies
- Check provider supports PHP currency
- Ensure currency_code is correct in request

### Player Errors (10012, 10014, 10015, 10016, 10023)
**Cause**: Invalid player account or account issues
**Solution**:
- Generate valid player account: prefix + alphanumeric (4-20 chars, min 3)
- Check player isn't frozen/blocked
- Use lowercase letters and numbers only

### Wallet Errors (10024, 10025)
**Cause**: Wallet mode mismatch or insufficient balance
**Solution**:
- Verify wallet_mode matches provider configuration
- Check player has sufficient balance before game launch
- Handle insufficient balance gracefully in UI

### Transfer Errors (10026, 10027)
**Cause**: Transfer mode specific errors
**Solution**:
- Generate unique transfer_id per transaction
- Implement retry logic with exponential backoff
- Log transfer failures for investigation

### Query Errors (10028, 10029, 10031, 10032)
**Cause**: Invalid date parameters for transaction queries
**Solution**:
- Validate date range is within 60 days
- Ensure end date > start date
- For daily queries, use same day for start and end

### Rate Limiting (10030)
**Cause**: Too many requests in short time
**Solution**:
- Implement exponential backoff retry strategy
- Add rate limiting on our side
- Cache responses where appropriate

## Implementation Example

```php
/**
 * Handle AYUT API response
 */
public function handleAyutResponse(array $response): array
{
    $code = $response['code'] ?? null;
    $msg = $response['msg'] ?? 'Unknown error';
    
    return match($code) {
        0 => [
            'success' => true,
            'data' => $response['payload'] ?? []
        ],
        
        // Agency errors
        10002 => throw new \Exception("Invalid agency configuration. Please contact support."),
        
        // Request errors
        10004, 10022 => throw new \Exception("Invalid request: {$msg}"),
        
        // System errors
        10005, 10034 => throw new \Exception("Provider system error. Please try again later."),
        
        // Game errors
        10008 => throw new \Exception("Game not found. Please refresh game list."),
        
        // Currency errors
        10011, 10013, 10018, 10020 => throw new \Exception("Currency not supported: {$msg}"),
        
        // Player errors
        10012 => throw new \Exception("Player account already exists."),
        10014, 10015, 10023 => throw new \Exception("Invalid player account: {$msg}"),
        10016 => throw new \Exception("Account frozen. Contact administrator."),
        
        // Wallet errors
        10024 => throw new \Exception("Wallet mode mismatch."),
        10025 => throw new \Exception("Insufficient balance."),
        
        // Transfer errors
        10026, 10027 => throw new \Exception("Transfer failed: {$msg}"),
        
        // Query errors
        10028, 10029, 10031, 10032 => throw new \Exception("Invalid query parameters: {$msg}"),
        
        // Rate limiting
        10030 => throw new \Exception("Too many requests. Please wait."),
        
        default => throw new \Exception("Unknown error (code: {$code}): {$msg}")
    };
}
```

## User-Friendly Error Messages

Map technical errors to user-friendly messages:

```php
const USER_MESSAGES = [
    10002 => 'Service temporarily unavailable. Please contact support.',
    10004 => 'An error occurred. Please try again.',
    10005 => 'Service temporarily unavailable. Please try again later.',
    10008 => 'This game is currently unavailable.',
    10011 => 'Your account currency is not supported for this game.',
    10012 => 'Unable to create game account. Please contact support.',
    10013 => 'Currency not supported.',
    10014 => 'Invalid account format.',
    10015 => 'Invalid account format. Use letters and numbers only.',
    10016 => 'Your account has been suspended. Please contact support.',
    10018 => 'This game does not support your currency.',
    10020 => 'Currency configuration error. Contact support.',
    10022 => 'Invalid request. Please try again.',
    10023 => 'Account name too short.',
    10024 => 'Game mode not available.',
    10025 => 'Insufficient balance. Please deposit funds.',
    10026 => 'Transaction failed. Please try again.',
    10027 => 'Duplicate transaction. Please refresh.',
    10028 => 'Invalid date range.',
    10029 => 'Date range must be within same day.',
    10030 => 'Too many requests. Please wait a moment.',
    10031 => 'Can only query last 60 days.',
    10032 => 'Invalid date range.',
    10033 => 'Invalid URL format.',
    10034 => 'System maintenance in progress. Please try again later.',
];
```

## Logging Recommendations

Log all non-success responses with context:

```php
if ($response['code'] !== 0) {
    Log::warning('AYUT API Error', [
        'code' => $response['code'],
        'message' => $response['msg'],
        'user_id' => $userId,
        'game_id' => $gameId,
        'endpoint' => $endpoint,
        'request_payload' => $payloadData,
    ]);
}
```

## Monitoring Alerts

Set up alerts for:
- **10002**: Agency configuration issues (critical)
- **10005, 10034**: System errors (high frequency = provider issues)
- **10016**: Frozen accounts (investigate abuse)
- **10025**: Insufficient balance (UX improvement opportunity)
- **10030**: Rate limiting (adjust request patterns)

---

**Last Updated**: December 26, 2025  
**Source**: AYUT Game API Documentation  
**Related**: [AYUT_API_IMPLEMENTATION.md](./AYUT_API_IMPLEMENTATION.md)
