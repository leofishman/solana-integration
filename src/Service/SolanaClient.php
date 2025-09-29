<?php

namespace Drupal\solana_integration\Service;

use GuzzleHttp\ClientInterface;
use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * Simple Solana JSON-RPC client wrapper.
 */
class SolanaClient {

  public function __construct(
    private readonly ClientInterface $httpClient,
    private readonly ConfigFactoryInterface $configFactory,
  ) {}

  protected function getEndpoint(): string {
    return (string) $this->configFactory->get('solana_integration.settings')->get('rpc_endpoint');
  }

  protected function getTimeout(): int {
    return (int) ($this->configFactory->get('solana_integration.settings')->get('request_timeout') ?? 5);
  }

  /**
   * Perform a JSON-RPC call to the Solana endpoint.
   *
   * @param string $method
   *   The JSON-RPC method name.
   * @param array $params
   *   Parameters for the method.
   *
   * @return array
   *   Decoded JSON response as an associative array.
   */
  public function rpc(string $method, array $params = []): array {
    $payload = [
      'jsonrpc' => '2.0',
      'id' => 1,
      'method' => $method,
      'params' => $params,
    ];

    $response = $this->httpClient->request('POST', $this->getEndpoint(), [
      'timeout' => $this->getTimeout(),
      'headers' => [
        'Content-Type' => 'application/json',
      ],
      'body' => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
    ]);

    return json_decode((string) $response->getBody(), TRUE) ?? [];
  }
}
