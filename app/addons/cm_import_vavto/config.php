<?php

if (!defined('BOOTSTRAP')) { die('Access denied'); }

fn_define('CM_CRON_KEY_LENGTH', 8);
fn_define('CM_VAVTO_SUPPLIER_ID', 1);
//fn_define('CM_ANLAS_SUPPLIER_ID', 2);	//deprecated
fn_define('CM_COMPANY_ID', 1);
fn_define('CM_BRAND_FEATURE', 18);
fn_define('CM_ARTICLE_FEATURE', 53);
fn_define('CM_DOUBLE_FEATURE', 57);
fn_define('CM_CATALOGNUM_FEATURE', 54);
fn_define('CM_DBLNUM_FEATURE', 57);

fn_define('CM_DIR', 'cannyMOD');	// Папка с рабочими файлами
//fn_define('CM_VAVTO_USLEEP',80000);	// Скорость обработки запросов v-avto

include 'config_local.php';
