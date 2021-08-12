<?php

include_once "OptimizerChain.php";
include_once "Optimizers/Jpegoptim.php";

class OptimizerChainFactory
{
    public static function create()
    {
        return (new OptimizerChain())
            ->addOptimizer(new Jpegoptim(['-m80', '--strip-all', '--all-progressive']));

/*            ->addOptimizer(new Pngquant([
                '--force',
            ]))

            ->addOptimizer(new Optipng([
                '-i0',
                '-o2',
                '-quiet',
            ]))

            ->addOptimizer(new Svgo([
                '--disable={cleanupIDs,removeViewBox}',
            ]))

            ->addOptimizer(new Gifsicle([
                '-b',
                '-O3',
            ]));
*/
    }
}
