<?php

/**
 * @file
 * Test script to demonstrate the new endpoint configuration system.
 * 
 * This file is for testing purposes only and should not be included in production.
 */

// Mock configuration data to simulate the new structure.
$mock_config = [
  'endpoints' => [
    'mainnet' => [
      'name' => 'Mainnet Beta',
      'url' => 'https://api.mainnet-beta.solana.com',
      'enabled' => true,
    ],
    'devnet' => [
      'name' => 'Devnet',
      'url' => 'https://api.devnet.solana.com',
      'enabled' => true,
    ],
    'testnet' => [
      'name' => 'Testnet',
      'url' => 'https://api.testnet.solana.com',
      'enabled' => false,
    ],
  ],
  'default_endpoint' => 'mainnet',
  'request_timeout' => 5,
];

/**
 * Simulate the SolanaClient::getEndpoint() method logic.
 */
function getEndpoint($config) {
  $default_endpoint_key = $config['default_endpoint'] ?? 'mainnet';
  $endpoints = $config['endpoints'] ?? [];
  
  // Get the URL for the default endpoint.
  if (isset($endpoints[$default_endpoint_key]['url'])) {
    return $endpoints[$default_endpoint_key]['url'];
  }
  
  // Fallback to the first enabled endpoint if default is not available.
  foreach ($endpoints as $endpoint) {
    if (!empty($endpoint['enabled']) && !empty($endpoint['url'])) {
      return $endpoint['url'];
    }
  }
  
  // Final fallback to mainnet if nothing else is available.
  return 'https://api.mainnet-beta.solana.com';
}

/**
 * Get list of enabled endpoints.
 */
function getEnabledEndpoints($config) {
  $enabled = [];
  $endpoints = $config['endpoints'] ?? [];
  
  foreach ($endpoints as $key => $endpoint) {
    if (!empty($endpoint['enabled'])) {
      $enabled[$key] = $endpoint;
    }
  }
  
  return $enabled;
}

// Test the functions
echo "Configuration Test Results:\n";
echo "==========================\n\n";

echo "Current endpoint URL: " . getEndpoint($mock_config) . "\n";
echo "Default endpoint key: " . $mock_config['default_endpoint'] . "\n\n";

echo "Enabled endpoints:\n";
foreach (getEnabledEndpoints($mock_config) as $key => $endpoint) {
  echo "  - {$key}: {$endpoint['name']} ({$endpoint['url']})\n";
}

echo "\nDisabled endpoints:\n";
foreach ($mock_config['endpoints'] as $key => $endpoint) {
  if (empty($endpoint['enabled'])) {
    echo "  - {$key}: {$endpoint['name']} ({$endpoint['url']})\n";
  }
}

echo "\n";

// Test fallback behavior
echo "Testing fallback behavior:\n";
echo "=========================\n";

// Test with non-existent default
$test_config = $mock_config;
$test_config['default_endpoint'] = 'nonexistent';
echo "With non-existent default: " . getEndpoint($test_config) . "\n";

// Test with disabled default
$test_config = $mock_config;
$test_config['default_endpoint'] = 'testnet';
echo "With disabled default: " . getEndpoint($test_config) . "\n";

// Test with all endpoints disabled
$test_config = $mock_config;
foreach ($test_config['endpoints'] as &$endpoint) {
  $endpoint['enabled'] = false;
}
echo "With all endpoints disabled: " . getEndpoint($test_config) . "\n";

echo "\nTest completed successfully!\n";