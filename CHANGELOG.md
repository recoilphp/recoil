# Recoil Changelog

## 0.4.0 (2016-03-06)

This is the final release that will operate with PHP 5. In an effort to work
towards a production ready 1.0 release, future releases will require PHP 7.

- **[BC]** Dropped `Interface` suffix from interfaces
- **[BC]** Renamed `ReadableStream` to `ReadablePhpStream`
- **[BC]** Renamed `WritableStream` to `WritablePhpStream`
- **[BC]** Renamed `CoroutineAdaptor` to `StandardCoroutineAdaptor`
- **[BC]** Renamed `KernelApi` to `StandardKernelApi`
- **[BC]** Renamed `Strand` to `StandardStrand`
- **[BC]** Renamed `StrandFactory` to `StandardStrandFactory`
- **[NEW]** Added support for Guzzle promises
- **[IMPROVED]** The callback given to `Recoil::suspend` is now optional

## 0.3.0 (2015-06-26)

- **[BC]** Removed `StrandInterface::resume()`
- **[NEW]** `return` statement can be used to return a value inside a coroutine (requires PHP 7)
- **[IMPROVED]** Improved method documentation on `Recoil` facade (thanks @rjkip)

## 0.2.1 (2014-10-16)

- **[IMPROVED]** Added support for cancellable promises

## 0.2.0 (2014-09-23)

To faciliate several performance improvements the following backwards compatibility breaking changes have been introduced:

- **[BC]** `CoroutineInterface` no longer implements `EventEmitterInterface` - several unused events were fired every time a coroutine was called
- **[BC]** `Recoil::finalize()` now only works with generator based coroutines - this was previously implemented using the aforementioned events

## 0.1.0 (2014-02-04)

- Initial release
