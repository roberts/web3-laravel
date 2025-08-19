<?php

namespace Roberts\Web3Laravel\Core\Rpc;

use Illuminate\Support\Facades\Http;

class HttpClient implements ClientInterface
{
    public function __construct(
        protected string $endpoint,
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
        do {
            $attempt++;
            $res = Http::withHeaders($this->headers)
                ->timeout($this->timeout)
                ->acceptJson()
                ->post($this->endpoint, $payload);

            if ($res->successful()) {
                $body = $res->json();
                if (is_array($body) && array_key_exists('error', $body) && $body['error']) {
                    throw new \RuntimeException('RPC error: '.json_encode($body['error']));
                }

                return $body['result'] ?? null;
            }

            // If retriable (>=500 or 429), backoff
            if ($attempt <= $this->retries && ($res->status() >= 500 || $res->status() === 429)) {
                usleep($this->backoffMs * 1000);

                continue;
            }

            $res->throw();
        } while ($attempt <= $this->retries);

        throw new \RuntimeException('RPC call failed after retries: '.$method);
    }
}
