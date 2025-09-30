# Balance Check Form - Endpoint Selector Feature

## Overview

The Balance Check Form now includes a collapsible "RPC Endpoint Settings" fieldset that allows users to select which RPC endpoint to use when checking account balances.

## Changes Made

### 1. BalanceCheckForm (`src/Form/BalanceCheckForm.php`)

#### Added Dependencies
- Injected `ConfigFactoryInterface` to access endpoint configuration
- Added `$configFactory` property to store the config factory service

#### Constructor Updates
```php
public function __construct(
  SolanaClient $solana_client, 
  ConfigFactoryInterface $config_factory,  // NEW
  MessengerInterface $messenger
)
```

#### Form Structure
Added a new collapsible fieldset with endpoint selector:

```php
$form['endpoint_settings'] = [
  '#type' => 'details',              // Collapsible fieldset
  '#title' => 'RPC Endpoint Settings',
  '#open' => FALSE,                  // Closed by default
  '#description' => '...',
];

$form['endpoint_settings']['endpoint'] = [
  '#type' => 'select',
  '#title' => 'RPC Endpoint',
  '#options' => $endpoint_options,   // Only enabled endpoints
  '#default_value' => $default_endpoint_key,  // Uses configured default
];
```

#### Submit Handler Updates
- Retrieves selected endpoint from form state
- Passes endpoint URL to `getBalance()` method
- Displays endpoint name and URL in success message

### 2. SolanaClient Service (`src/Service/SolanaClient.php`)

#### Updated `getBalance()` Method
Added optional `$endpoint` parameter to allow overriding the default endpoint:

```php
public function getBalance(string $pubkey, ?string $endpoint = NULL): ?array {
  $endpoint = $endpoint ?? $this->getEndpoint();  // Falls back to default
  // ... rest of implementation
}
```

**Backward Compatible**: Existing code calling `getBalance($pubkey)` continues to work using the default endpoint.

## User Interface

### Form Layout

```
┌─────────────────────────────────────────────────────┐
│ Solana Account Address                              │
│ [____________________________________________]      │
│ Enter the public key of the Solana account...      │
└─────────────────────────────────────────────────────┘

▸ RPC Endpoint Settings
  Select which RPC endpoint to use for this balance check.

┌─────────────────────────────────────────────────────┐
│ [Check Balance]                                      │
└─────────────────────────────────────────────────────┘
```

### When Expanded

```
┌─────────────────────────────────────────────────────┐
│ Solana Account Address                              │
│ [____________________________________________]      │
│ Enter the public key of the Solana account...      │
└─────────────────────────────────────────────────────┘

▾ RPC Endpoint Settings
  Select which RPC endpoint to use for this balance check.
  
  RPC Endpoint:
  [Mainnet Beta (https://api.mainnet-beta.solana.com) ▾]
  
  The RPC endpoint to query. Defaults to the configured 
  default endpoint.

┌─────────────────────────────────────────────────────┐
│ [Check Balance]                                      │
└─────────────────────────────────────────────────────┘
```

## Features

### ✓ Shows Only Enabled Endpoints
- Filters out disabled endpoints automatically
- If no endpoints are enabled, the fieldset is hidden entirely

### ✓ Defaults to Configured Default Endpoint
- Reads `default_endpoint` from configuration
- Pre-selects it in the dropdown

### ✓ Displays Endpoint Details
- Shows both name and URL in the dropdown options
- Example: "Mainnet Beta (https://api.mainnet-beta.solana.com)"

### ✓ Success Message Includes Endpoint Info
- Shows which endpoint was used for the query
- Displays: "RPC Endpoint: Mainnet Beta (https://api.mainnet-beta.solana.com)"

### ✓ User-Friendly Design
- Collapsed by default to avoid cluttering the form
- Users who want to use a different endpoint can expand it
- Advanced users can quickly switch between endpoints for testing

## Use Cases

### 1. Testing Different Endpoints
Users can compare response times or data from different RPC providers:
- Select Mainnet Beta (public)
- Check balance
- Expand settings, select QuickNode (private)
- Check same balance again
- Compare results

### 2. Development Workflow
Developers can switch between networks without changing configuration:
- Use mainnet for production queries
- Switch to devnet for testing with test accounts
- Switch to local validator (localhost:8899) for development

