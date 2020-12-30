<?php


namespace Uginroot\AsyncSymfonyProcess;


use Closure;
use RuntimeException;
use Symfony\Component\Process\Process;

class ProcessWrapper
{
    private Process $process;
    private float $executionStart;
    private float $executionEnd;
    private string $output;
    private bool $isEnd = false;
    private bool $isStart = false;

    public function __construct(Process $process)
    {
        $this->process = $process;
    }

    public function getExecutionStart():float
    {
        return $this->executionStart;
    }

    public function getExecutionEnd():float
    {
        return $this->executionEnd;
    }

    public function getOutput():string
    {
        return $this->output;
    }

    public function getProcess():Process
    {
        return $this->process;
    }

    public function start(Closure $step = null):void
    {
        if($this->isEnd){
            throw new RuntimeException('Process already end');
        }

        if($this->isStart){
            throw new RuntimeException('Process already start');
        }

        $this->isStart        = true;
        $this->executionStart = microtime(true);
        if($step instanceof Closure){
            $this->process->start(function(string $type, string $data) use ($step){
                ($step)($this->process, $type, $data);
            });
        } else {
            $this->process->start();
        }
    }

    public function isRunning():bool
    {
        if($this->isStart === false){
            throw new RuntimeException('Process not start');
        }

        if($this->isEnd){
            return true;
        }

        $isRunning = $this->process->isRunning();

        if($isRunning === false){
            $this->isEnd        = true;
            $this->executionEnd = microtime(true);
            $this->output       = $this->process->getOutput();
        }

        return $isRunning;
    }

    public function getExecutionTime():float
    {
        return $this->executionEnd - $this->executionStart;
    }
}