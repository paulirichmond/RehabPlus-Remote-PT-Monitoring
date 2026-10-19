<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class ApiAuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        // The key must come from the environment (.env); there is deliberately no built-in default.
        $expected = (string) env('API_KEY', '');

        if ($expected === '') {
            return service('response')
                ->setStatusCode(503)
                ->setJSON(['status' => 'error', 'message' => 'API key is not configured on this server.']);
        }

        $key = (string) ($request->getHeaderLine('X-API-KEY') ?: $request->getGet('api_key'));

        if (! hash_equals($expected, $key)) {
            return service('response')
                ->setStatusCode(401)
                ->setJSON(['status' => 'error', 'message' => 'Unauthorized']);
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null) {}
}
