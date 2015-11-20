<?php

namespace Tygh\Shippings;

use Tygh\Registry;

class Package
{

    /**
     * Get package information
     *
     * @param  array $group Group information
     * @return array Package information
     */
    public static function getPackageInfo($group)
    {
        $package_info = array();
        $package_info['C'] = 0;
        $package_info['W'] = 0;
        $package_info['I'] = 0;
        $package_info['shipping_freight'] = 0;

        if (is_array($group['products'])) {
            foreach ($group['products'] as $key_product => $product) {
                if (($product['is_edp'] == 'Y' && $product['edp_shipping'] != 'Y') || !empty($product['free_shipping']) && $product['free_shipping'] == 'Y') {
                    continue;
                }

                if (!empty($product['exclude_from_calculate'])) {
                    $product_price = 0;

                } elseif (!empty($product['subtotal'])) {
                    $product_price = $product['subtotal'];

                } elseif (!empty($product['price'])) {
                    $product_price = $product['price'];

                } elseif (!empty($product['base_price'])) {
                    $product_price = $product['base_price'];

                } else {
                    $product_price = 0;
                }

                $package_info['C'] += $product_price;
                $package_info['W'] += !empty($product['weight']) ? $product['weight'] * $product['amount'] : 0;
                $package_info['I'] += $product['amount'];
                if (isset($product['shipping_freight'])) {
                    $package_info['shipping_freight'] += $product['shipping_freight'] * $product['amount'];
                }
            }
        }

        $package_info['W'] = !empty($package_info['W']) ? sprintf("%.2f", $package_info['W']) : '0.01';

        $package_groups = array(
            'personal' => array(),
            'global' => array(
                'products' => array(),
                'amount' => 0,
            ),
        );
        foreach ($group['products'] as $cart_id => $product) {
            if (empty($product['shipping_params']) || (empty($product['shipping_params']['min_items_in_box']) && empty($product['shipping_params']['max_items_in_box']))) {
                if (!(($product['is_edp'] == 'Y' && $product['edp_shipping'] != 'Y') || !empty($product['free_shipping']) && $product['free_shipping'] == 'Y')) {
                    $package_groups['global']['products'][$cart_id] = $product['amount'];
                    $package_groups['global']['amount'] += $product['amount'];
                }

            } else {
                if (!isset($package_groups['personal'][$product['product_id']])) {
                    $package_groups['personal'][$product['product_id']] = array(
                        'shipping_params' => $product['shipping_params'],
                        'amount' => 0,
                        'products' => array(),
                    );
                }

                if (!(($product['is_edp'] == 'Y' && $product['edp_shipping'] != 'Y') || !empty($product['free_shipping']) && $product['free_shipping'] == 'Y')) {
                    $package_groups['personal'][$product['product_id']]['amount'] += $product['amount'];
                    $package_groups['personal'][$product['product_id']]['products'][$cart_id] = $product['amount'];
                }
            }
        }

        // Divide the products into a separate packages
        $packages = array();

        if (!empty($package_groups['personal'])) {
            foreach ($package_groups['personal'] as $product_id => $package_products) {

                while ($package_products['amount'] > 0) {
                    if (!empty($package_products['shipping_params']['min_items_in_box']) && $package_products['amount'] < $package_products['shipping_params']['min_items_in_box']) {
                        $full_package_size = 0;

                        list($package_products_pack, $package_size) = self::_getPackageByAmount($package_products['amount'], $package_products['products']);

                        foreach ($package_products_pack as $cart_id => $amount) {
                            $package_groups['global']['products'][$cart_id] = isset($package_groups['global']['products'][$cart_id]) ? $package_groups['global']['products'][$cart_id] : 0;
                            $package_groups['global']['products'][$cart_id] += $amount;
                            $package_groups['global']['amount'] += $amount;

                            $full_package_size += $amount;
                        }
                    } else {
                        $amount = empty($package_products['shipping_params']['max_items_in_box']) ? $package_products['amount'] : $package_products['shipping_params']['max_items_in_box'];

                        $pack_products = $package_products['products'];
                        $full_package_size = 0;

                        do {
                            list($package_products_pack, $package_size) = self::_getPackageByAmount($amount, $pack_products);

                            $packages[] = array(
                                'shipping_params' => $package_products['shipping_params'],
                                'products' => $package_products_pack,
                                'amount' => array_sum($package_products_pack),
                            );

                            $full_package_size += array_sum($package_products_pack);

                            $package_size -= array_sum($package_products_pack);
                            foreach ($package_products_pack as $cart_id => $_pack_amount) {
                                $pack_products[$cart_id] -= $_pack_amount;
                                if ($pack_products[$cart_id] <= 0) {
                                    unset($pack_products[$cart_id]);
                                }
                            }

                        } while ($package_size > 0);

                        // Re-check package (amount, min_amount, max_amount)
                        foreach ($packages as $package_id => $package) {
                            $valid = true;

                            if (!empty($package['shipping_params']['min_items_in_box']) && $package['amount'] < $package['shipping_params']['min_items_in_box']) {
                                $valid = false;
                            }

                            if (!empty($package['shipping_params']['max_items_in_box']) && $package['amount'] > $package['shipping_params']['max_items_in_box']) {
                                $valid = false;
                            }

                            if (!$valid) {
                                foreach ($package['products'] as $cart_id => $amount) {
                                    if (!isset($package_groups['global']['products'][$cart_id])) {
                                        $package_groups['global']['products'][$cart_id] = 0;
                                    }

                                    if (!isset($package_groups['global']['amount'])) {
                                        $package_groups['global']['amount'] = 0;
                                    }

                                    $package_groups['global']['products'][$cart_id] += $amount;
                                    $package_groups['global']['amount'] += $amount;
                                }

                                unset($packages[$package_id]);
                            }
                        }
                    }

                    // Decrease the current product amount in the global package groups
                    foreach ($package_products_pack as $cart_id => $amount) {
                        $package_products['products'][$cart_id] -= $amount;
                    }
                    $package_products['amount'] -= $full_package_size;
                }

            }
        }

        if (!empty($package_groups['global']['products'])) {
            $packages[] = $package_groups['global'];
        }

        // Calculate the package additional info (weight, cost)
        foreach ($packages as $package_id => $package) {
            $weight = 0;
            $cost = 0;

            foreach ($package['products'] as $cart_id => $amount) {
                $_weight = !empty($group['products'][$cart_id]['weight']) ? $group['products'][$cart_id]['weight'] : 0;
                $price = !empty($group['products'][$cart_id]['price']) ? $group['products'][$cart_id]['price'] : !empty($group['products'][$cart_id]['base_price']) ? $group['products'][$cart_id]['base_price'] : 0;
                $weight += $_weight * $amount;
                $cost += $price * $amount;
            }

            $packages[$package_id]['weight'] = !empty($weight) ? $weight : 0.1;
            $packages[$package_id]['cost'] = $cost;
        }

        $package_info['packages'] = $packages;

        return $package_info;
    }

    /**
     * Get package by amount
     *
     * @param  array $amount   Amount products in package group
     * @param  array $products Products list in package group
     * @return array Products list and package size
     */
    private static function _getPackageByAmount($amount, $products)
    {
        $data = array();
        $package_size = 0;

        foreach ($products as $cart_id => $product_amount) {
            if ($product_amount == 0 || $amount == 0) {
                continue;
            }
            $data[$cart_id] = min($product_amount, $amount);
            $package_size += $product_amount;
            $amount -= $product_amount;

            if ($amount <= 0) {
                break;
            }
        }

        return array($data, $package_size);
    }
}
