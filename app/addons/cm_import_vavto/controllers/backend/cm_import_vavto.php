<?php

use Tygh\Registry;
use Tygh\Settings;

if (!defined('BOOTSTRAP')) {
    die('Access denied');
}

//set_time_limit(0);
//ini_set('max_execution_time', 0);
//ini_set('memory_limit', '512M');

$cm_ctrl = Registry::get('runtime.controller');
$cm_vendor = fn_cm_import_vendor($cm_ctrl);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    if ($mode == 'keygen') {
		fn_cm_gen_cron_key();
		return array( CONTROLLER_STATUS_REDIRECT , 'addons.update?addon='.$cm_ctrl );
		
    } elseif ($mode == 'import_manual') {
		$func="fn_".$cm_ctrl."_get_obj";

		$func( $cm_ctrl, $cm_vendor, CM_VAVTO_SUPPLIER_ID ) && fn_set_notification("N", __("notice"), __("$cm_ctrl.done"));
		return array(CONTROLLER_STATUS_REDIRECT, 'addons.update?addon='.$cm_ctrl);
		
    } elseif ($mode == 'save') {
		if (!empty($_REQUEST['remote_category_ids'])) {
			// Заносим привязанные категории в базу
			$company_id = Registry::get('runtime.company_id');
			//fn_print_r ( $company_id, $_REQUEST['remote_category_ids'] );die;
			foreach ($_REQUEST['remote_category_ids'] as $remote_category_id => $local_categories) {
				if(empty($local_categories)) continue;
				if (!empty($company_id)) {
					db_query("REPLACE INTO ?:cm_".$cm_vendor."_companies_categories (remote_category_id, company_id, category_ids) VALUES (?s, ?i, ?s)", $remote_category_id, $company_id, $local_categories);
				} else {
					db_query("UPDATE ?:cm_".$cm_vendor."_companies_categories SET category_ids = ?s WHERE remote_category_id = ?s", "", $remote_category_id);
					$cats = explode(",", $local_categories);
					if (!empty($cats)) {
						$all_cats = array();
						foreach ($cats as $c_id) {
							$category_company_id = db_get_field("SELECT company_id FROM ?:categories WHERE category_id = ?i", $c_id);
							if (!empty($category_company_id)) {
								$all_cats[$category_company_id][] = $c_id;
							}
						}
						if (!empty($all_cats)) {
							foreach ($all_cats as $comp_id => $categs) {
								db_query("REPLACE INTO ?:cm_".$cm_vendor."_companies_categories (remote_category_id, company_id, category_ids) VALUES (?s, ?i, ?s)", $remote_category_id, $comp_id, implode(",", $categs));
							}
						}
					}
				}
			}
	    //Settings::instance()->updateValue( 'last_update', 1, $cm_ctrl ) ;
            fn_set_notification("N", __("notice"), __("$cm_ctrl.categories_bind_successfully"));
        }
    }
	
    return array(CONTROLLER_STATUS_OK, "$cm_ctrl.update");
}

if ($mode == 'update') {
    $company_id = Registry::get('runtime.company_id');
    $remote_categories = fn_cm_import_get_remote_categories($cm_ctrl,$company_id);
	Tygh::$app['view']->assign('remote_categories', $remote_categories);
}
elseif ($mode == 'import_cron') {
	if (!empty($_REQUEST['magic_key'])) {
		$addon_info = Registry::get('addons.'.$cm_ctrl);
		if (urldecode($_REQUEST['magic_key']) == $addon_info['cron_key']) {

		    $func="fn_".$cm_ctrl."_get_obj";
		    $func( $cm_ctrl, $cm_vendor, CM_VAVTO_SUPPLIER_ID, true, 
			isset($_REQUEST['start_from'])?urldecode($_REQUEST['start_from']):null,
			isset($_REQUEST['count'])?urldecode($_REQUEST['count']):null
		    );
		} else {
			fn_print_die('access denied');
		}
	} else {
		fn_print_die('access denied');
	}
	exit;
}
elseif ($mode == 'chk_zero_amount') {
    $func = "fn_".$cm_ctrl."_chk_zero_amount";
    $func(
        isset($_REQUEST['popularity'])?urldecode($_REQUEST['popularity']):null,
        isset($_REQUEST['limit'])?urldecode($_REQUEST['limit']):null
    );
    exit;
}
elseif ($mode == 'test') {
    $func = "fn_".$cm_ctrl."_test";
    $func(
        isset($_REQUEST['filename'])?urldecode($_REQUEST['filename']):null
        //isset($_REQUEST['limit'])?urldecode($_REQUEST['limit']):null
    );
    exit;
}
elseif ($mode == 'img_optimize') {
    $func = "fn_".$cm_ctrl."_imgoptimize_foreground";
    $func(
        isset($_REQUEST['filename'])?urldecode($_REQUEST['filename']):null
        //isset($_REQUEST['limit'])?urldecode($_REQUEST['limit']):null
    );
    exit;
}
