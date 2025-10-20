# Fix: Payment Entity Not Being Created

## Problem
The logs show:
- ✓ Completion page reached
- ✓ Gateway is set correctly  
- ✗ **0 payments found** - Payment entity was never created!

## Root Cause
The `payment_process` pane is either:
1. Disabled in your checkout flow
2. Not visible for manual gateways
3. Being skipped

## Solution: Enable Payment Process Pane

### Step 1: Check Checkout Flow

Go to `/admin/commerce/config/checkout-flows/manage/default/edit`

Find the **"payment"** step and make sure:
- **"Payment process" pane is ENABLED** (not in Disabled section)
- It should be in the "payment" step
- Weight should be around 4

### Step 2: Verify Pane Configuration

The payment_process pane should have:
- **Display label**: null or empty
- **Step**: payment
- **Wrapper element**: container
- **Capture**: false (for manual gateways)

### Step 3: Alternative - Use Different Step

If the "payment" step is being skipped, you can also put it in the "review" step:

1. Go to checkout flow edit page
2. Find "Payment process" pane
3. Drag it to the **"review"** step (instead of "payment")
4. Put it AFTER the "Review" pane
5. Save

This will create the payment before going to the complete page.

## Quick Fix via Drush

```bash
ddev drush php:eval "
\$flow = \Drupal::entityTypeManager()->getStorage('commerce_checkout_flow')->load('default');
\$config = \$flow->get('configuration');
\$config['panes']['payment_process'] = [
  'display_label' => NULL,
  'step' => 'review',
  'weight' => 10,
  'wrapper_element' => 'container',
  'capture' => FALSE,
];
\$flow->set('configuration', \$config);
\$flow->save();
echo 'Payment process pane enabled in review step';
"
ddev drush cr
```

## Test Again

After enabling the pane:
1. Clear cache
2. Complete a new order
3. Check logs: `ddev drush watchdog:tail`
4. You should now see:
   - "createPayment called for order X"
   - "Payment X created with state pending"
   - "Found 1 payments for order X"
   - "Building payment instructions for payment X"

## If Still Not Working

Check if the pane is even showing up during checkout:
- Add some debug output to see the pane form
- Or check the page HTML source to see if any payment_process elements exist

The pane might be hidden due to the `isVisible()` logic in PaymentProcess.
