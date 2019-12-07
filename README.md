# clippy/container

This is a derivative of [pimple](https://pimple.symfony.com/) with a few notable changes.

## Function parameter injection

In standard pimple, the service factories all receive `Container $c` as input, e.g.

```php
$c['descendent'] = function($c) {
  return $c['parent']->generateDescendent();
};
```

In Clippy's container:

```php
$c['descendent'] = function(MyParent $parent) {
  return $parent->generateDescendent();
};
```

This allows the consumers of services to (progressively) use type-hinting.

## Service methods

A "service method" is a function which supports *both* service-injection and runtime data-passing. For example:

```php
$c['getPassword()'] = function ($domain, SymfonyStyle $io) {
  if (getenv('PASSWORD')) return getenv('PASSWORD');
  return $io->askHidden("What is the password for <comment>$domain</comment>?");
}
$c['app']->main('', function($getPassword) {
  $pass = $getPassword('example.com');
});
```

The first parameter to `getPassword` is given at runtime (`$getPassword('example.com')`); the second parameter (`$io`)
is injected automatically.

The service-method is denoted by including `()` in the declaration. Compare:

```php
// Standard service
`$c['foo']` = function($injectedService, ...) { ... }

// Service method
`$c['foo()']` = function($inputtedData, ... , $injectedService, ...)  { ... }
};
```
