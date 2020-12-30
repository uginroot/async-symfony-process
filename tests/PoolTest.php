<?php

namespace Uginroot\AsyncSymfonyProcess\Test;

use Symfony\Component\Process\Process;
use Uginroot\AsyncSymfonyProcess\Pool;
use PHPUnit\Framework\TestCase;
use Uginroot\AsyncSymfonyProcess\ProcessWrapper;

class PoolTest extends TestCase
{
    public function testExecuteBase():void
    {
        $indexes = range(1, 10);
        $results = [];

        $pool = new Pool(
            static function() use (&$indexes):?Process
            {
                if(count($indexes) === 0){
                    return null;
                }

                $index = array_shift($indexes);
                return Process::fromShellCommandline(sprintf('echo %d', $index));
            },
            static function(ProcessWrapper $processWrapper) use (&$results):void
            {
                $index = (int)$processWrapper->getOutput();
                $results[] = $index;
            }
        );

        $pool->execute();
        $diff = array_diff($indexes, $results);

        // All process ended
        self::assertCount(0, $diff);
    }

    private array $indexes;
    private array $results;

    public function processFactory():?Process
    {
        if(count($this->indexes) === 0){
            return null;
        }

        $index = array_shift($this->indexes);
        return Process::fromShellCommandline(sprintf('echo %d', $index));
    }

    public function processCallback(ProcessWrapper $processWrapper):void
    {
        $index = (int)$processWrapper->getOutput();
        $this->results[] = $index;
    }

    public function testExecuteInClass():void
    {
        $countProcess = 10;
        $this->indexes = $indexes = range(1, $countProcess);
        $this->results = [];

        $pool = new Pool();
        $pool
            ->setProcessFactory([$this, 'processFactory'])
            ->setCallback([$this, 'processCallback'])
            ->execute()
        ;

        $diff = array_diff($indexes, $this->results);

        // All process ended
        self::assertCount(0, $diff);
        self::assertCount($countProcess, $indexes);
    }

    public function testExecuteEternal():void
    {
        $iteration = 0;
        $this->indexes = [];
        $this->results = [];

        $pool = new Pool();
        $pool
            ->setProcessFactory([$this, 'processFactory'])
            ->setCallback([$this, 'processCallback'])
            ->setIsEternal(true)
            ->setWhileListener(function() use (&$iteration, $pool){
                $iteration++;

                if($iteration % 5 === 0){
                    $this->indexes[] = $iteration;
                }

                if($iteration === 20){
                    $pool->setIsEternal(false);
                }
            })
            ->execute()
        ;

        self::assertSame([5, 10, 15, 20], $this->results);
    }
}
