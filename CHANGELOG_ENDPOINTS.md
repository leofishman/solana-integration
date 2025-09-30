# Changelog: Endpoint Management System

## Overview
The Solana Integration module now supports a flexible endpoint management system where users can add, edit, and delete RPC endpoints.

## Changes Made

### 1. Configuration System (`config/`)

#### `config/install/solana_integration.settings.yml`
- Changed from single `rpc_endpoint` string to `endpoints` mapping
- Provides three official Solana endpoints by default:
  - Mainnet Beta (enabled)
  - Devnet (enabled)  
  - Testnet (disabled)
- Added `default_endpoint` key to specify which endpoint to use
- All endpoints have identical structure (name, url, enabled)

#### `config/schema/solana_integration.schema.yml`
- Updated to use `sequence` type for endpoints
- Created reusable `solana_integration.endpoint` type definition
- Removed distinction between official and custom endpoints
- All endpoints stored with: name, url, enabled fields

### 2. Settings Form (`src/Form/SettingsForm.php`)

#### New Features Added:
- **Endpoint Selection Section**: Checkboxes to enable/disable endpoints + dropdown to select default
- **Manage Endpoints Section**: Collapsible fieldsets for each endpoint with:
  - Name field (editable)
  - URL field (editable)
  - Delete checkbox (for all endpoints)
- **Add Custom Endpoint Section**: Form to add new endpoints with:
  - Endpoint name (auto-generates machine name)
  - Endpoint machine name (unique identifier)
  - Endpoint URL (validated)
  - Enable checkbox

#### New Methods:
- `endpointExists($key)`: Check if endpoint machine name already exists
- Enhanced `validateForm()`: Validates endpoint requirements and custom endpoint fields
- Updated `submitForm()`: Handles endpoint updates, deletion, and creation

#### Validation Rules:
- At least one endpoint must be enabled
- Default endpoint must be an enabled endpoint
- Custom endpoint fields (key, name, URL) all required when adding
- Machine names must be unique

### 3. Service Layer (`src/Service/SolanaClient.php`)

#### Updated `getEndpoint()` method:
- Reads from new `endpoints` configuration structure
- Uses `default_endpoint` key to find selected endpoint
- Implements fallback logic:
  1. Use configured default endpoint
  2. Fall back to first enabled endpoint
  3. Ultimate fallback to mainnet URL

### 4. Documentation

#### `CUSTOM_ENDPOINTS.md` (New)
Comprehensive guide covering:
- Feature overview
- How to add/manage endpoints
- Configuration structure
- Usage examples
- Common use cases (private nodes, third-party providers, local dev)
- Best practices
- Troubleshooting
- Technical details

#### `CHANGELOG_ENDPOINTS.md` (This file)
Technical changelog for developers

## Upgrade Path

### For New Installations:
- Module installs with three official Solana endpoints
- Mainnet is set as default
- Ready to use immediately

### For Existing Installations:
If upgrading from a version with single `rpc_endpoint`:

1. Backup your configuration:
   ```bash
   drush cex
   ```

2. The old `rpc_endpoint` configuration will need to be migrated manually to the new format

3. Clear cache after update:
   ```bash
   drush cr
   ```

## Configuration Example

### Before (Old Format):
```yaml
rpc_endpoint: 'https://api.mainnet-beta.solana.com'
request_timeout: 5
```

### After (New Format):
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
  quicknode_custom:
    name: 'QuickNode Premium'
    url: 'https://my-node.quiknode.pro/xxxxx'
    enabled: true
default_endpoint: 'mainnet'
request_timeout: 5
```

## Key Benefits

1. **Flexibility**: Add unlimited custom RPC endpoints
2. **Easy Switching**: Toggle between different endpoints via UI
3. **No Lock-in**: Official endpoints can be edited or removed
4. **Development-Friendly**: Easy to add local test validator endpoints
5. **Production-Ready**: Support for private/premium RPC providers
6. **Failover Support**: Service includes fallback logic for reliability

## Technical Notes

### Storage Format
- Endpoints are stored as an associative array (mapping)
- Keys are machine names (e.g., 'mainnet', 'my_custom_node')
- Each endpoint has: name, url, enabled boolean

### Service Pattern
- SolanaClient service automatically uses configured default endpoint
- No code changes needed in consuming code
- Transparent endpoint management

### Form Pattern
- Uses Drupal's machine_name element for automatic slug generation
- Implements proper validation at form level
- Checkboxes provide intuitive enable/disable interface

## Future Enhancements (Possible)

- Add endpoint health checking
- Track endpoint performance metrics
- Support for authenticated endpoints (API keys)
- Import/export endpoint configurations
- Endpoint testing before saving
- Automatic failover to backup endpoints
- Rate limit tracking per endpoint