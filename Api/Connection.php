<?php
/*
 * The MIT License
 *
 * Copyright (c) 2019 Payout, s.r.o. (https://payout.one/)
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */

namespace Payout\Payment\Api;

use CurlHandle;
use Exception;

/**
 * Class Connection
 *
 * HTTP connection using cURL library.
 *
 * @package Payout
 * @since   0.1.0
 */
class Connection
{
    const string TYPE_JSON = 'application/json';

    /**
     * @var string $base_url API base URL
     * @var $curl
     * @var array $headers HTTP request headers
     * @var mixed $response HTTP response
     */
    private string $base_url;
    private string $token;
    private CurlHandle|false $curl;
    private array $headers = array();
    private mixed $response;

    /**
     * Connection constructor.
     *
     * @param string $base_url
     */
    public function __construct(string $base_url)
    {
        $this->base_url = $base_url;
        $this->curl = curl_init();

        curl_setopt_array(
            $this->curl,
            array(
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1
            )
        );
    }

    /**
     * Add a custom header to the request.
     *
     * @param string $header HTTP request field name
     * @param string $value HTTP request field value
     */
    public function addHeader(string $header, string $value): void
    {
        $this->headers[$header] = "$header: $value";
    }

    /**
     * remove header.
     *
     * @param string $header HTTP request field name
     * @param string $value HTTP request field value
     */
    private function removeHeader(string $header, string $value): void
    {
        if ($index = array_search("$header: $value", $this->headers)) {
            unset($this->headers[$index]);
        }
    }

    /**
     *  Authenticate API connection. Make an HTTP POST request to the
     *  authorization endpoint  and obtain access token.
     * @param string $url
     * @param string $client_id
     * @param string $client_secret
     * @param int|null $timeout
     * @return mixed
     * @throws Exception
     */
    public function authenticate(string $url, string $client_id, string $client_secret, int $timeout = null): mixed
    {
        if (isset($timeout)) {
            curl_setopt($this->curl, CURLOPT_TIMEOUT, $timeout);
        }

        $this->initializeRequest();

        $credentials = json_encode(
            array(
                'client_id' => $client_id,
                'client_secret' => $client_secret
            )
        );

        curl_setopt($this->curl, CURLOPT_URL, $this->base_url . $url);
        curl_setopt($this->curl, CURLOPT_POSTFIELDS, $credentials);

        $this->response = curl_exec($this->curl);

        if (isset($timeout)) {
            curl_setopt($this->curl, CURLOPT_TIMEOUT, 0);
        }
        return $this->handleResponse();
    }

    /**
     * Make an HTTP POST request to the specified endpoint.
     *
     * @param string $url URL to which we send the request
     * @param mixed $body Data payload (JSON string or raw data)
     * @param array $headers
     * @param int|null $timeout
     * @return mixed
     * @throws Exception
     */
    public function post(string $url, mixed $body, array $headers = [], int $timeout = null): mixed
    {
        if (isset($timeout)) {
            curl_setopt($this->curl, CURLOPT_TIMEOUT, $timeout);
        }

        $this->addHeader('Authorization', 'Bearer ' . $this->token);
        foreach ($headers as $key => $header) {
            $this->addHeader($key, $header);
        }
        $this->initializeRequest();

        if (!is_string($body)) {
            $body = json_encode($body);
        }

        curl_setopt($this->curl, CURLOPT_URL, $this->base_url . $url);
        curl_setopt($this->curl, CURLOPT_POSTFIELDS, $body);

        $this->response = curl_exec($this->curl);

        foreach ($headers as $key => $header) {
            $this->removeHeader($key, $header);
        }
        curl_setopt($this->curl, CURLOPT_HTTPHEADER, $this->headers);

        if (isset($timeout)) {
            curl_setopt($this->curl, CURLOPT_TIMEOUT, 0);
        }
        return $this->handleResponse();
    }

    /**
     * Make an HTTP GET request to the specified endpoint.
     *
     * @param string $query Optional array of query string parameters
     * @param int|null $timeout
     * @return mixed
     * @throws Exception
     */
    public function get(string $query = '', int $timeout = null): mixed
    {
        if (isset($timeout)) {
            curl_setopt($this->curl, CURLOPT_TIMEOUT, $timeout);
        }

        $this->addHeader('Authorization', 'Bearer ' . $this->token);
        $this->initializeRequest();

        curl_setopt($this->curl, CURLOPT_CUSTOMREQUEST, 'GET');
        curl_setopt($this->curl, CURLOPT_URL, $this->base_url . $query);
        curl_setopt($this->curl, CURLOPT_POST, false);
        curl_setopt($this->curl, CURLOPT_PUT, false);
        curl_setopt($this->curl, CURLOPT_HTTPGET, true);

        $this->response = curl_exec($this->curl);

        if (isset($timeout)) {
            curl_setopt($this->curl, CURLOPT_TIMEOUT, 0);
        }
        return $this->handleResponse();
    }

    /**
     * Clear previously cached request data and prepare for
     * making a fresh request.
     */
    private function initializeRequest(): void
    {
        $this->response = '';
        $this->addHeader('Content-Type', self::TYPE_JSON);
        $this->addHeader('Accept', self::TYPE_JSON);

        curl_setopt($this->curl, CURLOPT_HTTPHEADER, $this->headers);
    }

    /**
     * Check the response for possible errors and handle the response body returned.
     *
     * @return mixed the value encoded in json in appropriate PHP type.
     * @throws Exception
     */
    private function handleResponse(): mixed
    {
        if (curl_error($this->curl)) {
            throw new Exception(__('Payout error') . ': ' . curl_error($this->curl));
        }

        $response = json_decode($this->response);

        if (isset($response->errors)) {
            throw new Exception(__('Payout api response error') . ': ' . json_encode($response->errors));
        }

        $responseHttpCode = curl_getinfo($this->curl, CURLINFO_HTTP_CODE);
        if (!in_array($responseHttpCode, [200, 201])) {
            throw new Exception(__('Payout api response error') . ', ' . __('non ok / created http response code') . ': ' . $responseHttpCode);
        }

        if (isset($response->token)) {
            $this->token = $response->token;
        }

        return $response;
    }
}
