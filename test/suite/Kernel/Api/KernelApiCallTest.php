<?php
namespace Recoil\Kernel\Api;

use BadMethodCallException;
use Exception;
use PHPUnit_Framework_TestCase;
use Phake;
use Recoil\Kernel\Kernel;
use Recoil\Recoil;

class KernelApiCallTest extends PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $this->api    = Phake::partialMock(KernelApi::class);
        $this->kernel = new Kernel(null, $this->api);
    }

    public function testTick()
    {
        $coroutine = function () {
            yield Recoil::return_(123);
        };

        $strand = $this->kernel->execute($coroutine());

        $this->kernel->eventLoop()->run();

        Phake::verify($this->api)->return_($this->identicalTo($strand), 123);
    }

    public function testTickFailure()
    {
        $coroutine = function () {
            try {
                yield Recoil::foo();
                $this->fail('Expected exception was not thrown.');
            } catch (BadMethodCallException $e) {
                $this->assertSame(
                    'The kernel API does not have an operation named "foo".',
                    $e->getMessage()
                );
            }
        };

        $this->kernel->execute($coroutine());

        $this->kernel->eventLoop()->run();
    }
}
