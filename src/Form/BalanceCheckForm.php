<?php

namespace Drupal\solana_integration\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\solana_integration\Service\SolanaClient;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form to check the balance of a Solana account.
 */
class BalanceCheckForm extends FormBase {

  /**
   * The Solana client service.
   *
   * @var \Drupal\solana_integration\Service\SolanaClient
   */
  protected SolanaClient $solanaClient;

  /**
   * {@inheritdoc}
   */
  public function __construct(SolanaClient $solana_client, MessengerInterface $messenger) {
    $this->solanaClient = $solana_client;
    $this->setMessenger($messenger);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get('solana_integration.client'),
      $container->get('messenger')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'solana_integration_balance_check_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['account_address'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Solana Account Address'),
      '#description' => $this->t('Enter the public key of the Solana account to check.'),
      '#required' => TRUE,
      '#maxlength' => 44,
      '#size' => 45,
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Check Balance'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $address = trim($form_state->getValue('account_address'));
    // Basic validation for Base58 characters and length.
    if (!preg_match('/^[1-9A-HJ-NP-Za-km-z]{32,44}$/', $address)) {
      // $form_state->setErrorByName('account_address', $this->t('This does not appear to be a valid Solana wallet address.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $address = $form_state->getValue('account_address');

    try {
      $balance_array = $this->solanaClient->getBalance($address);
      $lamports = $balance_array['value'] ?? null;
      if (is_int($lamports)) {
        $sol = $lamports / 1_000_000_000; // 1 SOL = 10^9 lamports
        $this->messenger()->addStatus($this->t('The balance for account @address is @sol SOL (@lamports lamports). <br />RPC Endpoint: @endpoint', [
          '@address' => $address,
          '@sol' => number_format($sol, 9),
          '@lamports' => number_format($lamports),
          '@endpoint' => $this->solanaClient->getEndpoint()
        ]));
      }
      else {
        $this->messenger()->addWarning($this->t('Could not determine the balance. The account may not exist or an RPC error occurred.'));
      }
    }
    catch (\Exception $e) {
      $this->messenger()->addError($this->t('An unexpected error occurred: @message', ['@message' => $e->getMessage()]));
    }
  }

}