# Recoil Changelog

### 0.2.1 (2014-10-16)

* **[IMPROVED]** Added support for cancellable promises

### 0.2.0 (2014-09-23)

To faciliate several performance improvements the following backwards compatibility breaking changes have been introduced:

* **[BC]** `CoroutineInterface` no longer implements `EventEmitterInterface` - several unused events were fired every time a coroutine was called
* **[BC]** `Recoil::finalize()` now only works with generated based coroutines - this was previously implemented using the aforementioned events

### 0.1.0 (2014-02-04)

* Initial release
