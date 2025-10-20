#!/bin/bash

# Script to setup Solana Pay payment gateway in Drupal Commerce

set -e

echo "================================================"
echo "Solana Pay Gateway Setup"
echo "================================================"
echo ""

# Step 1: Enable module
echo "Step 1: Enabling solana_pay module..."
ddev drush en solana_pay -y
echo "✓ Module enabled"

# Step 2: Clear cache
echo ""
echo "Step 2: Clearing cache..."
ddev drush cr
echo "✓ Cache cleared"

# Step 3: Check if payment gateway exists
echo ""
echo "Step 3: Checking for existing gateway..."
GATEWAY_EXISTS=$(ddev drush config:get commerce_payment.commerce_payment_gateway.solana_pay 2>/dev/null || echo "")

if [ -z "$GATEWAY_EXISTS" ]; then
  echo "Gateway not found. Creating payment gateway..."
  
  # Create the payment gateway via Drush
  ddev drush php:eval "
  \$gateway_storage = \Drupal::entityTypeManager()->getStorage('commerce_payment_gateway');
  \$gateway = \$gateway_storage->create([
    'id' => 'solana_pay',
    'label' => 'Solana Pay',
    'plugin' => 'solana_pay',
    'configuration' => [
      'display_label' => 'Pay with Solana',
      'mode' => 'live',
    ],
    'status' => TRUE,
    'weight' => 0,
  ]);
  \$gateway->save();
  echo 'Payment gateway created successfully.';
  "
  
  echo "✓ Payment gateway created"
else
  echo "✓ Payment gateway already exists"
fi

# Step 4: Assign to store
echo ""
echo "Step 4: Assigning gateway to store..."
ddev drush php:eval "
\$store_storage = \Drupal::entityTypeManager()->getStorage('commerce_store');
\$stores = \$store_storage->loadMultiple();
foreach (\$stores as \$store) {
  \$gateways = \$store->get('payment_gateways')->getValue();
  \$gateway_ids = array_column(\$gateways, 'target_id');
  if (!in_array('solana_pay', \$gateway_ids)) {
    \$gateways[] = ['target_id' => 'solana_pay'];
    \$store->set('payment_gateways', \$gateways);
    \$store->save();
    echo 'Added Solana Pay to store: ' . \$store->label() . PHP_EOL;
  } else {
    echo 'Solana Pay already assigned to store: ' . \$store->label() . PHP_EOL;
  }
}
"
echo "✓ Gateway assigned to store"

# Step 5: Clear cache again
echo ""
echo "Step 5: Final cache clear..."
ddev drush cr
echo "✓ Cache cleared"

# Step 6: Show configuration
echo ""
echo "Step 6: Current configuration..."
ddev drush config:get commerce_payment.commerce_payment_gateway.solana_pay
echo ""

echo "================================================"
echo "✓ Setup complete!"
echo "================================================"
echo ""
echo "Next steps:"
echo "1. Configure merchant wallet: /admin/config/services/solana-integration"
echo "2. View payment gateways: /admin/commerce/config/payment-gateways"
echo "3. Test checkout flow"
echo ""
