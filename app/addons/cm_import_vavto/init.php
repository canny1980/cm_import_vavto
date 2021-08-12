<?php

if (!defined('BOOTSTRAP')) { die('Access denied'); }

fn_register_hooks(
    'update_image',
    'generate_thumbnail_file_post',
    'get_shipments_info_post',
    'update_product_pre'
    );

//*/

