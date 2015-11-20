<?php

namespace Tygh\Shippings\Services;

use Tygh\Shippings\IService;
use Tygh\Http;

/**
 * Qwintry shipping service
 */
class Qwintry implements IService
{
    /**
     * Sets data to internal class variable
     *
     * @param array $shipping_info
     */
    public function prepareData($shipping_info)
    {
        $this->_shipping_info = $shipping_info;
    }

    /**
     * Gets shipping cost and information about possible errors
     *
     * @param  string $resonse Reponse from Shipping service server
     * @return array  Shipping cost and errors
     */
    public function processResponse($response)
    {
        $return = array(
            'cost' => false,
            'error' => false,
            'delivery_time' => false,
        );

        $rates = $this->processRates($response);

        if (isset($rates)) {
            $return['cost'] = $rates;
        } else {
            $return['error'] = $this->processErrors($response);
        }

        return $return;
    }

    /**
     * Gets error message from shipping service server
     *
     * @param  string $resonse Reponse from Shipping service server
     * @return string Text of error or false if no errors
     */
    public function processErrors($response)
    {
        if(!$response || $response->success) return false;

        return $response->errorCode . ': ' . $response->errorMessage;
    }

    /**
     * Gets shipping service rate
     *
     * @param  string $reponse           Reponse from Shipping service server
     * @return float  Shipping service rate of false of rate was not found
     */
    public function processRates($response)
    {
        if(!$response || empty($response->success) || !$response->success) {
            if($this->_get_type() == 'courier') return false;
            $this->_set_type('courier');
            return $this->processRates($this->getSimpleRates());
        }

        return $response->result->total;
    }

    /**
     * Checks if shipping service allows to use multithreading
     *
     * @return bool true if allow
     */
    public function allowMultithreading()
    {
        return false;
    }

    /**
     * Prepare request information
     *
     * @return array Prepared data
     */
    public function getRequestData()
    {
        $weight_data = fn_expand_weight($this->_shipping_info['package_info']['W']);
        $package_cost = $this->_shipping_info['package_info']['C'];

        $shipping_settings = $this->_shipping_info['service_params'];

        $pounds = $weight_data['pounds'];

        $location = $this->_shipping_info['package_info']['location'];

        $type = $this->_get_type();

        $currencies = array_keys(fn_get_currencies());

        if(in_array('USD', $currencies)){
            $package_cost = fn_format_price_by_currency($package_cost, CART_PRIMARY_CURRENCY, 'USD');
        } elseif(CART_PRIMARY_CURRENCY == 'EUR') {
            $package_cost = $package_cost * 1.097;
        } elseif(CART_PRIMARY_CURRENCY == 'RMB') {
            $package_cost = $package_cost * 1.157;
        }

        if($type == 'courier') {
            $data = array(
                'params' => array(
                    'method' => 'qwair',
                    'hub_code' => empty($shipping_settings['hub']) ? 'DE1' : $shipping_settings['hub'],
                    'insurance' => false,
                    'retail_pricing' => false,
                    'weight' => $pounds > 0.1 ? $pounds : (empty($shipping_settings['default_weight']) ? 4 : $shipping_settings['default_weight']),
                    'items_value' => $package_cost,
                    'addr_country' => $location['country'],
                    'addr_zip' => $location['zipcode'],
                    'addr_line1' => $location['address'],
                    'addr_line2' => empty($location['address_2']) ? '' : $location['address_2'],
                    'addr_city' => $location['city'],
                    'addr_state' => fn_get_state_name($location['state'], $location['country'])
                )
            );
        } elseif($type == 'pickup'){
            $data = array(
                'params' => array(
                    'insurance' => false,
                    'retail_pricing' => false,
                    'weight' => $pounds > 0.1 ? $pounds : (empty($shipping_settings['default_weight']) ? 4 : $shipping_settings['default_weight']),
                    'items_value' => $package_cost,
                    'delivery_pickup' => $this->_get_pickup_point()
                )
            );
        }
        $request_data = array(
            'method' => 'get',
            'url' => 'cost',
            'data' => $data,
        );
        return $request_data;
    }

    /**
     * Process simple request to shipping service server
     *
     * @return string Server response
     */
    public function getSimpleRates()
    {
        $data = $this->getRequestData();
        $response = fn_qwintry_send_api_request($data['url'], $data['data'], $this->_shipping_info['service_params']);

        return $response;
    }

    private function _get_type(){
        $cart = $this->_get_cart();
        $keys = $this->_shipping_info['keys'];
        return empty($cart['qwintry'][$keys['group_key']][$keys['shipping_id']]['type']) ? 'courier' : $cart['qwintry'][$keys['group_key']][$keys['shipping_id']]['type'];
    }
    private function _set_type($type){
        $keys = $this->_shipping_info['keys'];
        $_SESSION['cart']['qwintry'][$keys['group_key']][$keys['shipping_id']]['type'] = $type;
        return $type;
    }

    private function _get_pickup_point(){
        $cart = $this->_get_cart();
        $keys = $this->_shipping_info['keys'];
        return empty($cart['qwintry'][$keys['group_key']][$keys['shipping_id']]['point']) ? '' : $cart['qwintry'][$keys['group_key']][$keys['shipping_id']]['point'];
    }

    private function _get_cart()
    {
        return $_SESSION['cart'];
    }
}
