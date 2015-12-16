<?php

declare (strict_types = 1);

namespace Recoil\Kernel;

// final class WaitAll implements Awaitable
// {
//     public function __construct(array $tasks)
//     {
//         $this->tasks = $task;
//     }

//     /**
//      * Perform the work and resume the caller upon completion.
//      *
//      * This method must not be called multiple times on the same object.
//      *
//      * @param Suspendable $caller The waiting object.
//      * @param Api         $api    The kernel API.
//      */
//     public function await(Suspendable $caller, Api $api)
//     {
//         foreach ($this->tasks as $index => $task) {
//             $caller = new ($this, $index) class implements Suspendable
//             {
//                 public function __construct(WaitAll $parent, $index)
//                 {
//                     $this->index = $index;
//                     $this->parent = $x;
//                 }

//                 public function resume($result = null)
//                 {
//                     $this->parent->results[$this->index] = $result;
//                 }

//                 public function throw(Exception $exception)
//                 {

//                 }

//                 private $index;
//                 private $parent;
//             };

//             $api->__dispatch(
//                 DispatchSource::API,
//                 $caller
//                 $task
//             );
//         }
//     }


//     /**
//      * @var array
//      */
//     private $tasks;

//     /**
//      * @var array<Strand>
//      */
//     private $results = [];
// }
