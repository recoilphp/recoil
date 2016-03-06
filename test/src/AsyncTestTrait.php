<?php

declare (strict_types = 1);

namespace Recoil;

use Recoil\Exception\TerminatedException;
use Recoil\Kernel\Strand;
use Recoil\Kernel\StrandObserver;
use Recoil\React\ReactKernel;
use Throwable;

trait AsyncTestTrait
{
    public function setUp()
    {
        $this->kernel = new ReactKernel();
    }

    public function __asyncProvider()
    {
        foreach (get_class_methods($this) as $name) {
            if (preg_match('/^asyncTest/', $name)) {
                yield $name => [$name];
            }
        }
    }

    /**
     * @test
     * @large
     * @dataProvider __asyncProvider
     */
    public function __async(string $method)
    {
        $strand = $this->kernel->execute([$this, $method]);
        $strand->attachObserver(
            new class implements StrandObserver
            {
                public function success(Strand $strand, $value)
                    { }
                public function failure(Strand $strand, Throwable $exception)
                    { throw $exception; }
                public function terminated(Strand $strand)
                    { throw new TerminatedException($strand); }
            }
        );

        $this->kernel->wait();
    }
}
