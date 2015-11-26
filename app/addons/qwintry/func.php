<?php

use Tygh\Registry;
use Tygh\Languages\Languages;
use Tygh\Shippings\Package;
use Tygh\Pdf;
use Tygh\Settings;

if (!defined('BOOTSTRAP')) { die('Access denied'); }

function fn_qwintry_pre_place_order(&$cart, $allow, $product_groups)
{
    if (empty($cart['qwintry'])) return;
    foreach ($cart['product_groups'] as $gk => $group) {
        if (empty($group['chosen_shippings'])) continue;
        foreach ($group['chosen_shippings'] as $sk => $shipping) {
            if (empty($cart['qwintry'][$shipping['group_key']][$shipping['shipping_id']])) continue;
            $cart['product_groups'][$gk]['chosen_shippings'][$sk]['extra'] = $cart['qwintry'][$shipping['group_key']][$shipping['shipping_id']];
        }
    }
}

function fn_qwintry_create_shipment($order_info){
    $shipping = fn_qwintry_find_qwintry_shipping($order_info);

    if(empty($shipping)) return false;

    $shipping_settings = fn_get_shipping_params($shipping['shipping_id']);

    $package_info = fn_qwintry_get_package_from_order_info($order_info);
    $dimensions = fn_qwintry_get_biggest_package($package_info);
    $invoice = fn_qwintry_save_order_invoice($order_info['order_id']);

    if(!$dimensions) $dimensions = $shipping_settings['dimensions'];
    $weight_data = fn_expand_weight($package_info['W']);

    $pounds = $weight_data['pounds'];

    $data = array (
        'Shipment' => array (
            'first_name' => $order_info['s_firstname'],
            'last_name' => $order_info['s_lastname'],
            'phone' => empty($order_info['s_phone']) ? $order_info['phone'] : $order_info['s_phone'],
            'email' => $order_info['email'],
            'customer_notes' => $order_info['notes'],
            'weight' => $pounds > 0.1 ? $pounds : (empty($shipping_settings['default_weight']) ? 4 : $shipping_settings['default_weight']),
            'dimensions' => $dimensions['box_length'] . 'x' . $dimensions['box_width'] . 'x' . $dimensions['box_height'],
            'insurance' => false,
            'external_id' => $order_info['order_id'],
            'hub_code' => empty($shipping_settings['hub']) ? 'DE1' : $shipping_settings['hub']
        ),
        'invoices' => array(
            0 => array(
                'base64_data' => base64_encode(file_get_contents($invoice)),
                'base64_extension' => 'pdf',
            ),
        ),
    );

    $data['Shipment']['addr_line1'] = $order_info['s_address'];
    $data['Shipment']['addr_line2'] = $order_info['s_address_2'];
    $data['Shipment']['addr_zip'] = $order_info['s_zipcode'];
    $data['Shipment']['addr_state'] = fn_get_state_name($order_info['s_state'], $order_info['s_country']);
    $data['Shipment']['addr_city'] = $order_info['s_city'];
    $data['Shipment']['addr_country'] = $order_info['s_country'];

    if(empty($shipping['extra']['type']) || $shipping['extra']['type'] == 'courier'){
        $data['Shipment']['delivery_type'] = 'courier';
    } elseif($shipping['extra']['type'] == 'pickup' && !empty($shipping['extra']['point'])){
        $data['Shipment']['delivery_type'] = 'pickup';
        $data['Shipment']['delivery_pickup'] = $shipping['extra']['point'];
    }

    if($shipping_settings['mode'] == 'test'){
        $data['Shipment']['test'] = true;
    }

    $cart = fn_qwintry_fn_form_cart($order_info);

    foreach($cart['products'] as $product){
        $rus_name = fn_get_product_name($product['product_id'], 'RU');
        $data['items'][] = array(
            'descr' => $product['product'],
            'descr_ru' => empty($rus_name) ? $product['product'] : $rus_name,
            'count' => $product['amount'],
            'line_value' => fn_qwintry_get_price($product['price']),
            'line_weight' => empty($product['weight']) ? 0.1 : $product['weight'],
            'link' => fn_url('products.view&product_id=' . $product['product_id'], 'C')
        );
    }

    $result = fn_qwintry_send_api_request('package-create', $data, $shipping_settings);

    if(!$result || empty($result->success) || !$result->success || empty($result->result->tracking)) {
        if(empty($result->errorMessage)) return false;
        return array('[error]' => (string) $result->errorMessage);
    }

    $shipment_data = array(
        'order_id' => $order_info['order_id'],
        'shipping_id' => $shipping['shipping_id'],
        'tracking_number' => $result->result->tracking
    );

    fn_qwintry_update_shipment($shipment_data, 0, 0, true);

    if(fn_qwintry_save_label($order_info['order_id']. '.pdf', $result->result->tracking, $shipping_settings) !== false){
        return true;
    }

    return false;
}

