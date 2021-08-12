<?php

include_once "DummyLogger.php";
include_once "Optimizer.php";
include_once "Image.php";

class OptimizerChain
{
    /* @var \Spatie\ImageOptimizer\Optimizer[] */
    protected $optimizers = [];

    /** @var \Psr\Log\LoggerInterface */
    protected $logger;

    /** @var int */
    protected $timeout = 60;

    public function __construct()
    {
        $this->useLogger(new DummyLogger());
    }

    public function getOptimizers()
    {
        return $this->optimizers;
    }

    public function addOptimizer(Optimizer $optimizer)
    {
        $this->optimizers[] = $optimizer;
        return $this;
    }

    public function setOptimizers(array $optimizers)
    {
        $this->optimizers = [];

        foreach ($optimizers as $optimizer) {
            $this->addOptimizer($optimizer);
        }

        return $this;
    }

    /*
     * Set the amount of seconds each separate optimizer may use.
     */
    public function setTimeout(int $timeoutInSeconds)
    {
        $this->timeout = $timeoutInSeconds;

        return $this;
    }

    public function useLogger(LoggerInterface $log)
    {
        $this->logger = $log;
        return $this;
    }

    public function optimize($pathToImage, $pathToOutput = null)
    {
        if ($pathToOutput) {
            if(!copy($pathToImage, $pathToOutput)) return "can`t copy from `{$pathToImage}` to `{$pathToOutput}`";
            $pathToImage = $pathToOutput;
        }
        try { $image = new Image($pathToImage); }
        catch (InvalidArgumentException $e) { return $e->getMessage(); }

        $this->logger->info("Start optimizing {$pathToImage}");

        $result ="";
        foreach ($this->optimizers as $optimizer) {
            $result .= (!empty($result)?"\n":""). $this->applyOptimizer($optimizer, $image);
        }
        return $result;
    }

    protected function applyOptimizer(Optimizer $optimizer, Image $image)
    {
        if (! $optimizer->canHandle($image)) {
            return;
        }

        $optimizerClass = get_class($optimizer);

        $this->logger->info("Using optimizer: `{$optimizerClass}`");

        $optimizer->setImagePath($image->path());

        $command = $optimizer->getCommand();

        $this->logger->info("Executing `{$command}`");
        $output = [];
        $retval = 0;
        exec($command .' 2>&1', $output, $retval);
	//var_dump($output);
        return preg_replace("/^(\/\S+\/)(\S+ .+)/", "$2", $output[count($output)-1]);

//        $process = Process::fromShellCommandline($command);

//        $process
//            ->setTimeout($this->timeout)
//            ->run();

//        $this->logResult($process);
    }

    protected function logResult($process)
    {
        if (! $process->isSuccessful()) {
            $this->logger->error("Process errored with `{$process->getErrorOutput()}`");

            return;
        }

        $this->logger->info("Process successfully ended with output `{$process->getOutput()}`");
    }
}
