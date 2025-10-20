# Debug: Why Solana Pay Pane Isn't Showing

## Quick Check

1. **Is the pane enabled in the checkout flow?**
   - Go to `/admin/commerce/config/checkout-flows`
   - Edit "Default"
   - Look for "Solana Pay Checkout" - is it in the "payment" step or "Disabled"?
   - If it's in "Disabled", drag it to the "payment" step
   - Save

2. **Check pane order**
   The pane needs to be AFTER the payment gateway is selected. Typical order:
   ```
   Step: ORDER INFORMATION
   - Payment information (selects gateway)
   
   Step: REVIEW
   - Review pane
   
   Step: PAYMENT
   - Solana Pay Checkout (shows QR) ← Should be here
   
   Step: COMPLETE
   - Completion message
   ```

## Enable the Pane

If "Solana Pay Checkout" is in the Disabled section:

1. Drag it from "Disabled" to "payment" step
2. Position it at weight 4 (or wherever you want)
3. **Important**: Make sure the "payment" step exists in your flow
4. Save the checkout flow
5. Clear cache

## Alternative: Check if Payment Step Exists

Your checkout flow might not have a "payment" step. Check the steps:
- login
- order_information
- review
- **payment** ← Does this exist?
- complete

If "payment" step doesn't exist, either:
- Create it, OR
- Put the pane in a different step (like "review")

## Manual Configuration

Edit the checkout flow YAML or use this Drush command:

```bash
drush php:eval "
\$flow = \Drupal::entityTypeManager()->getStorage('commerce_checkout_flow')->load('default');
\$config = \$flow->get('configuration');
\$config['panes']['solana_pay_checkout'] = [
  'display_label' => NULL,
  'step' => 'payment',
  'weight' => 4,
  'wrapper_element' => 'container',
];
\$flow->set('configuration', \$config);
\$flow->save();
echo 'Solana Pay Checkout pane enabled in payment step';
"
```

## Debug: Force Pane to Always Show

Temporarily modify the `isVisible()` method to always return TRUE:

In `SolanaPayCheckout.php`, change:

```php
public function isVisible() {
  // Debug: Always show
  return TRUE;
  
  // Original code below...
}
```

This will show the pane regardless of gateway selection, helping you verify it's configured.

## Expected Behavior

When working correctly:
1. You select "Pay with Solana" at checkout
2. Click through to review
3. Click "Continue to payment" (or whatever your button says)
4. You should see QR code page
5. JavaScript polls for payment
6. Redirects to complete when paid

If you're going straight from review to complete, the payment step is being skipped entirely.
