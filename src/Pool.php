<?php

namespace Uginroot\AsyncSymfonyProcess;

use Closure;
use RuntimeException;
use Symfony\Component\Process\Process;

class Pool
{
    public const SLEEP_MICROSECONDS = 1000;

    private bool $isEternal;
    private ?int $limitProcess = null;

    /** @var ProcessWrapper[] */
    private array $processWrappers = [];
    /** @var null|Closure():?Process  */
    private ?Closure $processFactory;
    /** @var null|Closure(WrapperProcess $wrapperProcess):void  */
    private ?Closure $callback;
    /** @var null|Closure(Process $process, string $type, string $data):void  */
    private ?Closure $outputListener;
    /** @var null|Closure():void  */
    private ?Closure $whileListener;

    /**
     * @param Closure():?Process $processFactory
     * @param Closure(WrapperProcess $wrapperProcess):void $callback
     * @param null|Closure(Process $process, string $type, string $data):void $outputListener
     * @param null|Closure():void $whileListener
     * @param null|bool $isEternal
     * @param null|int $limitProcess
     */
    public function __construct(
        ?callable $processFactory = null,
        ?callable $callback = null,
        ?callable $outputListener = null,
        ?callable $whileListener = null,
        ?bool $isEternal = false,
        ?int $limitProcess = null
    )
    {
        $this->setProcessFactory($processFactory);
        $this->setCallback($callback);
        $this->setOutputListener($outputListener);
        $this->setWhileListener($whileListener);
        $this->setIsEternal($isEternal);
        $this->setLimitProcess($limitProcess);
    }

    public function setProcessFactory(?callable $processFactory):self
    {
        if($processFactory instanceof Closure){
            $this->processFactory = $processFactory;
        } elseif($processFactory === null) {
            $this->processFactory = null;
        } else {
            $this->processFactory = Closure::fromCallable($processFactory);
        }

        return $this;
    }

    private function generateNewProcess():?Process
    {
        if($this->processFactory === null){
            throw new RuntimeException('Expected not null process factory');
        }

        return ($this->processFactory)();
    }

    public function setCallback(?callable $callback):self
    {
        if($callback instanceof Closure){
            $this->callback = $callback;
        } elseif($callback === null) {
            $this->callback = null;
        } else {
            $this->callback = Closure::fromCallable($callback);
        }

        return $this;
    }

    private function callCallback(ProcessWrapper $processWrapper):void
    {
        if($this->callback === null){
            return;
        }

        ($this->callback)($processWrapper);
    }

    public function setOutputListener(?callable $outputListener):self
    {
        if($outputListener instanceof Closure){
            $this->outputListener = $outputListener;
        } elseif($outputListener === null) {
            $this->outputListener = null;
        } else {
            $this->outputListener = Closure::fromCallable($outputListener);
        }

        return $this;
    }

    public function setWhileListener(?callable $whileListener):self
    {
        if($whileListener instanceof Closure){
            $this->whileListener = $whileListener;
        } elseif($whileListener === null) {
            $this->whileListener = null;
        } else {
            $this->whileListener = Closure::fromCallable($whileListener);
        }

        return $this;
    }

    private function callWhileListener():bool
    {
        if($this->whileListener === null){
            return false;
        }

        ($this->whileListener)();
        return true;
    }

    public function getIsEternal():bool
    {
        return $this->isEternal;
    }

    public function setIsEternal(bool $isEternal):self
    {
        $this->isEternal = $isEternal;
        return $this;
    }

    public function getLimitProcess():int
    {
        if($this->limitProcess === null){
            if (PHP_OS_FAMILY === 'Windows') {
                $cores = (int)shell_exec('echo %NUMBER_OF_PROCESSORS%');
            } else {
                $cores = (int)shell_exec('nproc');
            }

            $this->limitProcess = max(4, $cores);
        }

        return $this->limitProcess;
    }

    public function setLimitProcess(?int $limitProcess):self
    {
        $this->limitProcess = $limitProcess;
        return $this;
    }

    public function execute():void
    {
        $hasNewProcess = true;
        $hasNewProcessNext = true;

        while (true){

            $whileListenerIsCalled = $this->callWhileListener();

            foreach ($this->processWrappers as $index => $processWrapper){
                if($processWrapper->isRunning() === true){
                    continue;
                }

                unset($this->processWrappers[$index]);
                $this->callCallback($processWrapper);
            }

            if(count($this->processWrappers) >= $this->getLimitProcess()){
                if($whileListenerIsCalled === false){
                    usleep(self::SLEEP_MICROSECONDS);
                }
                continue;
            }

            if($this->isEternal){
                $hasNewProcess = true;
                $hasNewProcessNext = true;
            }

            if($hasNewProcess === false && $hasNewProcessNext === false){
                if(count($this->processWrappers) === 0){
                    break;
                }
                continue;
            }

            $process = $this->generateNewProcess();

            if ($process === null){
                if($hasNewProcess === false){
                    $hasNewProcessNext = false;
                } else {
                    $hasNewProcess = false;
                }
            } else {
                $this->addProcessWrapper($process);
                $hasNewProcess = true;
                $hasNewProcessNext = true;
            }
        }
    }

    private function addProcessWrapper(Process $process):void
    {
        $process->getInput();
        $processWrapper = new ProcessWrapper($process);
        $processWrapper->start($this->outputListener);
        $this->processWrappers[] = $processWrapper;
    }
}