### 3. Troubleshooting
If the default endpoint is having issues:
- User can manually select an alternative endpoint
- Check if the issue is endpoint-specific or account-specific

### 4. Endpoint Performance Comparison
Users can benchmark different RPC providers:
- Check same account with different endpoints
- Compare response times and reliability

## Technical Details

### Dependency Injection
The form now requires three services:
1. `solana_integration.client` - SolanaClient service
2. `config.factory` - ConfigFactoryInterface service
3. `messenger` - MessengerInterface service

### Configuration Reading
```php
$config = $this->configFactory->get('solana_integration.settings');
$endpoints = $config->get('endpoints') ?? [];
$default_endpoint_key = $config->get('default_endpoint') ?? 'mainnet';
```

### Endpoint Filtering
Only enabled endpoints are shown:
```php
foreach ($endpoints as $key => $endpoint) {
  if (!empty($endpoint['enabled'])) {
    $endpoint_options[$key] = $endpoint['name'] . ' (' . $endpoint['url'] . ')';
  }
}
```

### Endpoint Selection
```php
$selected_endpoint_key = $form_state->getValue('endpoint');
$selected_endpoint = $endpoints[$selected_endpoint_key] ?? null;
$endpoint_url = $selected_endpoint['url'];
```

### Service Call
```php
// Pass selected endpoint URL to getBalance method
$balance_array = $this->solanaClient->getBalance($address, $endpoint_url);
```

## Backward Compatibility

✅ **Fully Backward Compatible**

- Existing code calling `SolanaClient::getBalance($pubkey)` continues to work
- The `$endpoint` parameter is optional and defaults to `NULL`
- When `NULL`, the service uses the configured default endpoint
- No breaking changes to the service API

## Example: Custom Endpoint Usage

### Scenario: Testing with Local Validator

1. **Add local endpoint** in settings form:
   - Name: "Local Test Validator"
   - Machine name: local_validator
   - URL: http://localhost:8899
   - Enable: Yes

2. **Use balance check form**:
   - Enter test account address
   - Expand "RPC Endpoint Settings"
   - Select "Local Test Validator (http://localhost:8899)"
   - Click "Check Balance"

3. **Result message**:
   ```
   The balance for account ABC...XYZ is 5.000000000 SOL (5,000,000,000 lamports).
   RPC Endpoint: Local Test Validator (http://localhost:8899)
   ```

## Future Enhancements (Possible)

- **Remember Last Selection**: Store user's last selected endpoint in user settings or session
- **Add Endpoint Health Indicator**: Show green/yellow/red status next to each endpoint
- **Response Time Display**: Show how long the query took
- **Multiple Account Batch Check**: Allow checking multiple accounts with one submission
- **Export Results**: Add button to export balance data as CSV or JSON

## Testing

### Test Scenarios

1. **Default Behavior**
   - Leave fieldset collapsed
   - Submit form
   - Should use configured default endpoint

2. **Select Different Endpoint**
   - Expand fieldset
   - Select devnet
   - Submit form
   - Should use devnet endpoint

3. **All Endpoints Disabled**
   - Disable all endpoints in settings
   - Visit balance check form
   - Fieldset should not appear

4. **Invalid Endpoint Selection**
   - Submit with deleted/disabled endpoint
   - Should show error message

### Drush Commands for Testing

```bash
# View current endpoints
ddev drush config:get solana_integration.settings endpoints

# Change default endpoint
ddev drush config:set solana_integration.settings default_endpoint devnet

# Clear cache
ddev drush cr

# Test the form
ddev launch /balance-check
```

## UI/UX Improvements

### Why Collapsed by Default?
- **Simplicity**: Most users will use the default endpoint
- **Clean Interface**: Reduces visual clutter
- **Progressive Disclosure**: Advanced options available when needed
- **Fast Workflow**: Quick balance checks don't require extra clicks

### Why Show Both Name and URL?
- **Clarity**: Users can verify which endpoint they're selecting
- **Transparency**: Full URL visible for security-conscious users
- **Debugging**: Easy to spot incorrect configurations

### Why Include Endpoint in Success Message?
- **Confirmation**: Users know which endpoint was actually used
- **Debugging**: Helpful when troubleshooting issues
- **Transparency**: Clear audit trail of which RPC was queried