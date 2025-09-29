<?php

namespace Drupal\solana_integration\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Plugin implementation of the 'solana_wallet_link' formatter.
 *
 * @FieldFormatter(
 *  id = "solana_wallet_link",
 *  label = @Translation("Solana Wallet Link"),
 *  field_types = {
 *      "solana_wallet"
 *  }
 * )
 */
class SolanaWalletFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return [
      'link_to_explorer' => TRUE,
      'trim_address' => TRUE,
      'trim_length' => 4,
      'explorer' => 'solscan',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $elements = parent::settingsForm($form, $form_state);

    $elements['link_to_explorer'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Link to a blockchain explorer'),
      '#default_value' => $this->getSetting('link_to_explorer'),
    ];

    $elements['explorer'] = [
      '#type' => 'select',
      '#title' => $this->t('Explorer'),
      '#options' => [
        'solscan' => 'Solscan',
        'solanafm' => 'Solana.fm',
        'official' => 'explorer.solana.com',
      ],
      '#default_value' => $this->getSetting('explorer'),
      '#states' => [
        'visible' => [':input[name$="[link_to_explorer]"]' => ['checked' => TRUE]],
      ],
    ];

    $elements['trim_address'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Abbreviate the address (e.g., 43rW...y5kC)'),
      '#default_value' => $this->getSetting('trim_address'),
    ];

    $elements['trim_length'] = [
      '#type' => 'number',
      '#title' => $this->t('Number of characters to show at start/end'),
      '#default_value' => $this->getSetting('trim_length'),
      '#min' => 2,
      '#max' => 10,
      '#states' => [
        'visible' => [':input[name$="[trim_address]"]' => ['checked' => TRUE]],
      ],
    ];

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(): array {
    $summary = [];
    if ($this->getSetting('link_to_explorer')) {
      $summary[] = $this->t('Links to @explorer', ['@explorer' => $this->getSetting('explorer')]);
    }
    else {
      $summary[] = $this->t('Does not link to explorer');
    }
    if ($this->getSetting('trim_address')) {
      $len = $this->getSetting('trim_length');
      $summary[] = $this->t('Abbreviated to @len...@len characters', ['@len' => $len]);
    }
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $elements = [];
    $link_to_explorer = $this->getSetting('link_to_explorer');
    $trim = $this->getSetting('trim_address');
    $trim_length = (int) $this->getSetting('trim_length');
    $explorer = $this->getSetting('explorer');

    foreach ($items as $delta => $item) {
      $address = $item->address;
      if (!$address) {
        continue;
      }

      $display_address = $address;
      if ($trim && strlen($address) > ($trim_length * 2)) {
        $display_address = substr($address, 0, $trim_length) . '...' . substr($address, -$trim_length);
      }

      if ($link_to_explorer) {
        $url = $this->buildExplorerUrl($address, $explorer);
        $elements[$delta] = [
          '#type' => 'link',
          '#title' => $display_address,
          '#url' => Url::fromUri($url),
          '#options' => ['attributes' => ['target' => '_blank', 'title' => $this->t('View on explorer: @address', ['@address' => $address])]],
        ];
      }
      else {
        $elements[$delta] = ['#plain_text' => $display_address];
      }
    }

    return $elements;
  }

  /**
   * Helper function to build the explorer URL.
   */
  protected function buildExplorerUrl(string $address, string $explorer): string {
    return match ($explorer) {
      'solanafm' => "https://solana.fm/address/{$address}",
      'official' => "https://explorer.solana.com/address/{$address}",
      default => "https://solscan.io/account/{$address}",
    };
  }
}