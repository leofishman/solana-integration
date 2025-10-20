<?php

namespace Drupal\solana_pay\Plugin\Commerce\PaymentGateway;

use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\PaymentGatewayBase;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\ManualPaymentGatewayInterface;
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
      }
    }
    else {
      // Regenerate URL from existing reference
      $payment_request_url = $this->solanaClient->generatePaymentRequest($amount, $label, $message, $reference);
    }

    if (!$payment_request_url) {
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
    $this->assertPaymentState($payment, ['new']);
    $payment->state = $received ? 'completed' : 'pending';
    $payment->save();
  }

  /**
   * {@inheritdoc}
   */
  public function buildPaymentOperations(PaymentInterface $payment) {
    return [];
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
