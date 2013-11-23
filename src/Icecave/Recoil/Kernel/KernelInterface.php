<?php
namespace Icecave\Recoil\Kernel;

interface KernelInterface
{
    public function execute($coroutine);

    public function executeStrand(StrandInterface $strand);

    public function api();

    public function coroutineAdaptor();

    public function strandFactory();

    public function eventLoop();
}
