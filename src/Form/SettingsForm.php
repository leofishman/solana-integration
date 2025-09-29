<?php

namespace Drupal\solana_integration\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure Solana Integration settings for this site.
 */
class SettingsForm extends ConfigFormBase {

  protected function getEditableConfigNames() {
    return ['solana_integration.settings'];
  }

  public function getFormId() {
    return 'solana_integration_settings_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('solana_integration.settings');

    $form['rpc_endpoint'] = [
      '#type' => 'textfield',
      '#title' => $this->t('RPC endpoint URL'),
      '#default_value' => $config->get('rpc_endpoint') ?? 'https://api.mainnet-beta.solana.com',
      '#required' => TRUE,
      '#description' => $this->t('URL of the Solana JSON-RPC endpoint.'),
    ];

    $form['request_timeout'] = [
      '#type' => 'number',
      '#title' => $this->t('Request timeout (seconds)'),
      '#default_value' => $config->get('request_timeout') ?? 5,
      '#min' => 1,
      '#required' => TRUE,
    ];

    return parent::buildForm($form, $form_state);
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);
    $this->configFactory->getEditable('solana_integration.settings')
      ->set('rpc_endpoint', $form_state->getValue('rpc_endpoint'))
      ->set('request_timeout', (int) $form_state->getValue('request_timeout'))
      ->save();
  }
}
