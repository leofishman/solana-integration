<?php

namespace Drupal\solana_integration\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use JosephOpanel\SolanaSDK\SolanaRPC;
use JosephOpanel\SolanaSDK\Endpoints\JsonRPC\Account;
use JosephOpanel\SolanaSDK\Endpoints\JsonRPC\Block;
use JosephOpanel\SolanaSDK\Endpoints\JsonRPC\Transaction;

/**
 * A wrapper for the Solana PHP SDK, configured via Drupal services.
 */
class SolanaClient {

  /**
   * The Solana RPC service.
   *
   * @var \JosephOpanel\SolanaSDK\SolanaRPC
   */
  // protected SolanaRPC $rpc = new SolanaRPC();

  
  /**
   * The Solana RPC endpoint.
   *
   * @var string
   */
  protected string $endpoint;


  /**
   * Constructs a new SolanaClient object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   */
  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
  ) {}

 public function getEndpoint(): string {
    $config = $this->configFactory->get('solana_integration.settings');
    $default_endpoint_key = $config->get('default_endpoint') ?? 'mainnet';
    $endpoints = $config->get('endpoints') ?? [];
    
    // Get the URL for the default endpoint.
    if (isset($endpoints[$default_endpoint_key]['url'])) {
      return (string) $endpoints[$default_endpoint_key]['url'];
    }
    
    // Fallback to the first enabled endpoint if default is not available.
    foreach ($endpoints as $endpoint) {
      if (!empty($endpoint['enabled']) && !empty($endpoint['url'])) {
        return (string) $endpoint['url'];
      }
    }
    
    // Final fallback to mainnet if nothing else is available.
    return 'https://api.mainnet-beta.solana.com';
  }

  protected function getTimeout(): int {
    return (int) ($this->configFactory->get('solana_integration.settings')->get('request_timeout') ?? 5);
  }

  /**
   * Get the balance of a Solana account.
   *
   * @param string $pubkey
   *   The public key of the account.
   *
   * @return array|null The balance in lamports, or null on error.
   */
  public function getBalance(string $pubkey): ?array {
    $endpoint = $this->getEndpoint();
    $timeout = $this->getTimeout();
    $rpc = new SolanaRPC($endpoint, $timeout);
    $account = new Account($rpc);
    $block = new Block($rpc);
    $transaction = new Transaction($rpc);
    // Get the balance of an account
    $balance = $account->getBalance($pubkey, $block->getLatestBlockhash()['value']['blockhash'] ?? 'finalized');
    return $balance;
  }
}
