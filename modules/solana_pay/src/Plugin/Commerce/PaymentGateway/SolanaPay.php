<?php

namespace Drupal\solana_pay\Plugin\Commerce\PaymentGateway;

use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\OffsitePaymentGatewayBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\SupportsRefundsInterface;
use Drupal\solana_integration\Service\SolanaClient;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\commerce_price\Price;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Messenger\MessengerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;
use Drupal\Core\Url;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;

/**
 * Provides the Solana Pay Checkout payment gateway.
 *
 * @CommercePaymentGateway(
 *   id = "solana_pay",
 *   label = "Solana Pay",
 *   display_label = "Solana Pay",
 *   payment_method_types = {"solana_pay"},
 *   forms = {},
 * )
 */
class SolanaPay extends OffsitePaymentGatewayBase implements SupportsRefundsInterface, ContainerFactoryPluginInterface {

  /**
   * The Solana client.
   *
   * @var \Drupal\solana_integration\Service\SolanaClient
   */
  protected $solanaClient;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

    /**
   * The messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

   /**
    * The logger.
    *
    * @var \Psr\Log\LoggerInterface
    */
   protected $logger;

  /**
   * Constructs a new SolanaPay object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\solana_integration\Service\SolanaClient $solana_client
   *   The solana client.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    SolanaClient $solana_client,
    ConfigFactoryInterface $config_factory,
    MessengerInterface $messenger
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->solanaClient = $solana_client;
    $this->configFactory = $config_factory;
    $this->messenger = $messenger;
  }

   /**
    * {@inheritdoc}
    */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('solana_integration.client'),
      $container->get('config.factory'),
      $container->get('messenger')
    );
   }

   /**
    * {@inheritdoc}
    */
   public function defaultConfiguration() {
     return [
       'merchant_wallet_address' => '',
     ] + parent::defaultConfiguration();
   }

   /**
    * {@inheritdoc}
    */
   public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
     $form = parent::buildConfigurationForm($form, $form_state);

     $form['merchant_wallet_address'] = [
       '#type' => 'textfield',
       '#title' => $this->t('Merchant Wallet Address'),
       '#description' => $this->t('The Solana wallet address that will receive payments.'),
       '#default_value' => $this->configuration['merchant_wallet_address'],
       '#required' => TRUE,
     ];

     return $form;
   }

    /**
    * {@inheritdoc}
    */
    public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::validateConfigurationForm($form, $form_state);

    $configuration = $form_state->getValue('configuration');
    $value = isset($configuration['solana_pay']['merchant_wallet_address']) ? $configuration['solana_pay']['merchant_wallet_address'] : '';
    $address = is_string($value) ? trim($value) : '';

    if (empty($address)) {
      $form_state->setErrorByName('merchant_wallet_address', $this->t('Please enter a merchant wallet address.'));
    }
  }

   /**
    * {@inheritdoc}
    */
   public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
     parent::submitConfigurationForm($form, $form_state);

   $values = $form_state->getValues();
    if (isset($values['configuration']['solana_pay']['merchant_wallet_address'])) {
      $this->configuration['merchant_wallet_address'] = $values['configuration']['solana_pay']['merchant_wallet_address'];
    }
   }

    /**
    * {@inheritdoc}
    */
   public function buildRedirectForm(PaymentInterface $payment, Request $request, FormStateInterface $form_state) {

    $order = $payment->getOrder();
    $order_id = $order->id();
    $amount = $payment->getAmount()->getNumber();
    $label = 'Payment for order #' . $order_id;
    $message = 'Order #' . $order_id;
    $reference = '';

    $payment_request_url = $this->solanaClient->generatePaymentRequest($amount, $label, $message, $reference);

    if (!$payment_request_url) {
      $this->messenger->addError($this->t('Solana Pay is not configured correctly.'));
      return [];
    }

    $form = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['commerce-payment-redirect-form'],
      ],
    ];

    $form['#attached']['library'][] = 'commerce_payment/redirect';

    $form['#action'] = $payment_request_url;

    return $form;
  }

   /**
    * {@inheritdoc}
    */
   public function capturePayment(PaymentInterface $payment, OrderInterface $order, ?float $amount = NULL) {
     // Solana transactions are captured immediately.
     $payment->setState('completed');
     $payment->save();
   }

   /**
    * {@inheritdoc}
    */
   public function refundPayment(PaymentInterface $payment, ?Price $amount = NULL) {
     // Implement refund logic here. This is a placeholder.
     $payment->setState('refunded');
     $payment->save();
   }

    /**
   * Gets the merchant wallet address.
   *
   * @return string
   *   The merchant wallet address.
   */
  public function getMerchantWalletAddress() {
    return $this->configuration['merchant_wallet_address'];
   }

}
