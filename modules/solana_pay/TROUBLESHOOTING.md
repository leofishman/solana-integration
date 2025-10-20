# Troubleshooting: Payment Gateway Not Set Error

## The Problem

You're getting: **"Call to a member function getPluginId() on null"**

This means the `payment_gateway` field on the order is not being set when you select "Pay with Solana".

## Root Cause

The **Payment information** pane is not properly saving the selected payment gateway to the order.

## Solution: Fix Checkout Flow Configuration

### Step 1: Verify Payment Information Pane

Go to `/admin/commerce/config/checkout-flows` and edit "Default"

Make sure you have these panes in the correct order:

```
ORDER INFORMATION step:
  - Login (weight 0)
  - Contact information (weight 1)
  - Payment information (weight 2)  ← MUST BE ENABLED
  - Review (weight 3)

PAYMENT step:
  - Solana Pay Checkout (weight 4)  ← Your custom pane
  OR
  - Payment process (weight 4)      ← Standard pane (disable if using custom)
```

### Step 2: Check Payment Information Configuration

The `payment_information` pane MUST be:
- **Enabled** (in "order_information" step)
- **Before** the review and payment steps
- Has proper configuration:
  - `always_display`: false or true (your choice)
  - `require_payment_method`: false (for offsite gateways)

### Step 3: Verify Gateway Assignment to Store

1. Go to `/admin/commerce/config/stores`
2. Edit your store
3. Under "Payment gateways", ensure **"Solana Pay"** is checked
4. Save

### Step 4: Verify Gateway Exists

Check at `/admin/commerce/config/payment-gateways`:
- You should see "Solana Pay" listed
- Status: Enabled
- Plugin: solana_pay

If not there, create it:
- Click "Add payment gateway"
- Label: `Solana Pay`
- Plugin: `Solana Pay`
- Save

## Test the Fix

1. Clear cache
2. Add product to cart
3. Go to checkout
4. On "order_information" step, you should see payment method selection
5. Select "Pay with Solana"
6. Continue to next step
7. The `payment_gateway` field should now be set on the order

## Debug: Check if Gateway is Selected

Add this debug code temporarily to `SolanaPayCheckout.php` line 60:

```php
// DEBUG: Check what's happening
\Drupal::messenger()->addWarning('Payment gateway empty: ' . ($this->order->get('payment_gateway')->isEmpty() ? 'YES' : 'NO'));
if (!$this->order->get('payment_gateway')->isEmpty()) {
  $pg = $this->order->get('payment_gateway')->entity;
  \Drupal::messenger()->addWarning('Gateway: ' . ($pg ? $pg->label() : 'NULL'));
}
```

This will show you whether the gateway is being selected.

## Alternative: Force Gateway Selection

If the payment_information pane isn't working, you can force-set it in your custom pane:

```php
public function buildPaneForm(array $pane_form, FormStateInterface $form_state, array &$complete_form) {
  // Force set Solana Pay if not set
  if ($this->order->get('payment_gateway')->isEmpty()) {
    $gateway_storage = $this->entityTypeManager->getStorage('commerce_payment_gateway');
    $gateway = $gateway_storage->load('solana_pay');
    if ($gateway) {
      $this->order->set('payment_gateway', $gateway);
      $this->order->save();
    }
  }
  
  // Rest of the code...
}
```

But this is a **workaround** - the proper solution is to fix the payment_information pane.

## Expected Checkout Flow

```
Step 1: ORDER INFORMATION
├── Login
├── Contact Information
└── Payment Information  ← User selects "Pay with Solana" here
    └── Saves gateway to order

Step 2: REVIEW
└── Show order summary

Step 3: PAYMENT
└── Solana Pay Checkout  ← Shows QR code (only if Solana selected)
    └── Polls for payment
    └── Redirects on success

Step 4: COMPLETE
└── Order complete message
```

## Still Not Working?

Export your checkout flow config:
```bash
drush config:get commerce_checkout.commerce_checkout_flow.default
```

Share the output to debug the configuration.
