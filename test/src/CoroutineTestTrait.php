<?php

declare (strict_types = 1);

namespace Recoil;

use React\EventLoop\Factory;
use Recoil\Kernel\StandardKernel;
use Recoil\React\ReactApi;

trait CoroutineTestTrait
{
    public function setUp()
    {
        $this->eventLoop = Factory::create();
        $this->api = new ReactApi($this->eventLoop);
        $this->kernel = new StandardKernel($this->api);
    }

    public function __recoilProvider()
    {
        foreach (get_class_methods($this) as $name) {
            if (preg_match('/^recoilTest/', $name)) {
                yield $name => [$name];
            }
        }
    }

    /**
     * @test
     * @large
     * @dataProvider __recoilProvider
     */
    public function __recoil(string $method)
    {
        $task = $this->{$method}();

        $promise = $this->kernel->execute($task);

        $this->eventLoop->run();

        $this->assertResolvedWith(
            $this->expected,
            $promise
        );
    }

    public function expectResult($value)
    {
        $this->expected = $value;
    }

    use PromiseTestTrait;

    private $eventLoop;
    private $api;
    private $kernel;
    private $expected;
}
