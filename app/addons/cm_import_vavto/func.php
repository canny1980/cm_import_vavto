<?php

use Tygh\Registry;
use Tygh\Settings;
use Tygh\Bootstrap;
use Tygh\Storage;

if (!defined('BOOTSTRAP')) { die('Access denied'); }


if(!function_exists('fn_cm_get_func')) {
function fn_cm_func($func_name) {
    return 'fn_' . Registry::get('runtime.controller') .'_'. $func_name;
    }
}

function fn_cm_install() {
    $dir = fn_get_files_dir_path() . CM_DIR;
    if(!is_dir($dir)){
        if(!mkdir($dir)) return false;
    }
    return true;
}

function fn_cm_uninstall() { return true; }


if(!function_exists('fn_cm_gen_cron_key')) {
function fn_cm_gen_cron_key() {
    $cron_key = fn_cm_generate_key(CM_CRON_KEY_LENGTH);
    Settings::instance()->updateValue( 'cron_key', $cron_key, Registry::get('runtime.controller') );
    return $cron_key;
    }
}

if(!function_exists('fn_cm_generate_key')) {
function fn_cm_generate_key($length = CM_CRON_KEY_LENGTH) {
    $chars = str_split('1234567890abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ');
    $key = '';
    for ($i = 0; $i < $length; $i++) $key .= $chars[rand(0, count($chars) -1)];
    return $key;
    }
}

if(!function_exists('fn_cm_cron_link')) {
function fn_cm_cron_link ($data) {
    $link = "/usr/bin/php " . Registry::get('config.dir.root') . DIRECTORY_SEPARATOR
            . Registry::get('config.admin_index') . " --dispatch=$data[1] --magic_key=$data[0]";
    return $link;
    }
}

if(!function_exists('fn_cm_import_get_remote_categories')) {
function fn_cm_import_get_remote_categories($ctrl,$company_id) {
    $vendor = fn_cm_import_vendor($ctrl);
    //fn_cm_func('set_categories')($vendor);	//only from php 5.4.0
    $func = fn_cm_func('set_categories');
    $func($vendor);
	if (!empty($company_id)) {
		$data = db_get_array("SELECT uc.remote_category_id, CONCAT(uc.remote_category_name,' [',uc.product_amount,']'), ucc.category_ids "
				    ."FROM ?:cm_".$vendor."_categories as uc "
				    ."LEFT JOIN ?:cm_".$vendor."_companies_categories as ucc ON uc.remote_category_id = ucc.remote_category_id AND ucc.company_id = ?i "
				    ."ORDER BY uc.product_amount DESC", $company_id);
	} else {
		$data = db_get_array("SELECT remote_category_id, CONCAT(remote_category_name,' [',product_amount,']') `remote_category_name` "
				    ."FROM ?:cm_".$vendor."_categories ORDER BY product_amount DESC");
		
		if (!empty($data)) {
			foreach ($data as &$_d) {
				$categories = db_get_fields("SELECT category_ids FROM ?:cm_".$vendor."_companies_categories WHERE remote_category_id = ?s", $_d['remote_category_id']);
				
				if (!empty($categories)) {
					foreach ($categories as $k => $v) {
						if (empty($v)) {
							unset($categories[$k]);
						}
					}
				}
				
				if (!empty($categories)) {
					$_d['category_ids'] = implode(",", $categories);
				}
			}
		}
	}
	return $data;
    }
}

if(!function_exists('fn_cm_get_xml')) {
function fn_cm_get_xml($xml_file, &$xml_date) {
    $local_file = fn_get_files_dir_path().CM_DIR .'/'. $xml_file;
    if( !is_file($local_file) ) {	//Файл не загружен
	    fn_set_notification("E", __("notice"), "File not exist: $local_file");
	    return false;
	    }
    if(!$xml = simplexml_load_file($local_file)){
	fn_set_notification("E", __("notice"), "Can not open xml file: $local_file"); 
	return false; 
	}
    $xml_date = date('Y-m-d H:i', filectime($local_file));
    return $xml;
    }
}

if(!function_exists('fn_cm_import_vendor')){ function fn_cm_import_vendor($str) { return preg_replace('/(.+_)/','',$str); } }

