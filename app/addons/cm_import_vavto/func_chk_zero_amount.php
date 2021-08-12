<?php

function fn_cm_import_vavto_chk_zero_amount($popularity=null, $limit=null) {
    global $argv;

    // Режим разработчика, для отображения ошибок
    define('DEVELOPMENT', true);
    // Отображение ошибок SMARTY и PHP на экран.
    error_reporting(E_ALL);
    ini_set('display_errors', 'on');
    ini_set('display_startup_errors', true);

    $argv[0] = basename($argv[0]);
    $grep = implode(' ', $argv);
    $cmd = "ps xa|grep -v grep|grep '$grep'";
    exec($cmd, $ps);
    if(count($ps)>2) die("Process '$grep' already running\n");
    unset($cmd, $grep, $ps);
    $flog = fopen(fn_get_files_dir_path() . CM_DIR . "/chk_zero_amount.log",'a'); //Открываем лог-файл для дозаписи

    if (!isset($popularity)) $popularity = 5000;//max product popularity
    $tstamp     = time() - 86400*60;           //min days ago product created
    $zstamp     = time() - 86400*30;          //min days ago amount sets zero (avail_since)
    if (!isset($limit)) $limit = 100;        //max amount of products to delete
    $pp = db_get_array(
         "SELECT SQL_CALC_FOUND_ROWS SQL_NO_CACHE products.product_id, products.product_code, SUBSTRING(FROM_UNIXTIME(avail_since),1,10) as since, popularity.total as popularity "
        ."FROM ?:products as products "
        ."LEFT JOIN ?:product_options_inventory as inventory ON inventory.product_id = products.product_id AND inventory.amount <= 0 "
        ."LEFT JOIN ?:product_popularity as popularity ON popularity.product_id = products.product_id "
        ."LEFT JOIN ?:supplier_links as links ON links.object_id = products.product_id AND links.object_type = 'P' "
        ."WHERE IF(products.tracking = 'O', inventory.amount <= 0, products.amount <= 0) AND (products.timestamp >= 0 AND products.timestamp <= ?i) "
        ."      AND popularity.total < ?i AND links.supplier_id = ?i AND avail_since <= ?i "
        ."ORDER BY avail_since, popularity.total desc, product_id ASC LIMIT ?i",
        $tstamp, $popularity, CM_VAVTO_SUPPLIER_ID, $zstamp, $limit);
    $found = db_get_found_rows();
    $deleted = 0;
    foreach($pp as $p) {
        //echo $p['product_id']."\n";
            $msg = "$p[product_code]: zero_date=$p[since] delete=";
            echo $msg;
            $res = fn_delete_product($p['product_id']);
            $deleted += $res;
            $msg.= $res;
            echo $res."\n";
            fn_cm_log($flog, $msg);
    }
    $msg = "Total found rows: $found, deleted: $deleted";
    echo "$msg\n";
    fn_cm_log($flog, $msg);
    fclose($flog);
    return true;
}

//*/