function fn_qwintry_get_price($price){
    $currencies = array_keys(fn_get_currencies());
    if(in_array('USD', $currencies)){
        $price = fn_format_price_by_currency($price, CART_PRIMARY_CURRENCY, 'USD');
    } elseif(CART_PRIMARY_CURRENCY == 'EUR') {
        $price = $price * 1.097;
    } elseif(CART_PRIMARY_CURRENCY == 'RMB') {
        $price = $price * 1.157;
    }
    return $price;
}

function fn_qwintry_find_qwintry_shipping($order_info){
    foreach($order_info['shipping'] as $shipping){
        if($shipping['module'] == 'qwintry'){
            return $shipping;
        }
    }

    return false;
}

function fn_qwintry_get_package_from_order_info($order_info){
   return Package::getPackageInfo(fn_qwintry_fn_form_cart($order_info));
}

function fn_qwintry_fn_form_cart($order_info){
    fn_clear_cart($cart, true);
    $customer_auth = fn_fill_auth();
    fn_form_cart($order_info['order_id'], $cart, $customer_auth, array());

    list ($cart_products,) = fn_calculate_cart_content($cart, $customer_auth, 'E', false, 'F', false);

    if (!empty($cart_products)) {
        foreach ($cart_products as $k => $v) {
            fn_gather_additional_product_data($cart_products[$k], false, false, true, false);
        }
    }
    $cart['products'] = $cart_products;

    return $cart;
}

function fn_qwintry_get_biggest_package($package_info){
    if (empty($package_info['packages'])) return false;
    $max_volume = 0;
    foreach($package_info['packages'] as $package){
        if(empty($package['shipping_params']) || empty($package['shipping_params']['box_length']) || empty($package['shipping_params']['box_width']) || empty($package['shipping_params']['box_height'])) continue;
        $volume = $package['shipping_params']['box_length'] * $package['shipping_params']['box_width'] * $package['shipping_params']['box_height'];
        if ($volume > $max_volume) {
            $max_volume = $volume;
            $dimensions = $package['shipping_params'];
        }
    }
    return empty($dimensions)? false : $dimensions;
}

function fn_qwintry_save_order_invoice($order_id, $area = AREA, $lang_code = CART_LANGUAGE)
{
    $view = Tygh::$app['view'];
    $html = array();

    $view->assign('order_status_descr', fn_get_simple_statuses(STATUSES_ORDER, true, true));
    $view->assign('profile_fields', fn_get_profile_fields('I'));

    $order_info = fn_get_order_info($order_id, false, true, false, true);

    if (empty($order_info)) {
        return;
    }

    if (fn_allowed_for('MULTIVENDOR')) {
        $view->assign('take_surcharge_from_vendor', fn_take_payment_surcharge_from_vendor($order_info['products']));
    }

    list($shipments) = fn_get_shipments_info(array('order_id' => $order_info['order_id'], 'advanced_info' => true));
    $use_shipments = !fn_one_full_shipped($shipments);

    $view->assign('order_info', $order_info);
    $view->assign('shipments', $shipments);
    $view->assign('use_shipments', $use_shipments);
    $view->assign('payment_method', fn_get_payment_data((!empty($order_info['payment_method']['payment_id']) ? $order_info['payment_method']['payment_id'] : 0), $order_info['order_id'], $lang_code));
    $view->assign('order_status', fn_get_status_data($order_info['status'], STATUSES_ORDER, $order_info['order_id'], $lang_code, $order_info['company_id']));
    $view->assign('status_settings', fn_get_status_params($order_info['status']));

    $view->assign('company_data', fn_get_company_placement_info($order_info['company_id'], $lang_code));

    fn_disable_live_editor_mode();
    $html[] = $view->displayMail('orders/print_invoice.tpl', false, $area, $order_info['company_id'], $lang_code);


    $filename = QWINTRY_DIR_INVOICES . $order_id . '.pdf';

    if (Pdf::render($html, $filename, true)) {
        return $filename;
    }

    return false;
}

