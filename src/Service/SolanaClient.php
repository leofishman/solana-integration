<?php

namespace Drupal\solana_integration\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
//use JosephOpanel\SolanaSDK\Connection;
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
   * The Solana SDK connection object.
   *
   * @var \JosephOpanel\SolanaSDK\Connection
   */
  // protected Connection $connection;

  /**
   * Constructs a new SolanaClient object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   */
  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
  ) {
    $endpoint = (string) $this->configFactory->get('solana_integration.settings')->get('rpc_endpoint');
    // $this->connection = new Connection($endpoint);
  }

  /**
   * Provides direct access to the underlying SDK connection object.
   */
  public function getConnection(): Connection {
    return $this->connection;
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
    $rpc = new SolanaRPC();
    $account = new Account($rpc);
    $block = new Block($rpc);
    $transaction = new Transaction($rpc);
    // Get the balance of an account
    $balance = $account->getBalance($pubkey, $block->getLatestBlockhash()['value']['blockhash'] ?? 'finalized');
    return $balance;
  }
}
