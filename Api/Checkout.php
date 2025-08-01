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

use Exception;

/**
 * Class Checkout
 *
 * Checkout object to verify input checkout data.
 *
 * @package Payout
 * @since   0.2.0
 */
class Checkout
{
    /**
     * Verify input data a return as array with required and optional attributes.
     *
     * @param $data
     *
     * @return array
     * @throws Exception
     */
    public function create($data): array
    {
        if (!is_array($data)) {
            throw new Exception(__('Payout error: Wrong checkout parameters.'));
        }

        $checkout_required = array(
            'amount',
            'currency',
            'customer',
            'external_id',
            'redirect_url'
        );

        foreach ($checkout_required as $required_attribute) {
            if (!key_exists($required_attribute, $data)) {
                throw new Exception(__('Payout error') . ': ' . __('Missing required parameter') . ' "$required_attribute"');
            }
        }

        $customer_required = array(
            'first_name',
            'last_name',
            'email'
        );

        foreach ($customer_required as $required_attribute) {
            if (!key_exists($required_attribute, $data['customer'])) {
                throw new Exception(__('Payout error') . ': ' . __('Missing required parameter') . ' "$required_attribute"');
            }
        }

        $checkout_data = array(
            'amount' => number_format($data['amount'] * 100, 0, '.', ''), // Amount in cents
            'currency' => $data['currency'],
            'customer' => [
                'first_name' => $data['customer']['first_name'],
                'last_name' => $data['customer']['last_name'],
                'email' => $data['customer']['email']
            ],
            'products' => $data['products'],
            'billing_address' => $data['billing_address'],
            'shipping_address' => $data['shipping_address'],
            'external_id' => strval($data['external_id']),
            'nonce' => '',
            'redirect_url' => $data['redirect_url'],
            'signature' => '',
            'idempotency_key' => $data['idempotency_key'],
        );

        if (isset($data['metadata']) && is_array($data['metadata'])) {
            $checkout_data['metadata'] = $data['metadata'];
        }

        return $checkout_data;
    }
}
