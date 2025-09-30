# Custom Endpoints Feature

## Overview

The Solana Integration module now supports adding custom RPC endpoints in addition to the predefined Solana network endpoints (mainnet, devnet, testnet).

## Features

### 1. **Default Official Endpoints**
The module is installed with three official Solana endpoints:
- **Mainnet Beta** - `https://api.mainnet-beta.solana.com` (enabled by default)
- **Devnet** - `https://api.devnet.solana.com` (enabled by default)
- **Testnet** - `https://api.testnet.solana.com` (disabled by default)

These endpoints are provided for convenience during installation but are treated the same as any custom endpoint you add - they can be fully edited or deleted.

### 2. **Adding Custom Endpoints**
Administrators can add unlimited custom RPC endpoints through the settings form.

#### Adding a Custom Endpoint

1. Navigate to `/admin/config/services/solana-integration`
2. Scroll to the "Add Custom Endpoint" section
3. Fill in the following fields:
   - **Endpoint machine name**: A unique machine-readable identifier (lowercase, numbers, underscores)
   - **Endpoint name**: A human-readable name for the endpoint
   - **Endpoint URL**: The JSON-RPC endpoint URL
   - **Enable this endpoint**: Checkbox to enable the endpoint immediately
4. Click "Save configuration"

#### Managing Endpoints

All endpoints (including the default Solana ones) appear in the "Manage Endpoints" section.

Each endpoint can be:
- **Renamed**: Edit the "Name" field
- **URL Updated**: Edit the "URL" field
- **Deleted**: Check the "Delete this endpoint" checkbox and save

**Note**: You can delete any endpoint, including the default Solana endpoints. Just make sure to keep at least one endpoint enabled.

### 3. **Endpoint Selection**

#### Enabled Endpoints
Use the checkboxes in the "RPC Endpoints Configuration" section to enable/disable endpoints. At least one endpoint must be enabled at all times.

#### Default Endpoint
Select which enabled endpoint should be used by default for JSON-RPC requests. The default endpoint must be an enabled endpoint.

## Configuration Structure

The configuration is stored in `solana_integration.settings` with the following structure:

```yaml
endpoints:
  mainnet:
    name: 'Mainnet Beta'
    url: 'https://api.mainnet-beta.solana.com'
    enabled: true
  devnet:
    name: 'Devnet'
    url: 'https://api.devnet.solana.com'
    enabled: true
  testnet:
    name: 'Testnet'
    url: 'https://api.testnet.solana.com'
    enabled: false
  my_custom_endpoint:
    name: 'My Custom RPC'
    url: 'https://my-custom-rpc.example.com'
    enabled: true
default_endpoint: 'mainnet'
request_timeout: 5
```

## Usage in Code

The `SolanaClient` service automatically uses the configured default endpoint:

```php
// Inject the service
$client = \Drupal::service('solana_integration.client');

// Make RPC calls - automatically uses the default endpoint
$result = $client->rpc('getHealth');
```

## Validation Rules

1. **At least one endpoint must be enabled** - The system requires at least one active endpoint
2. **Default endpoint must be enabled** - You cannot set a disabled endpoint as default
3. **Custom endpoint fields are required** - When adding a custom endpoint, all fields (key, name, URL) must be filled
4. **Unique machine names** - Custom endpoint machine names must be unique

## Common Use Cases

### Private RPC Nodes
Add your own private Solana RPC nodes for better performance and reliability:

```
Machine name: my_private_node
Name: My Private Node
URL: https://my-node.example.com:8899
```

### Third-Party RPC Providers
Add premium RPC endpoints from providers like:
- QuickNode
- Alchemy
- GenesysGo
- Triton

### Local Development
Add local Solana test validator endpoints:

```
Machine name: local_validator
Name: Local Test Validator
URL: http://localhost:8899
```

## Best Practices

1. **Use descriptive names** - Make it clear what each endpoint is for
2. **Test endpoints before enabling** - Verify the URL is accessible
3. **Monitor performance** - Different endpoints may have varying latency
4. **Use private endpoints for production** - Public endpoints may have rate limits
5. **Keep testnet disabled in production** - Only enable networks you actually use

## Troubleshooting

### Endpoint Not Working
- Verify the URL is correct and accessible
- Check if the endpoint requires authentication (API keys)
- Ensure the endpoint supports JSON-RPC 2.0

### Cannot Delete Endpoint
- Ensure at least one endpoint remains after deletion
- The system requires at least one enabled endpoint to function

### Default Endpoint Error
- Ensure the endpoint you're trying to set as default is enabled
- At least one endpoint must remain enabled

## Technical Details

### Form Elements
- Endpoint machine names use Drupal's `machine_name` form element
- URL validation is handled by Drupal's `url` form type
- All endpoints are stored identically in configuration
- Official Solana endpoints are provided as defaults during installation but can be modified or removed

### Service Integration
The `SolanaClient` service includes fallback logic:
1. Try to use the configured default endpoint
2. Fall back to the first enabled endpoint if default is unavailable
3. Ultimate fallback to mainnet URL if all else fails

This ensures the service remains functional even if configuration becomes inconsistent.