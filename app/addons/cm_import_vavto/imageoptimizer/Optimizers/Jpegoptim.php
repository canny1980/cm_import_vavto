<?php

include_once "BaseOptimizer.php";

class Jpegoptim extends BaseOptimizer
{
    public $binaryName = 'jpegoptim';

    public function canHandle($image)
    {
        return $image->mime() === 'image/jpeg';
    }
}
