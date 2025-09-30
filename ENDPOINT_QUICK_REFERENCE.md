# Endpoint Management - Quick Reference

## Default Endpoints (Installed Automatically)

| Key      | Name           | URL                                     | Enabled by Default |
|----------|----------------|----------------------------------------|--------------------|
| mainnet  | Mainnet Beta   | https://api.mainnet-beta.solana.com    | ✓ Yes             |
| devnet   | Devnet         | https://api.devnet.solana.com          | ✓ Yes             |
| testnet  | Testnet        | https://api.testnet.solana.com         | ✗ No              |

## Admin Interface

**URL**: `/admin/config/services/solana-integration`

### Form Sections

```
┌─────────────────────────────────────────────────────┐
│ RPC Endpoints Configuration                         │
├─────────────────────────────────────────────────────┤
│ ☑ Enabled endpoints:                                │
│   [x] Mainnet Beta (https://...)                    │
│   [x] Devnet (https://...)                          │
│   [ ] Testnet (https://...)                         │
│                                                      │
│ Default endpoint: [Mainnet Beta ▾]                  │
└─────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────┐
│ Manage Endpoints                                     │
├─────────────────────────────────────────────────────┤
│ ▸ Mainnet Beta                                       │
│   Name: [Mainnet Beta                            ]  │
│   URL:  [https://api.mainnet-beta.solana.com    ]  │
│   [ ] Delete this endpoint                          │
│                                                      │
│ ▸ Devnet                                             │
│ ▸ Testnet                                            │
└─────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────┐
│ Add Custom Endpoint                                  │
├─────────────────────────────────────────────────────┤
│ Endpoint name:         [                         ]  │
│ Endpoint machine name: [                         ]  │
│ Endpoint URL:          [                         ]  │
│ [x] Enable this endpoint                            │
└─────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────┐
│ Request timeout (seconds): [5]                      │
└─────────────────────────────────────────────────────┘
```

## Common Operations

### Add a Custom Endpoint
1. Go to "Add Custom Endpoint" section
2. Fill in:
   - **Name**: e.g., "QuickNode Premium"
   - **Machine name**: Auto-generated (e.g., "quicknode_premium")
   - **URL**: e.g., "https://my-node.quiknode.pro/xxxxx"
3. Check "Enable this endpoint" if you want to use it immediately
4. Click "Save configuration"

### Edit an Endpoint
1. Go to "Manage Endpoints" section
2. Expand the endpoint you want to edit
3. Modify the Name or URL
4. Click "Save configuration"

### Delete an Endpoint
1. Go to "Manage Endpoints" section
2. Expand the endpoint you want to delete
3. Check "Delete this endpoint"
4. Click "Save configuration"

### Change Default Endpoint
1. Go to "RPC Endpoints Configuration" section
2. Select desired endpoint from "Default endpoint" dropdown
3. Click "Save configuration"

### Enable/Disable Endpoints
1. Go to "RPC Endpoints Configuration" section
2. Check/uncheck endpoints in "Enabled endpoints"
3. Click "Save configuration"

## Rules & Constraints

✓ **Can Do**:
- Add unlimited custom endpoints
- Edit any endpoint name or URL
- Delete any endpoint (including official ones)
- Enable/disable any endpoint
- Change default endpoint anytime

✗ **Cannot Do**:
- Disable all endpoints (at least 1 must be enabled)
- Set a disabled endpoint as default
- Use duplicate machine names
- Add endpoint without name, key, or URL

## Code Usage

```php
// The service automatically uses the configured default endpoint
$client = \Drupal::service('solana_integration.client');

// Make RPC call - uses default endpoint transparently
$result = $client->rpc('getHealth');

// No code changes needed when switching endpoints!
```

## Configuration Structure (YAML)

```yaml
endpoints:
  mainnet:                                    # Machine name (key)
    name: 'Mainnet Beta'                      # Human-readable name
    url: 'https://api.mainnet-beta.solana.com' # RPC endpoint URL
    enabled: true                             # Is this endpoint enabled?
  my_custom:
    name: 'My Custom Node'
    url: 'https://my-rpc.example.com'
    enabled: true
default_endpoint: 'mainnet'                   # Which endpoint to use
request_timeout: 5                            # Request timeout in seconds
```

## Drush Commands

```bash
# Clear cache after configuration changes
drush cr

# Export configuration to sync directory
drush cex

# Import configuration from sync directory
drush cim

# Check configuration status
drush config:status
```

## Examples of Custom Endpoints

### Local Development
```
Name: Local Test Validator
Machine name: local_validator
URL: http://localhost:8899
```

### QuickNode
```
Name: QuickNode Mainnet
Machine name: quicknode_mainnet
URL: https://your-endpoint.quiknode.pro/xxxxx/
```

### Alchemy
```
Name: Alchemy Mainnet
Machine name: alchemy_mainnet
URL: https://solana-mainnet.g.alchemy.com/v2/xxxxx
```

### GenesysGo (Shadow)
```
Name: GenesysGo Mainnet
Machine name: genesysgo_mainnet
URL: https://ssc-dao.genesysgo.net/
```

### Private Node
```
Name: Company Private Node
Machine name: company_private
URL: https://solana-rpc.company.internal:8899
```

## Troubleshooting

| Problem | Solution |
|---------|----------|
| Cannot save with all endpoints disabled | Enable at least one endpoint |
| "Default endpoint must be enabled" error | Enable the endpoint you're trying to set as default |
| "Endpoint machine name already exists" | Choose a different machine name |
| Changes not taking effect | Run `drush cr` to clear cache |
| Endpoint not working | Verify URL is correct and accessible |

## Tips

💡 **Tip 1**: Use descriptive names for custom endpoints (e.g., "QuickNode Production" vs "custom1")

💡 **Tip 2**: Keep testnet disabled in production to avoid accidental usage

💡 **Tip 3**: Add local validator endpoint for development: http://localhost:8899

💡 **Tip 4**: Test new endpoints with a simple `getHealth` call before fully switching

💡 **Tip 5**: Premium RPC providers often have rate limits - monitor usage