function fn_qwintry_create_shipping_service(){
    $service = array(
        'status' => 'A',
        'module' => 'qwintry',
        'code' => 'Qwintry Air'
    );
    $service_id = db_query('INSERT INTO ?:shipping_services ?e', $service);

    $service_description = array(
        'service_id' => $service_id,
        'description' => 'Qwintry Air'
    );
    foreach (Languages::getAll() as $service_description['lang_code'] => $_v) {
        db_query("INSERT INTO ?:shipping_service_descriptions ?e", $service_description);
    }

    $section_id = db_get_field('SELECT section_id FROM ?:settings_sections WHERE name = ?s', 'Shippings');
    $setting = array(
        'edition_type' => 'ROOT',
        'name' => 'qwintry_enabled',
        'section_id' => $section_id,
        'section_tab_id' => 0,
        'type' => 'C',
        'value' => 'Y',
        'position' => 35,
        'is_global' => 'N',
        'handler' => '',
        'parent_id' => 0
    );
    $setting_id = db_query('INSERT INTO ?:settings_objects ?e', $setting);

    $setting_description = array(
        'object_id' => $setting_id,
        'object_type' => 'O',
        'value' => 'Enable Qwintry Air',
        'tooltip' => ''
    );
    foreach (Languages::getAll() as $setting_description['lang_code'] => $_v) {
        db_query("INSERT INTO ?:settings_descriptions ?e", $setting_description);
    }

}

function fn_qwintry_remove_shipping_service(){
    $service_ids = db_get_fields('SELECT service_id FROM ?:shipping_services WHERE module = ?s', 'qwintry');

    if ($service_ids) {
        db_query('DELETE FROM ?:shipping_services WHERE service_id IN (?n)', $service_ids);
        db_query('DELETE FROM ?:shipping_service_descriptions WHERE service_id IN (?n)', $service_ids);
    }

    $setting_id = db_get_field('SELECT object_id FROM ?:settings_objects WHERE name = ?s', 'qwintry_enabled');
    if ($setting_id) {
        db_query('DELETE FROM ?:settings_objects WHERE object_id = ?i', $setting_id);
        db_query('DELETE FROM ?:settings_descriptions WHERE object_id = ?i', $setting_id);
    }
}

function fn_qwintry_get_hubs($shipping_id){
    $result = fn_qwintry_send_api_request('hubs-list', array(), fn_get_shipping_params($shipping_id));
    if(!$result && !$result->success && empty($result->results)) return false;
    foreach($result->results as $hub){
        $hubs[] = array(
            'code' => (string) $hub->code,
            'name' => (string) $hub->name
        );
    }
    return empty($hubs) ? false : $hubs;
}

function fn_qwintry_get_pickup_points($country, $shipping_id){
    $result = fn_qwintry_send_api_request('locations-list', array(), fn_get_shipping_params($shipping_id));
    if(!$result && !$result->success && empty($result->result)) return false;
    foreach($result->result as $city_name => $city){
        if(empty($city->pickup_points) || $city->country != $country) continue;
        foreach($city->pickup_points as $code => $point){
            $points[] = array(
                'code' => (string) $code,
                'name' => (string) $city_name . '. ' . (string) $point->addr
            );
        }

    }
    return empty($points) ? false : $points;
}

function fn_qwintry_get_address_by_pickup_point($pickup_point, $shipping_id){
    $result = fn_qwintry_send_api_request('locations-list', array(), fn_get_shipping_params($shipping_id));
    if(!$result && !$result->success && empty($result->result)) return false;
    foreach($result->result as $city_name => $city){
        if(empty($city->pickup_points)) continue;
        foreach($city->pickup_points as $code => $point){
            if($code == $pickup_point) return (string) $city_name . '. ' . (string) $point->addr;
        }

    }
    return false;
}

function fn_qwintry_get_country_data($country, $shipping_id){
    $result = fn_qwintry_send_api_request("countries-list?country=" . $country, array(), fn_get_shipping_params($shipping_id));
    if(!$result && !$result->success && empty($result->result) && empty($result->result->{$country})) return false;
    foreach($result->result->{$country} as $key => $row){
        if (empty($row) || !is_string($row)) continue;

        $data[] = array(
            'header' => __('qwintry_' . $key),
            'content' => $row,
            'bold' => in_array($key, array('lazy_workflow'))
        );
    }
    return empty($data) ? false : $data;
}

