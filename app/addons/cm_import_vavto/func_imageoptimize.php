<?php

/**
 * Image optimization
 * @param string $image_path  Path to image
 * @return string  Optimization output
 */
function fn_cm_import_vavto_imageoptimize($from_image, $to_image=null) {
    //echo "Hello from fn_cm_import_vavto_imageoptimize()\n";
    include_once 'imageoptimizer/OptimizerChainFactory.php';
    $opti = OptimizerChainFactory::create();
    return $opti->optimize($from_image, $to_image);
}

//