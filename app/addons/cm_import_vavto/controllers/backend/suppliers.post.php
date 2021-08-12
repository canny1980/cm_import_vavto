<?php

use Tygh\Registry;

if (!defined('BOOTSTRAP')) { die('Access denied'); }

if ($mode == 'update' || $mode == 'add') {

        // Remove "products" tab
        $tabs = Registry::get('navigation.tabs');
        if (isset($tabs['products'])) {
                unset($tabs['products']);
        }
    Registry::set('navigation.tabs', $tabs);

//* Do not do this, so products lost its suppliers! @canny 24.02.2019
        // Remove products variable in supplier data
        $supplier = Tygh::$app['view']->getTemplateVars('supplier');
        if (isset($supplier['products'])) { //fn_print_die($supplier['products']);
                unset($supplier['products']);
        }
    Tygh::$app['view']->assign('supplier', $supplier);
//*/
}