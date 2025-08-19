<?php

namespace Roberts\Web3Laravel\Core\Rpc;

use Illuminate\Support\Facades\Http;
use Roberts\Web3Laravel\Core\Provider\Pool;

class PooledHttpClient implements ClientInterface
{
    public function __construct(
        protected Pool $pool,
        protected int $timeout = 10,
        protected int $retries = 2,
        protected int $backoffMs = 200,
        protected array $headers = [],
    ) {}

    public function call(string $method, array $params = [])
    {
        $id = random_int(1, PHP_INT_MAX);
        $payload = [
            'jsonrpc' => '2.0',
            'id' => $id,
            'method' => $method,
            'params' => array_values($params),
        ];

        $attempt = 0;
        $lastError = null;
        do {
            $attempt++;
            $endpoint = $this->pool->pick();
            $res = Http::withHeaders(array_merge($this->headers, $endpoint->headers))
                ->timeout($this->timeout)
                ->acceptJson()
                ->post($endpoint->rpc, $payload);

            if ($res->successful()) {
                $body = $res->json();
                if (is_array($body) && array_key_exists('error', $body) && $body['error']) {
                    $lastError = new \RuntimeException('RPC error: '.json_encode($body['error']));
                } else {
                    return $body['result'] ?? null;
                }
            } else {
                $lastError = new \RuntimeException('HTTP status '.$res->status());
            }

            if ($attempt <= $this->retries) {
                usleep($this->backoffMs * 1000);
            }
        } while ($attempt <= $this->retries);

        // Bubble the last error as previous if available
        throw new \RuntimeException('RPC call failed', 0, $lastError);
    }
}
