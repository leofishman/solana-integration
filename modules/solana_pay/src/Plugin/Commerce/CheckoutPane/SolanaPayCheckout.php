<?php

namespace Drupal\solana_pay\Plugin\Commerce\CheckoutPane;

use Drupal\commerce_checkout\Plugin\Commerce\CheckoutPane\CheckoutPaneBase;
use Drupal\commerce_payment\Plugin\Commerce\CheckoutPane\PaymentCheckoutPaneBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\solana_integration\Service\SolanaClient;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\commerce_checkout\Plugin\Commerce\CheckoutFlow\CheckoutFlowInterface;

/**
 * Provides the Solana Pay checkout pane.
 *
 * @CommerceCheckoutPane(
 *   id = "solana_pay_checkout",
 *   label = @Translation("Solana Pay Checkout"),
 *   default_step = "payment",
 * )
 */
class SolanaPayCheckout extends PaymentCheckoutPaneBase {

  /**
   * The Solana client.
   *
   * @var \Drupal\solana_integration\Service\SolanaClient
   */
  protected $solanaClient;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition, ?CheckoutFlowInterface $checkout_flow = NULL) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition, $checkout_flow);
    $instance->solanaClient = $container->get('solana_integration.client');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function isVisible() {
    // Only show this pane if Solana Pay is selected as the payment gateway.
    if ($this->order->get('payment_gateway')->isEmpty()) {
      return FALSE;
    }

    $payment_gateway = $this->order->get('payment_gateway')->entity;
    if (!$payment_gateway) {
      return FALSE;
    }

    return $payment_gateway->getPluginId() === 'solana_pay';
  }

  /**
   * {@inheritdoc}
   */
  public function buildPaneForm(array $pane_form, FormStateInterface $form_state, array &$complete_form) {
    $payment_gateway = $this->order->get('payment_gateway')->entity;
    
    if (!$payment_gateway) {
      $pane_form['error'] = [
        '#markup' => $this->t('Payment gateway not found.'),
      ];
      return $pane_form;
    }

    // Create or load the payment.
    $payment_storage = $this->entityTypeManager->getStorage('commerce_payment');
    $payments = $payment_storage->loadByProperties([
      'order_id' => $this->order->id(),
      'state' => ['new', 'pending'],
    ]);
    
    if (!empty($payments)) {
      $payment = reset($payments);
    }
    else {
      $payment = $payment_storage->create([
        'state' => 'new',
        'amount' => $this->order->getTotalPrice(),
        'payment_gateway' => $payment_gateway->id(),
        'order_id' => $this->order->id(),
      ]);
      $payment->save();
    }

    $order_id = $this->order->id();
    $amount = (float) $this->order->getTotalPrice()->getNumber();
    $label = 'Payment for order #' . $order_id;
    $message = 'Order #' . $order_id;

    $reference = $payment->getRemoteId();
    if (empty($reference)) {
      $payment_request_url = $this->solanaClient->generatePaymentRequest($amount, $label, $message, $reference);
      
      if (!$payment_request_url || empty($reference)) {
        $pane_form['error'] = [
          '#markup' => $this->t('Solana Pay is not configured correctly. Please check the merchant wallet address.'),
        ];
        return $pane_form;
      }

      $payment->setRemoteId($reference);
      $payment->setState('pending');
      $payment->save();
    }
    else {
      // Regenerate URL from stored reference
      $payment_request_url = $this->solanaClient->generatePaymentRequest($amount, $label, $message, $reference);
    }

    $pane_form['#attached']['library'][] = 'solana_pay/checkout';
    
    $pane_form['instructions'] = [
      '#markup' => '<div class="solana-pay-instructions">' .
        '<h3>' . $this->t('Complete your payment') . '</h3>' .
        '<p>' . $this->t('Scan the QR code with your Solana wallet or click the button below to open your wallet.') . '</p>' .
        '</div>',
    ];

    $pane_form['qr_container'] = [
      '#markup' => '<div id="solana-pay-qr" class="solana-pay-qr"></div>',
    ];

    $pane_form['wallet_link'] = [
      '#markup' => '<div class="solana-pay-wallet-link">' .
        '<a id="solana-pay-open" href="' . htmlspecialchars($payment_request_url) . '" class="button button--primary">' .
        $this->t('Open in Wallet') .
        '</a></div>',
    ];

    $pane_form['status_message'] = [
      '#markup' => '<div id="solana-pay-status" class="solana-pay-status">' .
        $this->t('Waiting for payment...') .
        '</div>',
    ];

    $return_url = Url::fromRoute('commerce_checkout.form', [
      'commerce_order' => $this->order->id(),
      'step' => $this->checkoutFlow->getNextStepId($this->getStepId()),
    ], ['absolute' => TRUE]);

    $pane_form['#attached']['drupalSettings']['solanaPay'] = [
      'solanaUrl' => $payment_request_url,
      'statusUrl' => Url::fromRoute('solana_pay.status', ['commerce_payment' => $payment->id()], ['absolute' => TRUE])->toString(),
      'returnUrl' => $return_url->toString(),
      'paymentId' => $payment->id(),
    ];

    // Hide the continue button - payment status polling will redirect automatically
    $complete_form['actions']['next']['#access'] = FALSE;

    return $pane_form;
  }

  /**
   * {@inheritdoc}
   */
  public function validatePaneForm(array &$pane_form, FormStateInterface $form_state, array &$complete_form) {
    // Validation happens via the status check endpoint.
  }

  /**
   * {@inheritdoc}
   */
  public function submitPaneForm(array &$pane_form, FormStateInterface $form_state, array &$complete_form) {
    // Payment is already saved via the status check endpoint.
  }

}