function fn_cm_import_vavto_set_categories($vendor) {
    $ctrl = Registry::get('runtime.controller');
    $file_url = fn_get_files_dir_path() . CM_DIR . "/v-avto.csv";
    $csv = file($file_url); 

        // Записываем в массив все категории из файла
        $data=array();
        foreach($csv as $k=>$v) {
            $v = explode(';', mb_convert_encoding($v,'UTF-8','Windows-1251') );
            if( array_key_exists($v[0],$data) ) $data[$v[0]] ++ ;
            else $data[$v[0]]=1;
            }
        db_query("UPDATE ?:cm_".$vendor."_categories SET parsed = ?s", 'N');
        foreach($data as $category_name=>$cnt){
                db_query("UPDATE ?:cm_".$vendor."_categories SET `product_amount` = ?i, `parsed` = ?s 
			    WHERE remote_category_name=?s", $cnt,'Y',$category_name)
		or db_query("INSERT INTO ?:cm_".$vendor."_categories SET `remote_category_name` = ?s, `product_amount` = ?i, `parsed` = ?s", $category_name, $cnt, 'Y');
                }

        $to_do=db_get_fields("SELECT count(*) FROM ?:cm_".$vendor."_categories WHERE remote_category_id NOT in "
			    ."(SELECT DISTINCT remote_category_id FROM ?:cm_".$vendor."_companies_categories)");
	$msg = "Всего товаров: " . array_sum($data).'<br>'
	    . "Неувязанных категорий: <b>$to_do[0]</b>";

        fn_set_notification("W", __("notice"), $msg);

        return true;
}

if(!function_exists('fn_cm_import_reset_amount')) {
function fn_cm_import_reset_amount($supplier_id) {
	db_query("UPDATE ?:products as tb1
		LEFT JOIN ?:supplier_links  as tb2 ON tb1.`product_id` = tb2.`object_id`
		SET tb1.`cm_import_product_updated` = ?s
		WHERE tb2.`supplier_id` = ?i AND tb2.`object_type` = ?s", 'N', $supplier_id, 'P');
	return true;
    }
}

if(!function_exists('fn_cm_import_images_v1')){
function fn_cm_import_images_v1($prefix, $image_file, $detailed_file, $position, $type, $object_id, $object, $remove_images = false) {
	static $updated_products = array();

	if (!empty($object_id)) {
		if (empty($updated_products[$object_id]) && $remove_images) {
			$updated_products[$object_id] = true;
			fn_delete_image_pairs($object_id, $object, 'A');
		}
		$_REQUEST["server_import_image_icon"] = '';
		$_REQUEST["type_import_image_icon"] = '';

		// Get image alternative text if exists
		if (!empty($image_file) && strpos($image_file, '#') !== false) {
			list ($image_file, $image_alt) = explode('#', $image_file);
		}

		if (!empty($detailed_file) && strpos($detailed_file, '#') !== false) {
			list ($detailed_file, $detailed_alt) = explode('#', $detailed_file);
		}

		if (!preg_match('#(http|https)://#i', $image_file))	{
			$image_file = fn_normalize_path($image_file);
			if (strpos($prefix . $image_file, fn_get_files_dir_path()) === false) {
				$image_file = fn_get_files_dir_path() . $prefix . $image_file;
			}
		}
		if (!preg_match('#(http|https)://#i', $detailed_file)) {
			$detailed_file = fn_normalize_path($detailed_file);
			if (strpos($prefix . $detailed_file, fn_get_files_dir_path()) === false) {
				$detailed_file = fn_get_files_dir_path() . $prefix . $detailed_file;
			}
		}
		if (!empty($image_alt)) {
			preg_match_all('/\[([A-Za-z]+?)\]:(.*?);/', $image_alt, $matches);
			if (!empty($matches[1]) && !empty($matches[2])) {
				$image_alt = array_combine(array_values($matches[1]), array_values($matches[2]));
			}
		}
		if (!empty($detailed_alt)) {
			preg_match_all('/\[([A-Za-z]+?)\]:(.*?);/', $detailed_alt, $matches);
			if (!empty($matches[1]) && !empty($matches[2])) {
				$detailed_alt = array_combine(array_values($matches[1]), array_values($matches[2]));
			}
		}
		$type_image_detailed = (strpos($detailed_file, '://') === false) ? 'server' : 'url';
		$type_image_icon = (strpos($image_file, '://') === false) ? 'server' : 'url';
		$_REQUEST["type_import_image_icon"] = array($type_image_icon);
		$_REQUEST["type_import_image_detailed"] = array($type_image_detailed);
		$image_file = fn_cm_find_file_v1($prefix, $image_file);

		if ($image_file !== false) {
			if ($type_image_detailed == 'url') {
				$_REQUEST["file_import_image_icon"] = array($image_file);
			} elseif (strpos($image_file, Registry::get('config.dir.root')) === 0) {
				$_REQUEST["file_import_image_icon"] = array(str_ireplace(fn_get_files_dir_path(), '', $image_file));
			} else {
				fn_set_notification('E', __('error'), __('error_images_need_located_root_dir'));
				$_REQUEST["file_import_image_detailed"] = array();
			}
		} else {
			$_REQUEST["file_import_image_icon"] = array();
		}
		$detailed_file = fn_cm_find_file_v1($prefix, $detailed_file);
		if ($detailed_file !== false) {
			if ($type_image_detailed == 'url') {
				$_REQUEST["file_import_image_detailed"] = array($detailed_file);
			} elseif (strpos($detailed_file, Registry::get('config.dir.root')) === 0) {
				$_REQUEST["file_import_image_detailed"] = array(str_ireplace(fn_get_files_dir_path(), '', $detailed_file));
			} else {
				fn_set_notification('E',  __('error'), __('error_images_need_located_root_dir'));
				$_REQUEST["file_import_image_detailed"] = array();
			}
		} else {
			$_REQUEST["file_import_image_detailed"] = array();
		}
		$_REQUEST['import_image_data'] = array(
			array(
				'type' => $type,
				'image_alt' => empty($image_alt) ? '' : $image_alt,
				'detailed_alt' => empty($detailed_alt) ? '' : $detailed_alt,
				'position' => empty($position) ? 0 : $position,
			)
		);

		return fn_attach_image_pairs('import', $object, $object_id);
	}
	return false;
}
}

if(!function_exists('fn_cm_find_file_v1')){
function fn_cm_find_file_v1($prefix, $file) {
	$file = Bootstrap::stripSlashes($file);

	// Absolute path
	if (is_file($file)) {
		return fn_normalize_path($file);
	}
	// Path is relative to prefix
	if (is_file($prefix . '/' . $file)) {
		return fn_normalize_path($prefix . '/' . $file);
	}
	// Url
	if (strpos($file, '://') !== false) {
		return $file;
	}
	return false;
}
}
//

// функции получения время выполнения скрипта
if(!function_exists('fn_cm_get_time')){
function fn_cm_get_time ($time = false) { return $time === false ? microtime(true) : microtime(true) - $time; }
}


// возвращает ид соответствия категорий
if(!function_exists('fn_cm_import_get_category_id_by_name')) {
function fn_cm_import_get_category_id_by_name($vendor, $remote_category_name, &$product) {
	
	if (empty($remote_category_name)) return false;
	$lc = db_get_fields(
		"SELECT category_ids FROM ?:cm_".$vendor."_categories `c` ".
		"INNER JOIN ?:cm_".$vendor."_companies_categories `cc` ON c.remote_category_id = cc.remote_category_id ".
		"WHERE c.remote_category_name = ?s", $remote_category_name
	        );
	if (empty($lc)) return false;
	$product['category_ids'] = array();
	foreach ($lc as $l_category) {
		if (!empty($l_category)) {
			$categories = explode(",", $l_category);
			$product['category_ids'] = array_merge($product['category_ids'], $categories);
			}
		}
	return true;
	}
}
if(!function_exists('fn_cm_log')){
function fn_cm_log($f,$msg) {
    fwrite ($f,date("[M d H:i:s] ")."$msg\n");
    }
}

function fn_cm_import_vavto_get_obj($ctrl, $vendor, $supplier_id, $is_cron = false, $start_from=null, $obj_count=null) {

    function fn_cm_get_import_csv($fname,&$deps){	// Returns Array( [p_code] = array( catalogue_no, double_no, description ))
	$fname = fn_get_files_dir_path() . CM_DIR .DIRECTORY_SEPARATOR. $fname;
	$csv = file($fname);
	$res=array();
	foreach($csv as $k=>$v) {
        	$v = explode(';', mb_convert_encoding($v,'UTF-8','Windows-1251') );
		//print_r($v);die();
		$v = array_map('trim', $v);
		if(isset($res[$v[1]])) echo "Dublicate mog=$v[1] in csv\n";
		if (!$key = array_search($v[0],$deps)) { $deps[]=$v[0]; $key = array_search($v[0],$deps); }
		$res[$v[1]] = array('department' => &$deps[$key],
				    'catalog_no' => isset($v[2])? $v[2]:null, 
				    'dbl_no' => isset($v[3])? $v[3]:null,
				    'description' => isset($v[4])? $v[4]:null );
	}
	return $res;
    }

    function fn_cm_update_product_feature($feature_id, $variant) {	//Returns feature_varian_ID 
	$feature_data = fn_get_product_feature_data($feature_id, false, false, DESCR_SL);
	$feature_data['variants'][] = array(
		'position' => '',
		'variant' => $variant,
		'description' => '',
		'page_title' => '',
		'url' => '',
		'meta_description' => '',
		'meta_keywords' => ''
		);
	fn_update_product_feature($feature_data, $feature_id, CART_LANGUAGE);
	//fn_share_object_to_all("product_features", CM_BRAND_FEATURE);
	return db_get_field("SELECT ?:product_feature_variants.variant_id FROM ?:product_feature_variants
					LEFT JOIN ?:product_feature_variant_descriptions
					ON ?:product_feature_variants.variant_id = ?:product_feature_variant_descriptions.variant_id
					WHERE ?:product_feature_variants.feature_id = ?i AND ?:product_feature_variant_descriptions.variant = ?s",
					$feature_id, $variant);
    }
    function fn_cm_get_product_feature_variant_id($feature_id,$variant) {
	$variant_id = db_get_field("SELECT ?:product_feature_variants.variant_id FROM ?:product_feature_variants
				LEFT JOIN ?:product_feature_variant_descriptions
				ON ?:product_feature_variants.variant_id = ?:product_feature_variant_descriptions.variant_id
				WHERE ?:product_feature_variants.feature_id = ?i AND ?:product_feature_variant_descriptions.variant = ?s",
				$feature_id, $variant);
	if ($variant_id) return $variant_id;
	if( !empty($variant) ) return fn_cm_update_product_feature($feature_id, $variant);
	return false;
    }

    function get_anlas_info($p_code){
        $s = @file_get_contents('https://anlas.ru/product/'.str_replace(' ','%20',$p_code));
        if($s === false || !preg_match("/<div class=\"description\">\n(.+\n)+?  <\/div>/", $s, $m)) return false;
        $m[0] = str_replace('<div class="description">'."\n",'',$m[0]);
        $m[0] =str_replace("\n  </div>",'',$m[0]);
        return trim($m[0]);
    }

    function cm_get_xml_online($ch, $url) {
        curl_setopt($ch, CURLOPT_URL, $url);
        $res = curl_exec($ch) or trigger_error(curl_error($ch));;
        $res = simplexml_load_string($res);
        return $res;
    }

    function add_sw($word, &$sw) {
	    $word = trim(str_replace('АКЦИЯ, РАСПРОДАЖА','',$word));
	    $word = trim(str_replace('акция','',$word));
	    if (strlen($word)) $sw[]=$word;
    }

    function db_product_touch($pids,$supplier_id){
	//var_dump($pids, $supplier_id, CM_COMPANY_ID);
	$affected_rows = db_query("UPDATE ?:products as tb1
		LEFT JOIN ?:supplier_links as tb2 ON tb1.`product_id` = tb2.`object_id`
	        SET tb1.`updated_timestamp` = UNIX_TIMESTAMP()
	        WHERE tb1.`product_id` IN (?a) AND tb2.`object_type` = ?s AND tb2.`supplier_id` = ?i AND tb1.company_id = ?i",
		$pids, 'P', $supplier_id, CM_COMPANY_ID);
	return $affected_rows;
    }

    function flush_touches(&$pids, $supplier_id){
	echo "Flush touches... "; echo db_product_touch($pids,$supplier_id)."\n";
	$pids=array();
    }


exec("ps xa|grep -v grep|grep cm_import_vavto.import_cron",$ps);
if(count($ps)>2) die("Process 'cm_import_vavto.import_cron' already running\n");
unset($ps);

$nacenka_min = 15;
$nacenka_max = 55; // "55" @29.04.2021, was "50"
$nacenka_min_from = 5000; //17.07.2021, was 3000
    $flog = fopen(fn_get_files_dir_path() . CM_DIR . "/import_".$vendor.".log",'a');	//Открываем лог-файл для дозаписи

    $startTimeOuther = fn_cm_get_time();
    $countProduct = 0;
    $images = array();
    $addon_info = Registry::get("addons.$ctrl");
    if(!$start_from && !$start_from = $addon_info['start_from']) die('can`t see start position');
    $xml_date = null;
    $remote_categories = array();
    $csv =fn_cm_get_import_csv('v-avto.csv',$remote_categories) or die('Can`t open CSV');	//Получаем полные категории для продуктов (mog=>category_name) из программы Восход3
    $skip_cnt = $new_cnt = $i = 0;

    $ch = curl_init() or die("curl error \n");
    curl_setopt($ch, CURLOPT_HTTPHEADER ,array('X-Voshod-API-KEY:'. $addon_info['api_key']));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    if(!isset($obj_count)) $obj_count = 8499;
    //$start_from = 81000;
    $end_at = $start_from + $obj_count;	// 9 starts for 90000+ products
    //$end_at = 4300;

    $msg = "Start at: $start_from, count: $obj_count";
    echo "$msg\n";
    fn_cm_log($flog,$msg);

    $page = ceil ($start_from/50);
    $i = ($page-1)*50;
    $touch_pids = array();
        do {
	    if(count($touch_pids)) {flush_touches($touch_pids, $supplier_id);}
	    $xml = cm_get_xml_online($ch, $addon_info['api_url']."items.xml?page=$page");
	    if ($xml === FALSE) { fn_cm_log($flog,"Can`t get '".$addon_info['api_url']."items.xml?page=$page'"); break; }
	    $page = (int)$xml->page->next;
	    $xml_offers = &$xml->items->item;	// Устанавливаем ссылку на раздел "Товары" в структуре
	    foreach ($xml_offers as $_xml) { $i++;
		    if($_xml->updated_at>$xml_date) $xml_date = $_xml->updated_at;
			if($i<$start_from) continue;
			$msg='';
			$product_code = (string)$_xml->mog;
			if(strlen($product_code)<1) {
			    fn_print_r("Неверная длина кода товара mog='".$_xml->mog."'. Игнорируем товар.");
			    continue;
			    }
			$in_csv = array_key_exists($product_code,$csv);
			$product = array('product_code' => $product_code);
			if($in_csv) $_csv = &$csv[$product_code]; else $_csv=null;
			$product['amount'] = (int)$_xml->count_chel;
			if($product['amount']<0) $product['amount']=0;

			/* Price */
			$product['price'] = (float)$_xml->price;
			if($product['price'] >= $nacenka_min_from) $nacenka = $nacenka_min;
			elseif($product['price']<11) $nacenka=100;
		        else $nacenka = $nacenka_max-($nacenka_max-$nacenka_min)*$product['price']/$nacenka_min_from;
			$product['price'] = round($product['price']*(1+$nacenka/100),2);
			
			if($_xml->shipment > 1) $product['qty_step'] = $_xml->shipment; else $product['qty_step'] = 0;
			
			$p = db_get_row("SELECT SQL_NO_CACHE tb1.`product_id`, tb1.`amount`, tb1.`status`, tb1.`list_price`, tb1.`qty_step`, from_unixtime(tb1.updated_timestamp) `uts`, "
					    ."tb2.`supplier_id`, tb3.`price`, tb4.`full_description`, tb4.search_words, id_path "
					."FROM ?:products as tb1 "
					    ."LEFT JOIN ?:supplier_links as tb2 ON tb1.`product_id` = tb2.`object_id` "
					    ."LEFT JOIN ?:product_prices as tb3 ON tb1.`product_id` = tb3.`product_id` "
					    ."LEFT JOIN ?:product_descriptions as tb4 ON tb1.`product_id` = tb4.`product_id` AND lang_code='ru' "
					    ."LEFT JOIN ?:products_categories as tb5 ON tb5.product_id = tb1.product_id "
					    ."LEFT JOIN ?:categories as tb6 ON tb6.category_id = tb5.category_id "
					    ."WHERE tb1.`product_code` = ?s AND `object_type` = ?s AND tb2.`supplier_id` = ?i AND tb1.company_id = ?i"
					   , $product_code, 'P', $supplier_id, CM_COMPANY_ID );

			/* Артикул производителя */
			$oem_num = (string)$_xml->oem_num;
			if($oem_num) $product['product_features'][CM_ARTICLE_FEATURE] = $oem_num;
			
			if (empty($p)) { /* New product */
				
				if( !$product['price'] ){
				    fn_cm_log($flog,"$i: $product_code: new, but price=$product[price], skip");
				    continue;
				    }
				if( !$product['amount'] ) {
				    fn_cm_log($flog,"$i: $product_code: new, but amount=$product[amount], skip");
				    continue;
				    }
    				if(!$in_csv) {
				    fn_cm_log($flog,"$i: Товар $product_code не найден в CSV! (". $_xml->department .")");
				    continue;
				    }
				if(!$_xml->name) {
				    fn_print_r("Пустое имя товара mog='".$_xml->name."'. Игнорируем товар.");
				    continue;
				    }
				if(strpos($_xml->name,'яя')===0) {
					fn_print_r("Товар mog='".$_xml->name."' выведен из продажи. Игнорируем товар.");
					continue;
					}
				$product['product'] = (string)$_xml->name;

				if(!fn_cm_import_get_category_id_by_name($vendor, $_csv['department'], $product)) {
				    if(!in_array($_csv['department'], array(
						'Автоаксессуары\Распродажа, уценка. Товар ВОЗВРАТУ не подлежит!!!',
						'Напитки', 'Запчасти иномарки\УЦЕНКА', 'Запчасти ВАЗ\Распродажа и Уценка'
						)) ) {
					fn_cm_log($flog, "$i: $product_code: Категория '$_csv[department]' не увязана!");
					if($is_cron) fwrite(STDERR, "$i: $product_code: Категория '$_csv[department]' не увязана!\n");
					}
				    continue;
				    }
				$product['supplier_id'] = $supplier_id;
				$product['company_id'] = CM_COMPANY_ID;
				$is_new = true; $new_cnt++;
				if($desc = get_anlas_info($product_code)) $product['full_description'] = $desc;
				elseif($_csv['description']) $product['full_description'] = str_replace('\n', "<br />", $_csv['description']);
				$msg.= "a=$product[amount] p=$product[price] ";
				
				/* Brand */
				$brand = trim((string)$_xml->oem_brand);
				if($brand_variant_id = fn_cm_get_product_feature_variant_id(CM_BRAND_FEATURE,$brand))
				    $product['product_features'][CM_BRAND_FEATURE] = $brand_variant_id;
				} /* end of new product */
			else {
				$is_new = false;
				/* Do not rely on loft-auto any more from 14/05/2020 @canny
				if(!$product['amount'] && intval($p['id_path'])==420 && $p['status']=='A' && strlen($oem_num)>=3) { 
					if($p['list_price']>=0) { // list_price=-1 means amount=0 at loft-auto
						$product['amount']=100; $msg .= "c=420! ";
						if($p['list_price']*1.15>$product['price']) $product['price'] = $p['list_price']*1.15; //Expects list_price = loft-auto_price
						}
					}
				*/
				if($p['amount']==$product['amount'] ) 
				    if(($p['price']==$product['price'] or !$p['amount']) && $p['status']=='A' ) {
					$touch_pids[] = intval($p['product_id']);
					//fn_cm_log($flog,"$i: $product_code: skip"); //31.01.2021
					$skip_cnt++; continue;
				    }
				    else unset($product['amount']); //no need update amount #17.07.2020
				else {
				    $msg.= "a=$p[amount]->$product[amount] ";
				    if ($p['amount']>0 && $product['amount']<=0) {
                                        $product['avail_since']=time(); //21.07.21
                                        $msg.= "since=NOW() ";
                                    }
				}
				if(!isset($product['amount']) or $product['price']==$p['price']) unset($product['price']);
				if (isset($product['price'])) $msg .= "p=$p[price]->$product[price] ";
				if(!$p['full_description']) {
				    if($desc = get_anlas_info($product_code)) $product['full_description'] = $desc;
				    elseif($in_csv && $_csv['description']) $product['full_description'] = str_replace('\n', "<br />", $_csv['description']);
				    }
				//if($p['supplier_id']==CM_ANLAS_SUPPLIER_ID) $product['supplier_id'] = $supplier_id; //deprecated 13.07.2020
				if($p['status']!='A') {$product['status']='A'; $msg .= "status=$p[status]->$product[status] ";} //01.11.2018
				if($p['qty_step'] != $product['qty_step']) $msg .="qty_step=$p[qty_step]->$product[qty_step] ";
				}

			//Слова для поиска
			
			$sw=array();
			if(!empty($p)) $p['search_words'] = trim($p['search_words']);
			else $p['search_words']='';
			if($in_csv) {
			    // Номер по каталогу
			    if($_csv['catalog_no']) {
				$product['product_features'][CM_CATALOGNUM_FEATURE] = $_csv['catalog_no'];
				add_sw($_csv['catalog_no'],$sw);
				}
			    // Дубликат
			    if($_csv['dbl_no']) {
				$product['product_features'][CM_DBLNUM_FEATURE] = $_csv['dbl_no'];
				//if($sw) $sw .= ',';
				add_sw($_csv['dbl_no'],$sw);
				}
			    }
			if($oem_num!=$product_code && !in_array($oem_num,$sw)) add_sw($oem_num,$sw);
			
			if( count($sw) ){
				$sw = implode(",",$sw);
				if($p['search_words'] != $sw ) {
				    $product['search_words'] = $sw;
				    $msg.='sw='; 
				    if (!$is_new) $msg.="'$p[search_words]'->";
				    $msg.="'$sw' ";
				    }
				}
			
			$product_id = fn_update_product($product, isset($p['product_id'])?$p['product_id']:null );

			// IMAGES
			$img_url = trim((string)$_xml->images->image);
			$product_image = fn_get_image_pairs($product_id, 'product', 'M');
			if ( empty($product_image) && $img_url) {
				// собираем картинки которых нет
				$filename = fn_get_files_dir_path() . $product_id .'-'. basename($img_url);
				$opts=array(
				    "ssl"=>array(
			            "verify_peer"=>false,
			            "verify_peer_name"=>false,
				    ),
				);
				if ( copy($img_url, $filename, stream_context_create($opts)) ) {
				    fn_cm_import_images_v1(fn_get_files_dir_path(), '', $filename, 0, 'M', $product_id, 'product');
				    unlink ($filename);
				    $msg_img=' (NEW image)';
				    }
				else {
				    $msg_img = " Не удалось скачать $img_url в `$filename`.";
				    $errors= error_get_last();
				    $msg_img .= " COPY ERROR: ".$errors['type']." ". $errors['message'];
				    fn_print_r($msg_img);
				    }
			} else $msg_img = !$img_url? ' (supplier have no image)':'';
			
			if(!$is_new) $msg .= "Last_upd=$p[uts] ";
			$msg = "$i: $product_code: $msg". ($is_new?' создан!':'') . $msg_img;
			echo($msg."\n");
			fn_cm_log($flog,$msg);
			$countProduct++;
		}
		// save $start_from every 100 iterations @17.09.2020
		if( ($i%100) == 0 ) Settings::instance()->updateValue( 'start_from', $i+1, $ctrl );
	    }
	    while ($i<$end_at && $page);
    
        if(count($touch_pids)) flush_touches($touch_pids, $supplier_id);

	fn_print_r("Итераций: $i");
	if($skip_cnt) fn_print_r("Пропущено товаров: $skip_cnt.");
	
	$logs = "Товаров обработано: $countProduct. <br>";
        $logs .= "Добавлено новых: $new_cnt. <br>";
        $logs .= 'Время, мин.: ' . round( fn_cm_get_time($startTimeOuther)/60, 1) .". <br>";
        $logs .= 'Память, Mb: ' . round(memory_get_peak_usage()/1024/1024, 3) .". <br>";
	$logs .= 'max_update_at= '. substr($xml_date,0,19);
        if(!$is_cron) fn_set_notification("W", __("notice"), $logs);

	if(!$page and $xml!==FALSE) $i=0;
	Settings::instance()->updateValue( 'start_from', $i+1, $ctrl ) ;
	$logs .= "\n-";// . str_repeat('-',17);
	fn_cm_log($flog, str_replace('<br>',"",$logs));
	return true;
}

/**
 * Hook is executed before saving or updating image.
 *
 * @param array   $image_data  Image data
 * @param int     $image_id    Image ID
 * @param string  $image_type  Type of an object image belongs to (product, category, etc.)
 * @param string  $images_path Path to directory image is located at
 * @param array   $_data       Data to be saved into "images" DB table
 * @param string  $mime_type   MIME type of an image file
 * @param bool    $is_clone    True if image is copied from an existing image object
 */
function fn_cm_import_vavto_update_image($image_data, $image_id, $image_type, $images_path, $_data, $mime_type, $is_clone) {
    if($is_clone) return;
    $flog = fopen(fn_get_files_dir_path() . CM_DIR . "/imageoptimize.log",'a');
    fn_cm_log($flog, "{$images_path}{$image_data['name']}: " . fn_cm_import_vavto_imageoptimize($image_data['path']));
}

/**
 * Actions after thumbnail file generate
 *
 * @param string $th_filename Thumbnail path
 */
function fn_cm_import_vavto_generate_thumbnail_file_post($th_filename) {
    $cmd = "php ".Registry::get('config.dir.root'). DIRECTORY_SEPARATOR .Registry::get('config.admin_index')
        ." --dispatch=cm_import_vavto.img_optimize --filename=$th_filename >/dev/null 2>&1 &"; //run as daemon
    shell_exec($cmd);
}

function fn_cm_import_vavto_imgoptimize_foreground($filename) {
    $flog = fopen(fn_get_files_dir_path() . CM_DIR . "/imageoptimize.log",'a');
    $image_path = Storage::instance('images')->getAbsolutePath($filename);
    fn_cm_log($flog, "{$filename}: " . preg_replace('/^\S+\.jpe?g\s/i', '', fn_cm_import_vavto_imageoptimize($image_path)) );
    fclose($flog);
}
include_once 'func_imageoptimize.php';


/**
 * Changes selected shipments
 *
 * @param array $shipments Array of shipments
 * @param array $params    Shipments search params
 */
function fn_cm_import_vavto_get_shipments_info_post(&$shipments, $params) {
    $ship = &$shipments[0];
    if( !isset($ship['carrier']) || $ship['carrier'] != 'russian_post' || !$ship['tracking_number'] 
       || !$params['advanced_info'] || !isset($ship['carrier_info'])) return;

    $track_dir = fn_get_files_dir_path() . CM_DIR . "/track";
    $fn = $track_dir .'/'. $ship['tracking_number'] .'.txt';
    if(time()-$ship['shipment_timestamp']>5184000)
        if(!file_exists($fn) || !$res = file_get_contents($fn)) return;
        else return;
    if(!isset($res)) {
        if(!file_exists($track_dir)) if (!mkdir($track_dir)) return;

        $ctime = @filectime($fn);
        if(!$ctime || time()-$ctime>3600) {
            $res = cm_getTrackStatus($ship['tracking_number']);
            if (strlen($res)>2) file_put_contents($fn, $res);
        }
        else $res = file_get_contents($fn);
    }
    if(!$res) return;
    $ship['carrier_info']['tracking_status'] = $res;
}

function cm_getTrackStatus($track) {
    $wsdlurl = 'https://tracking.russianpost.ru/rtm34?wsdl';
    $client = new SoapClient($wsdlurl, array('trace' => 1, 'soap_version' => SOAP_1_2));
    $params = array ('OperationHistoryRequest' => array ('Barcode' => $track, 'MessageType' => '0'),
       'AuthorizationHeader' => array ('login'=>CM_POCHTA_LOGIN, 'password'=>CM_POCHTA_PASS));
    $result = $client->getOperationHistory(new SoapParam($params,'OperationHistoryRequest'));
    $len = count($result->OperationHistoryData->historyRecord);
    if($len<2) return false;
    $rec = $result->OperationHistoryData->historyRecord[$len-1];
    $time = $rec->OperationParameters->OperDate;
    $time = str_replace("T", " ", substr($time, 0, 16));
    $dt = date_create_from_format("Y-m-d H:i", $time);
    $result = sprintf("%s\n%s,\n%s",
       date_format($dt, "d M Y, H:i"),
       $rec->AddressParameters->OperationAddress->Description,
       $rec->OperationParameters->OperAttr->Name);
    return $result;
}

/**
 * Update product data (running before fn_update_product() function)
 *
 * @param array   $product_data Product data
 * @param int     $product_id   Product identifier
 * @param string  $lang_code    Two-letter language code (e.g. 'en', 'ru', etc.)
 * @param boolean $can_update   Flag, allows addon to forbid to create/update product
 */
function fn_cm_import_vavto_update_product_pre(&$product_data, $product_id, $lang_code, $can_update) {
    if ((isset($product_data['avail_since']) && $product_data['avail_since']>0)
        || !isset($product_data['amount'])
        || $product_data['amount']>0
    ) return;
    if (empty($product_id) || fn_get_product_amount($product_id)>0) $product_data['avail_since'] = time();
}

include_once 'func_chk_zero_amount.php';

// --dispatch=cm_import_vavto.test
function fn_cm_import_vavto_test($filename) {
    echo "test\n";
}



//*/