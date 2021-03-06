<?php

use Tygh\Registry;

if (!defined('BOOTSTRAP')) { die('Access denied'); }

if($mode == 'qwintry_create_shipment'){
    $order_info = fn_get_order_info($_REQUEST['order_id']);
    $result = fn_qwintry_create_shipment($order_info, $_REQUEST['qwintry_data']);
    if($result === true){
        fn_set_notification('N', __('notice'), __('done'));
    } else {
        fn_set_notification('E', __('error'), __('error_ajax', $result));
    }

    return array(CONTROLLER_STATUS_REDIRECT, 'orders.details?order_id=' . $_REQUEST['order_id']);
} elseif($mode == 'qwintry_download_label'){
    fn_get_file(QWINTRY_DIR_LABELS . $_REQUEST['order_id'] . '.pdf');
}

if($mode == 'details'){
    $order_info = fn_get_order_info($_REQUEST['order_id']);

    foreach($order_info['shipping'] as $shipping){
        if($shipping['module'] == 'qwintry') $qwintry_data = $shipping;
    }

    if(!empty($qwintry_data) && !fn_qwintry_check_label($order_info['order_id'])) {
        $navigation_tabs = Registry::get('navigation.tabs');
        $navigation_tabs['qwintry'] = array(
            'title' => __('qwintry_air'),
            'js' => true
        );
        Registry::set('navigation.tabs', $navigation_tabs);
        Registry::get('view')->assign('qwintry_data', $qwintry_data);
    }
}