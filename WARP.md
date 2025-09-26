# WARP.md

This file provides guidance to WARP (warp.dev) when working with code in this repository.

## Project Overview

This is a **Drupal module** that provides Solana blockchain integration functionality. The module creates a JSON-RPC client service for communicating with Solana networks and includes an administrative interface for configuration.

## Common Development Commands

### Drupal Module Management
```bash
# Enable the module
drush en solana_integration

# Disable the module
drush dis solana_integration

# Uninstall the module (removes configuration)
drush pmu solana_integration

# Clear Drupal cache (essential after code changes)
drush cr

# Check module status
drush pm:list | grep solana
```

### Dependencies and Setup
```bash
# Install PHP dependencies
composer install

# Update dependencies
composer update

# Check for security updates
composer audit

# Install dev dependencies for testing
composer install --dev
```

### Testing and Quality Assurance
```bash
# Run PHPUnit tests (when test files exist)
phpunit --configuration core/phpunit.xml.dist modules/custom/solana_integration/

# Run PHP CodeSniffer for coding standards
phpcs --standard=Drupal modules/custom/solana_integration/

# Fix coding standards automatically
phpcbf --standard=Drupal modules/custom/solana_integration/

# Run PHPStan static analysis (if configured)
phpstan analyse modules/custom/solana_integration/
```

### Development Workflow
```bash
# Watch for configuration changes and export
drush cex

# Import configuration changes
drush cim

# Check configuration differences
drush config:status
```

## Code Architecture

### Service Layer Architecture
The module follows Drupal's service container pattern with dependency injection:

- **Main Service**: `SolanaClient` (`src/Service/SolanaClient.php`)
  - Registered as `solana_integration.client` in the service container
  - Handles JSON-RPC communication with Solana networks
  - Configured via Drupal's configuration system
  - Dependencies: HTTP client and config factory

### Configuration System
- **Schema**: Defined in `config/schema/solana_integration.schema.yml`
- **Default Values**: Set in `config/install/solana_integration.settings.yml`
- **Settings Form**: `src/Form/SettingsForm.php` extends `ConfigFormBase`
- **Route**: Admin interface at `/admin/config/services/solana-integration`

### Key Components

#### SolanaClient Service
- **Purpose**: Wrapper around Guzzle HTTP client for Solana JSON-RPC calls
- **Method**: `rpc(string $method, array $params = []): array`
- **Configuration**: Reads RPC endpoint and timeout from Drupal config
- **Usage Pattern**: Inject via dependency injection or use `\Drupal::service('solana_integration.client')`

#### SettingsForm
- **Purpose**: Administrative interface for module configuration
- **Fields**: RPC endpoint URL, request timeout
- **Default Endpoint**: `https://api.mainnet-beta.solana.com`
- **Permissions**: Requires 'administer site configuration'

### Module Integration Points
- **Hook Implementation**: `hook_help()` in the `.module` file
- **Service Registration**: `.services.yml` file registers the client service
- **Routing**: `.routing.yml` file defines admin routes
- **Dependencies**: Requires Drupal core modules: system, user, field, node

## File Structure

```
solana_integration/
├── config/
│   ├── install/                # Default configuration
│   └── schema/                 # Configuration schema definitions
├── src/
│   ├── Form/
│   │   └── SettingsForm.php   # Admin configuration form
│   └── Service/
│       └── SolanaClient.php   # Main JSON-RPC client service
├── solana_integration.info.yml # Module metadata and dependencies
├── solana_integration.module  # Hook implementations
├── solana_integration.routing.yml # Route definitions
└── solana_integration.services.yml # Service container definitions
```

## Extension Patterns

### Adding New RPC Methods
To add Solana-specific functionality, extend the `SolanaClient` service:

```php
// Example: Add a method to get account balance
public function getBalance(string $pubkey): array {
  return $this->rpc('getBalance', [$pubkey]);
}
```

### Custom Services
When creating additional services, register them in `solana_integration.services.yml` and follow Drupal's dependency injection patterns.

### Configuration Extensions
Add new configuration fields by:
1. Updating the schema in `config/schema/solana_integration.schema.yml`
2. Adding form fields in `src/Form/SettingsForm.php`
3. Updating default values in `config/install/solana_integration.settings.yml`

## Development Context

- **PHP Requirements**: 8.1+ (info.yml specifies 8.3)
- **Drupal Compatibility**: Core 10 or 11
- **Primary Dependency**: Guzzle HTTP client for JSON-RPC communication
- **Package Category**: Blockchain
- **License**: GPL-2.0-or-later

This module serves as a foundation for building Solana wallet integration, transaction handling, and smart contract interactions within Drupal applications.