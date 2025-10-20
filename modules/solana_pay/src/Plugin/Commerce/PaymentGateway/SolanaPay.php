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
class SolanaPay extends OffsitePaymentGatewayBase implements SupportsRefundsInterface, ContainerFactoryPluginInterface
{

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
    public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition)
    {
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
    public function defaultConfiguration()
    {
        $config = $this->configFactory->get('solana_integration.settings');
        $address = $config->get('merchant_wallet_address');
        return [
            'merchant_wallet_address' => $address,
        ] + parent::defaultConfiguration();
    }

    /**
     * {@inheritdoc}
     */
    public function buildConfigurationForm(array $form, FormStateInterface $form_state)
    {
        $form = parent::buildConfigurationForm($form, $form_state);
        return $form;
    }

    /**
     * {@inheritdoc}
     */
    public function validateConfigurationForm(array &$form, FormStateInterface $form_state)
    {
        parent::validateConfigurationForm($form, $form_state);
    }

    /**
     * {@inheritdoc}
     */
    public function submitConfigurationForm(array &$form, FormStateInterface $form_state)
    {
        parent::submitConfigurationForm($form, $form_state);
    }

    /**
     * {@inheritdoc}
     */
    public function createPayment(PaymentInterface $payment, array $payment_details)
    {
        // Set the payment state to 'new' initially
        $payment->setState('new');
        $payment->save();
    }

    /**
     * {@inheritdoc}
     */
    public function buildRedirectForm(PaymentInterface $payment, Request $request, FormStateInterface $form_state)
    {
        $order = $payment->getOrder();
        $order_id = $order->id();
        $amount = (float) $payment->getAmount()->getNumber();
        $label = 'Payment for order #' . $order_id;
        $message = 'Order #' . $order_id;

        $reference = '';
        $payment_request_url = $this->solanaClient->generatePaymentRequest($amount, $label, $message, $reference);

        if (!$payment_request_url || empty($reference)) {
            $this->messenger->addError($this->t('Solana Pay is not configured correctly. Please check the merchant wallet address.'));
            return [];
        }

        $payment->setRemoteId($reference);
        $payment->setState('pending');
        $payment->save();

        $form = [
            '#type' => 'container',
            '#attributes' => ['class' => ['solana-pay-checkout']],
        ];

        $form['instructions'] = [
            '#markup' => '<div class="solana-pay-instructions">' .
                '<h3>' . $this->t('Complete your payment') . '</h3>' .
                '<p>' . $this->t('Scan the QR code with your Solana wallet or click the button below to open your wallet.') . '</p>' .
                '</div>',
        ];

        $form['qr_container'] = [
            '#markup' => '<div id="solana-pay-qr" class="solana-pay-qr"></div>',
        ];

        $form['wallet_link'] = [
            '#markup' => '<div class="solana-pay-wallet-link">' .
                '<a id="solana-pay-open" href="' . htmlspecialchars($payment_request_url) . '" class="button button--primary">' .
                $this->t('Open in Wallet') .
                '</a></div>',
        ];

        $form['status_message'] = [
            '#markup' => '<div id="solana-pay-status" class="solana-pay-status">' .
                $this->t('Waiting for payment...') .
                '</div>',
        ];

        $return_url = Url::fromRoute('commerce_payment.checkout.return', [
            'commerce_order' => $order->id(),
            'step' => 'payment',
        ], ['absolute' => TRUE]);

        $cancel_url = Url::fromRoute('commerce_payment.checkout.cancel', [
            'commerce_order' => $order->id(),
            'step' => 'payment',
        ], ['absolute' => TRUE]);

        $form['cancel_link'] = [
            '#markup' => '<div class="solana-pay-cancel">' .
                '<a href="' . $cancel_url->toString() . '">' .
                $this->t('Cancel payment') .
                '</a></div>',
        ];

        $form['#attached']['library'][] = 'solana_pay/checkout';
        $form['#attached']['drupalSettings']['solanaPay'] = [
            'solanaUrl' => $payment_request_url,
            'statusUrl' => Url::fromRoute('solana_pay.status', ['commerce_payment' => $payment->id()], ['absolute' => TRUE])->toString(),
            'returnUrl' => $return_url->toString(),
            'cancelUrl' => $cancel_url->toString(),
            'paymentId' => $payment->id(),
        ];

        return $form;
    }

    /**
     * {@inheritdoc}
     */
    public function onReturn(OrderInterface $order, Request $request)
    {
        $payment_storage = $this->entityTypeManager->getStorage('commerce_payment');
        $payments = $payment_storage->loadByProperties([
            'order_id' => $order->id(),
            'state' => 'pending',
        ]);

        if (empty($payments)) {
            $this->messenger->addError($this->t('No pending payment found for this order.'));
            return;
        }

        $payment = reset($payments);
        $reference = $payment->getRemoteId();

        if (empty($reference)) {
            $this->messenger->addError($this->t('Payment reference not found.'));
            return;
        }

        $expected_amount = (float) $payment->getAmount()->getNumber();
        $is_verified = $this->solanaClient->verifyPayment($reference, $expected_amount);

        if ($is_verified) {
            $payment->setState('completed');
            $payment->save();
            $this->messenger->addStatus($this->t('Your payment has been confirmed on the Solana blockchain.'));
        } else {
            $this->messenger->addWarning($this->t('Payment verification is still pending. Please wait a moment and refresh the page.'));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function onCancel(OrderInterface $order, Request $request)
    {
        $payment_storage = $this->entityTypeManager->getStorage('commerce_payment');
        $payments = $payment_storage->loadByProperties([
            'order_id' => $order->id(),
            'state' => 'pending',
        ]);

        if (!empty($payments)) {
            $payment = reset($payments);
            $payment->setState('canceled');
            $payment->save();
        }

        $this->messenger->addMessage($this->t('You have canceled the Solana Pay checkout. You can try again when ready.'));
    }

    /**
     * {@inheritdoc}
     */
    public function capturePayment(PaymentInterface $payment, ?Price $amount = NULL)
    {
        // Solana transactions are captured immediately.
        $payment->setState('completed');
        $payment->save();
    }

    /**
     * {@inheritdoc}
     */
    public function refundPayment(PaymentInterface $payment, ?Price $amount = NULL)
    {
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
    public function getMerchantWalletAddress()
    {
        $config = $this->configFactory->get('solana_integration.settings');
        return $config->get('merchant_wallet_address') ?? '';
    }
}
