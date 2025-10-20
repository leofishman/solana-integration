<?php

namespace Drupal\solana_pay\Plugin\Commerce\PaymentGateway;

use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\PaymentGatewayBase;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\ManualPaymentGatewayInterface;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\HasPaymentInstructionsInterface;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\SupportsRefundsInterface;
use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\commerce_price\Price;
use Drupal\solana_integration\Service\SolanaClient;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Provides the Solana Pay payment gateway.
 *
 * @CommercePaymentGateway(
 *   id = "solana_pay",
 *   label = "Solana Pay",
 *   display_label = "Pay with Solana",
 *   modes = {
 *     "live" = @Translation("Live"),
 *   },
 *   payment_type = "payment_manual",
 *   requires_billing_information = FALSE,
 * )
 */
class SolanaPayManual extends PaymentGatewayBase implements ManualPaymentGatewayInterface, SupportsRefundsInterface, ContainerFactoryPluginInterface {

  /**
   * The Solana client.
   *
   * @var \Drupal\solana_integration\Service\SolanaClient
   */
  protected $solanaClient;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->solanaClient = $container->get('solana_integration.client');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function buildPaymentInstructions(PaymentInterface $payment) {
    \Drupal::logger('solana_pay')->notice('Building payment instructions for payment @id', ['@id' => $payment->id()]);
    
    $order = $payment->getOrder();
    $order_id = $order->id();
    $amount = (float) $payment->getAmount()->getNumber();
    $label = 'Payment for order #' . $order_id;
    $message = 'Order #' . $order_id;

    $reference = $payment->getRemoteId();
    if (empty($reference)) {
      $payment_request_url = $this->solanaClient->generatePaymentRequest($amount, $label, $message, $reference);
      
      if ($payment_request_url && !empty($reference)) {
        $payment->setRemoteId($reference);
        $payment->save();
        \Drupal::logger('solana_pay')->notice('Generated new reference: @ref', ['@ref' => $reference]);
      }
    }
    else {
      // Regenerate URL from existing reference
      $payment_request_url = $this->solanaClient->generatePaymentRequest($amount, $label, $message, $reference);
      \Drupal::logger('solana_pay')->notice('Using existing reference: @ref', ['@ref' => $reference]);
    }

    if (!$payment_request_url) {
      \Drupal::logger('solana_pay')->error('Failed to generate payment URL');
      return [
        '#markup' => $this->t('Solana Pay is not configured. Please contact support.'),
      ];
    }

    $instructions = [
      '#theme' => 'solana_pay_instructions',
      '#payment_url' => $payment_request_url,
      '#amount' => $amount,
      '#payment_id' => $payment->id(),
      '#attached' => [
        'library' => ['solana_pay/checkout'],
        'drupalSettings' => [
          'solanaPay' => [
            'solanaUrl' => $payment_request_url,
            'statusUrl' => Url::fromRoute('solana_pay.status', ['commerce_payment' => $payment->id()], ['absolute' => TRUE])->toString(),
            'paymentId' => $payment->id(),
          ],
        ],
      ],
    ];

    return $instructions;
  }

  /**
   * {@inheritdoc}
   */
  public function createPayment(PaymentInterface $payment, $received = FALSE) {
    \Drupal::logger('solana_pay')->notice('createPayment called for order @order, received: @received', [
      '@order' => $payment->getOrder()->id(),
      '@received' => $received ? 'true' : 'false',
    ]);
    $this->assertPaymentState($payment, ['new']);
    $payment->state = $received ? 'completed' : 'pending';
    $payment->save();
    \Drupal::logger('solana_pay')->notice('Payment @id created with state @state', [
      '@id' => $payment->id(),
      '@state' => $payment->getState()->getId(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function receivePayment(PaymentInterface $payment, ?Price $amount = NULL) {
    $this->assertPaymentState($payment, ['pending']);
    // If not specified, use the entire amount.
    $amount = $amount ?: $payment->getAmount();
    $payment->state = 'completed';
    $payment->setAmount($amount);
    $payment->save();
  }

  /**
   * {@inheritdoc}
   */
  public function voidPayment(PaymentInterface $payment) {
    $this->assertPaymentState($payment, ['pending']);
    $payment->state = 'voided';
    $payment->save();
  }

  /**
   * {@inheritdoc}
   */
  public function canVoidPayment(PaymentInterface $payment) {
    return $payment->getState()->getId() === 'pending';
  }

  /**
   * {@inheritdoc}
   */
  public function buildPaymentOperations(PaymentInterface $payment) {
    $operations = [];
    $operations['receive'] = [
      'title' => $this->t('Receive'),
      'page_title' => $this->t('Receive payment'),
      'plugin_form' => 'receive-payment',
      'access' => $payment->getState()->getId() === 'pending',
    ];
    $operations['void'] = [
      'title' => $this->t('Void'),
      'page_title' => $this->t('Void payment'),
      'plugin_form' => 'void-payment',
      'access' => $this->canVoidPayment($payment),
    ];
    $operations['refund'] = [
      'title' => $this->t('Refund'),
      'page_title' => $this->t('Refund payment'),
      'plugin_form' => 'refund-payment',
      'access' => $this->canRefundPayment($payment),
    ];
    return $operations;
  }

  /**
   * {@inheritdoc}
   */
  public function refundPayment(PaymentInterface $payment, ?Price $amount = NULL) {
    $this->assertPaymentState($payment, ['completed', 'partially_refunded']);
    $amount = $amount ?: $payment->getAmount();
    $this->assertRefundAmount($payment, $amount);

    $old_refunded_amount = $payment->getRefundedAmount();
    $new_refunded_amount = $old_refunded_amount->add($amount);
    if ($new_refunded_amount->lessThan($payment->getAmount())) {
      $payment->state = 'partially_refunded';
    }
    else {
      $payment->state = 'refunded';
    }

    $payment->setRefundedAmount($new_refunded_amount);
    $payment->save();
  }

}
