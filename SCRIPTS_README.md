# DDEV Drush Scripts for Solana Integration Module

## Quick Reinstall Script

### Full Script (Recommended)
```bash
./reinstall-module.sh
```

This script will:
1. Check if the module is installed
2. Uninstall the module (if installed)
3. Clear all caches
4. Reinstall the module with fresh configuration
5. Clear caches again
6. Display module status and configuration

### One-Liner Commands

#### Simple Reinstall
```bash
ddev drush pm:uninstall solana_integration -y && ddev drush cr && ddev drush pm:enable solana_integration -y && ddev drush cr
```

#### Reinstall with Config Display
```bash
ddev drush pm:uninstall solana_integration -y && ddev drush cr && ddev drush pm:enable solana_integration -y && ddev drush cr && ddev drush config:get solana_integration.settings
```

#### Reinstall and Launch Browser
```bash
ddev drush pm:uninstall solana_integration -y && ddev drush cr && ddev drush pm:enable solana_integration -y && ddev drush cr && ddev launch /admin/config/services/solana-integration
```

## Individual Commands

### Check Module Status
```bash
ddev drush pm:list --filter=solana_integration
```

### Install Module
```bash
ddev drush pm:enable solana_integration -y
```

### Uninstall Module
```bash
ddev drush pm:uninstall solana_integration -y
```

### Clear Cache
```bash
ddev drush cr
```

### View Configuration
```bash
ddev drush config:get solana_integration.settings
```

### Edit Configuration via CLI
```bash
ddev drush config:edit solana_integration.settings
```

### Export Configuration
```bash
ddev drush config:export
# or
ddev drush cex
```

### Import Configuration
```bash
ddev drush config:import
# or
ddev drush cim
```

## Configuration Management

### View Endpoints Configuration
```bash
ddev drush config:get solana_integration.settings endpoints
```

### View Default Endpoint
```bash
ddev drush config:get solana_integration.settings default_endpoint
```

### Set Default Endpoint (via Drush)
```bash
ddev drush config:set solana_integration.settings default_endpoint devnet
```

### Add Custom Endpoint (via Drush)
```bash
# This requires editing the full config
ddev drush config:edit solana_integration.settings
```

## Testing Workflow

### Test Fresh Installation
```bash
# 1. Uninstall module
ddev drush pm:uninstall solana_integration -y

# 2. Clear cache
ddev drush cr

# 3. Reinstall module
ddev drush pm:enable solana_integration -y

# 4. Verify default endpoints are installed
ddev drush config:get solana_integration.settings endpoints

# 5. Open admin page
ddev launch /admin/config/services/solana-integration
```

### Test Configuration Changes
```bash
# 1. Make changes via admin UI at /admin/config/services/solana-integration

# 2. Export configuration
ddev drush cex -y

# 3. View the exported config file
cat config/sync/solana_integration.settings.yml

# 4. Reinstall to test if config persists
./reinstall-module.sh

# 5. Import the saved config
ddev drush cim -y
```

## Debugging

### Check for Errors
```bash
# View recent log messages
ddev drush watchdog:show --type=php

# View all logs
ddev drush watchdog:show

# Tail logs in real-time
ddev drush watchdog:tail
```

### Rebuild Cache and Registry
```bash
# Clear cache
ddev drush cr

# Rebuild router
ddev drush router:rebuild
```

### Check Module Dependencies
```bash
ddev drush pm:list --type=module --status=enabled
```

## Useful Aliases

Add these to your `.bashrc` or `.zshrc`:

```bash
# Alias for quick module reinstall
alias solana-reinstall='cd "/home/leo/Proyects/solana/path_to_solana/solana-integration/drupal module/solana-integration" && ./reinstall-module.sh'

# Alias for quick cache clear
alias ddrush-cr='ddev drush cr'

# Alias for module status
alias solana-status='ddev drush pm:list --filter=solana_integration'

# Alias for config view
alias solana-config='ddev drush config:get solana_integration.settings'

# Alias for admin page
alias solana-admin='ddev launch /admin/config/services/solana-integration'
```

## Common Scenarios

### Scenario 1: Testing Code Changes
```bash
# Make code changes to module files
# Then reinstall:
./reinstall-module.sh
```

### Scenario 2: Testing Default Configuration
```bash
# Uninstall, clear, reinstall to get fresh defaults
ddev drush pm:uninstall solana_integration -y && \
ddev drush cr && \
ddev drush pm:enable solana_integration -y && \
ddev drush config:get solana_integration.settings
```

### Scenario 3: Backing Up Configuration
```bash
# Export current config
ddev drush config:get solana_integration.settings > backup-config.yml

# Later restore it
ddev drush config:set --input-format=yaml solana_integration.settings - < backup-config.yml
```

### Scenario 4: Testing on Fresh Database
```bash
# Drop and recreate database
ddev drush sql:drop -y

# Reinstall Drupal
ddev drush site:install -y

# Install module
ddev drush pm:enable solana_integration -y
```

## Notes

- Always clear cache after code changes: `ddev drush cr`
- Configuration changes in the UI are saved immediately
- Module uninstall removes all configuration (including custom endpoints)
- Configuration export/import is useful for deploying to other environments
- The reinstall script is non-destructive to other modules

## DDEV-Specific Commands

### SSH into DDEV Container
```bash
ddev ssh
```

### Run Commands Inside Container
```bash
# From outside container
ddev drush <command>

# From inside container (after ddev ssh)
drush <command>
```

### Access Database
```bash
ddev mysql
```

### View DDEV Logs
```bash
ddev logs
```

### Restart DDEV
```bash
ddev restart
```