function fn_qwintry_update_shipment($shipment_data, $shipment_id = 0, $group_key = 0, $all_products = false, $force_notification = array())
{

    if (!empty($shipment_id)) {
        $arow = db_query("UPDATE ?:shipments SET tracking_number = ?s, carrier = ?s WHERE shipment_id = ?i", $shipment_data['tracking_number'], $shipment_data['carrier'], $shipment_id);
        if ($arow === false) {
            fn_set_notification('E', __('error'), __('object_not_found', array('[object]' => __('shipment'))),'','404');
            $shipment_id = false;
        }
    } else {

        if (empty($shipment_data['order_id']) || empty($shipment_data['shipping_id'])) {
            return false;
        }

        $order_info = fn_get_order_info($shipment_data['order_id'], false, true, true);
        $use_shipments = (Settings::instance()->getValue('use_shipments', '', $order_info['company_id']) == 'Y') ? true : false;

        if (!$use_shipments && empty($shipment_data['tracking_number']) && empty($shipment_data['tracking_number'])) {
            return false;
        }

        if ($all_products) {
            foreach ($order_info['product_groups'] as $group) {
                foreach ($group['products'] as $item_key => $product) {

                    if (!empty($product['extra']['group_key'])) {
                        if ($group_key == $product['extra']['group_key']) {
                            $shipment_data['products'][$item_key] = $product['amount'];
                        }
                    } elseif ($group_key == 0) {
                        $shipment_data['products'][$item_key] = $product['amount'];
                    }
                }
            }
        }

        if (!empty($shipment_data['products']) && fn_check_shipped_products($shipment_data['products'])) {

            fn_set_hook('create_shipment', $shipment_data, $order_info, $group_key, $all_products);

            foreach ($shipment_data['products'] as $key => $amount) {
                if (isset($order_info['products'][$key])) {
                    $amount = intval($amount);

                    if ($amount > ($order_info['products'][$key]['amount'] - $order_info['products'][$key]['shipped_amount'])) {
                        $shipment_data['products'][$key] = $order_info['products'][$key]['amount'] - $order_info['products'][$key]['shipped_amount'];
                    }
                }
            }

            if (fn_check_shipped_products($shipment_data['products'])) {

                $shipment_data['timestamp'] = time();
                $shipment_id = db_query("INSERT INTO ?:shipments ?e", $shipment_data);

                foreach ($shipment_data['products'] as $key => $amount) {

                    if ($amount == 0) {
                        continue;
                    }

                    $_data = array(
                        'item_id' => $key,
                        'shipment_id' => $shipment_id,
                        'order_id' => $shipment_data['order_id'],
                        'product_id' => $order_info['products'][$key]['product_id'],
                        'amount' => $amount,
                    );

                    db_query("INSERT INTO ?:shipment_items ?e", $_data);
                }

                if (fn_check_permissions('orders', 'update_status', 'admin') && !empty($shipment_data['order_status'])) {
                    fn_change_order_status($shipment_data['order_id'], $shipment_data['order_status']);
                }

                /**
                 * Called after new shipment creation.
                 *
                 * @param array $shipment_data Array of shipment data.
                 * @param array $order_info Shipment order info
                 * @param int $group_key Group number
                 * @param bool $all_products
                 * @param int $shipment_id Created shipment identifier
                 */
                fn_set_hook('create_shipment_post', $shipment_data, $order_info, $group_key, $all_products, $shipment_id);

                if (!empty($force_notification['C'])) {
                    $shipment = array(
                        'shipment_id' => $shipment_id,
                        'timestamp' => $shipment_data['timestamp'],
                        'shipping' => db_get_field('SELECT shipping FROM ?:shipping_descriptions WHERE shipping_id = ?i AND lang_code = ?s', $shipment_data['shipping_id'], $order_info['lang_code']),
                        'tracking_number' => $shipment_data['tracking_number'],
                        'carrier' => $shipment_data['carrier'],
                        'comments' => $shipment_data['comments'],
                        'items' => $shipment_data['products'],
                    );

                    Mailer::sendMail(array(
                        'to' => $order_info['email'],
                        'from' => 'company_orders_department',
                        'data' => array(
                            'shipment' => $shipment,
                            'order_info' => $order_info,
                        ),
                        'tpl' => 'shipments/shipment_products.tpl',
                        'company_id' => $order_info['company_id'],
                    ), 'C', $order_info['lang_code']);

                }

                fn_set_notification('N', __('notice'), __('shipment_has_been_created'));
            }

        } else {
            fn_set_notification('E', __('error'), __('products_for_shipment_not_selected'));
        }

    }

    return $shipment_id;
}

function fn_qwintry_send_api_request($function, $data, $details, $method = 'get'){

    if(empty($details['api_key'])) return false;

    $url = 'http://' . QWINTRY_SITE_URL . '/api/' . $function;
    $data_string = http_build_query($data);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Authorization: Bearer '. $details['api_key']));
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS,  $data_string);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    $response = curl_exec($ch);
    curl_close($ch);
    if(empty($response)) return false;

    return json_decode($response);
}

function fn_qwintry_save_label($filename, $tracking, $details){

    if(empty($details['api_key'])) return false;

    $url =  'http://' . QWINTRY_SITE_URL . '/api/package-label?tracking=' . $tracking ;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Authorization: Bearer '. $details['api_key']));
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);

    $response = curl_exec($ch);

    $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    $content_type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);

    curl_close($ch);

    if ($content_type == 'application/pdf' && $http_status == 200) {
        return file_put_contents(QWINTRY_DIR_LABELS . $filename, $response);
    } else {
       return false;
    }
}

function fn_qwintry_check_label($order_id){
    return file_exists(QWINTRY_DIR_LABELS . $order_id . '.pdf');
}