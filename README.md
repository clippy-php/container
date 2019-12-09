# clippy/container

This is a derivative of [pimple](https://pimple.symfony.com/) with a few notable changes.

## Function parameter injection

In standard Pimple, the service factories all receive `Container $c` as input, e.g.

```php
$c['descendent'] = function($c) {
  return $c['parent']->generateDescendent()->withWhizBang();
};
```

In Clippy's container:

```php
$c['descendent'] = function(MyParent $parent) {
  return $parent->generateDescendent()->withWhizBang();
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
$c['foo'] = function($injectedService, ...) { ... }

// Service method
$c['foo()'] = function($inputtedData, ... , $injectedService, ...)  { ... }
};
```

## Sigils

In standard Pimple, you may define alternative handling for a callback by using a wrapper method. Clippy supports
wrappers as well as a sigil notation.

```php
// Run a function every time one reads `$c['theJoneses]`, with mix of inputs and services
$c['getPassword'] = $c->method(function ($domain, SymfonyStyle $io) { ... });
$c['getPassword()'] = function ($domain, SymfonyStyle $io) { ... };
$c['getPassword']('localhost');
$c['getPassword']('example.com');

// Create a new instance every time one reads `$c['theJonses']`:
$c['theJoneses'] = $c->factory(function() { return new CoolThing(microtime(1)); });
$c['theJoneses++'] = function() { return new CoolThing(microtime(1)); };
print_r($c['theJoneses']);
print_r($c['theJoneses']);

```
