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
    $endpoints = $config->get('endpoints') ?? [];
    $default_endpoint = $config->get('default_endpoint') ?? 'mainnet';

    // Endpoints configuration section.
    $form['endpoints_section'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('RPC Endpoints Configuration'),
      '#description' => $this->t('Configure which Solana RPC endpoints are available and which one to use by default.'),
    ];

    // Create checkboxes for enabling/disabling endpoints.
    $enabled_endpoints = [];
    $endpoint_options = [];
    
    foreach ($endpoints as $key => $endpoint) {
      if (!empty($endpoint['enabled'])) {
        $enabled_endpoints[$key] = $key;
      }
      $endpoint_options[$key] = $endpoint['name'] . ' (' . $endpoint['url'] . ')';
    }

    $form['endpoints_section']['enabled_endpoints'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Enabled endpoints'),
      '#options' => $endpoint_options,
      '#default_value' => $enabled_endpoints,
      '#description' => $this->t('Select which endpoints are available for use.'),
    ];

    // Create select for default endpoint (only show enabled ones).
    $form['endpoints_section']['default_endpoint'] = [
      '#type' => 'select',
      '#title' => $this->t('Default endpoint'),
      '#options' => $endpoint_options,
      '#default_value' => $default_endpoint,
      '#required' => TRUE,
      '#description' => $this->t('The default endpoint to use for Solana JSON-RPC requests.'),
    ];

    // Endpoint management section.
    $form['endpoint_management'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Endpoint Details'),
      '#description' => $this->t('View and modify endpoint configurations.'),
    ];

    foreach ($endpoints as $key => $endpoint) {
      $form['endpoint_management'][$key] = [
        '#type' => 'fieldset',
        '#title' => $endpoint['name'],
        '#collapsible' => TRUE,
        '#collapsed' => TRUE,
      ];

      $form['endpoint_management'][$key]['name'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Name'),
        '#default_value' => $endpoint['name'],
        '#required' => TRUE,
      ];

      $form['endpoint_management'][$key]['url'] = [
        '#type' => 'url',
        '#title' => $this->t('URL'),
        '#default_value' => $endpoint['url'],
        '#required' => TRUE,
        '#description' => $this->t('The JSON-RPC endpoint URL.'),
      ];
    }

    $form['request_timeout'] = [
      '#type' => 'number',
      '#title' => $this->t('Request timeout (seconds)'),
      '#default_value' => $config->get('request_timeout') ?? 5,
      '#min' => 1,
      '#required' => TRUE,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
    
    $enabled_endpoints = array_filter($form_state->getValue('enabled_endpoints'));
    $default_endpoint = $form_state->getValue('default_endpoint');
    
    // Ensure at least one endpoint is enabled.
    if (empty($enabled_endpoints)) {
      $form_state->setErrorByName('enabled_endpoints', $this->t('At least one endpoint must be enabled.'));
    }
    
    // Ensure the default endpoint is enabled.
    if ($default_endpoint && !isset($enabled_endpoints[$default_endpoint])) {
      $form_state->setErrorByName('default_endpoint', $this->t('The default endpoint must be enabled.'));
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);
    
    $config = $this->configFactory->getEditable('solana_integration.settings');
    $endpoints = $config->get('endpoints') ?? [];
    $enabled_endpoints = array_filter($form_state->getValue('enabled_endpoints'));
    
    // Update endpoint enabled status.
    foreach ($endpoints as $key => $endpoint) {
      $endpoints[$key]['enabled'] = isset($enabled_endpoints[$key]);
      
      // Update endpoint details if they were modified.
      if ($form_state->hasValue($key)) {
        $endpoint_values = $form_state->getValue($key);
        if (isset($endpoint_values['name'])) {
          $endpoints[$key]['name'] = $endpoint_values['name'];
        }
        if (isset($endpoint_values['url'])) {
          $endpoints[$key]['url'] = $endpoint_values['url'];
        }
      }
    }
    
    $config
      ->set('endpoints', $endpoints)
      ->set('default_endpoint', $form_state->getValue('default_endpoint'))
      ->set('request_timeout', (int) $form_state->getValue('request_timeout'))
      ->save();
  }
}
