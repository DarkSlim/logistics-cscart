<?php

use Tygh\Registry;

if (!defined('BOOTSTRAP')) { die('Access denied'); }

$cart = & $_SESSION['cart'];

if($mode == 'checkout'){
    if(!empty($cart['edit_step']) && $cart['edit_step'] == 'step_three' && !empty($_REQUEST['qwintry'])){
        $cart['qwintry'] = $_REQUEST['qwintry'];
    }
}

if ($mode == 'update_steps') {
    if (!empty($_REQUEST['update_step']) && $_REQUEST['update_step'] == 'step_three') {
        $cart['qwintry'] = empty($_REQUEST['qwintry']) ? array() : $_REQUEST['qwintry'];
    }
}