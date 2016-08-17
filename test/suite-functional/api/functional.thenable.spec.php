<?php

declare (strict_types = 1); // @codeCoverageIgnore

namespace Recoil;

// context('can be invoked by yielding a thenable', function () {
//     rit('that does fulfill', function () {
//         $resolved = false;

//         $promise = Phony::mock(
//             [
//                 'function then' => function () use (&$resolved) { $resolved = true; },
//                 'function resolve' => function () { $this->then(); },
//             ]
//         );

//         yield $promise;

//         $promise->resolve();

//         expect($resolved)->to->be(true);
//     });

//     rit('that does reject with throwable', function () {
//         // TODO
//     });

//     rit('that does reject with non throwable', function () {
//         // TODO
//     });

//     rit('that does cancel', function () {
//         // TODO
//     });
// });

// context('can be invoked by yielding a thenable that is doneable', function () {
//     rit('can be invoked by yielding a thenable that is doneable', function () {
//         $this->thenable = Phony::mock(
//             [
//                 'function then' => null,
//                 'function done' => null,
//             ]
//         );
//     });

//     rit('that does reject with throwable', function () {
//         // TODO
//     });

//     rit('that does reject with non throwable', function () {
//         // TODO
//     });

//     rit('that does cancel', function () {
//         // TODO
//     });
// });
