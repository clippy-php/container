<?php
namespace Clippy;

use Invoker\Invoker;
use Invoker\ParameterResolver\AssociativeArrayResolver;
use Invoker\ParameterResolver\Container\ParameterNameContainerResolver;
use Invoker\ParameterResolver\DefaultValueResolver;
use Invoker\ParameterResolver\NumericArrayResolver;
use Invoker\ParameterResolver\ResolverChain;
use Psr\Container\ContainerInterface;

class Container implements ContainerInterface, \ArrayAccess {

  /**
   * @var \Invoker\Invoker
   */
  private $invoker = NULL;

  /**
   * @var \Pimple\Container
   */
  private $pimple;

  /**
   * ClippyContainer constructor.
   */
  public function __construct() {
    $this->pimple = new \Pimple\Container();
    $parameterResolver = new ResolverChain(array(
      new NumericArrayResolver(),
      new ParameterNameContainerResolver($this),
      new AssociativeArrayResolver(),
      new DefaultValueResolver(),
    ));
    $this->invoker = new Invoker($parameterResolver, $this);
  }

  public function offsetExists($id) {
    return $this->pimple->offsetExists($id);
  }

  public function offsetGet($id) {
    return $this->pimple->offsetGet($id);
  }

  public function offsetSet($id, $value) {
    $len = strlen($id);

    if ($value instanceof \Closure) {
      if ($id[$len-2] === '(' && $id[$len-1] === ')' ) {
        // Partial service definitions - these where some params are given at call-time.
        $id = substr($id, 0, $len-2);
        $value = function () use ($value) {
          return $this->inject(1, $value);
        };
      }
      else {
        $value = $this->inject(0, $value);
      }
    }

    $this->pimple->offsetSet($id, $value);
  }

  public function offsetUnset($id) {
    $this->pimple->offsetUnset($id);
  }

  // Support for methods

  public function getInvoker() {
    return $this->invoker;
  }

  public function call($callable, array $parameters = array()) {
    return $this->invoker->call($callable, $parameters);
  }

  /**
   * Wrap a function, augmenting its inputs with values from the container.
   *
   * $f = $container->inject(0, function($msg, Some $svcA){ ... });
   * $f("Hello");
   *
   * @param bool|int $passthru
   *   Whether the original arguments should be passed along to $callable.
   * @param callable $callable
   * @return \Closure
   */
  public function inject($passthru, $callable) {
    $c = $this;
    return function () use ($passthru, $callable, $c) {
      $args = $passthru ? func_get_args() : [];
      return $c->invoker->call($callable->bindTo($c), $args);
    };
  }

  // PSR-11

  public function get($id) {
    return $this->pimple[$id];
  }

  public function has($id) {
    return isset($this->pimple[$id]);
  }

  // Sugar

  /**
   * Register a service value or factory function.
   *
   * @param string $id
   * @param mixed $value
   * @return $this
   */
  public function set($id, $value) {
    $this->offsetSet($id, $value);
    return $this;
  }

  /**
   * Register an environment variable.
   *
   * @param string $id
   * @param mixed $value
   *   The default value or factory function
   * @return $this
   */
  public function env($id, $value = NULL) {
    $this->offsetSet("_env_{$id}", $value);
    $this->pimple[$id] = function () use ($id) {
      return getenv($id) ? getenv($id) : $this->pimple["_env_{$id}"];
    };
    return $this;
  }

  /**
   * @param string $id
   * @param callable $callable
   * @return $this
   * @see \Pimple\Container::extend()
   */
  public function extend($id, $callable) {
    $this->pimple->extend($id, $this->inject(0, $callable));
    return $this;
  }

  /**
   * @param callable $callable
   * @return callable
   * @see \Pimple\Container::factory()
   */
  public function factory($callable) {
    return $this->pimple->factory($this->inject(0, $callable));
  }

  //public function command($sig, $callback) {
  //  return $this->pimple['app']->command($sig, $callback);
  //}

  /**
   * @param callable[] $plugins
   *   List of functions which manipulate the container.
   * @return $this
   */
  public function register($plugins) {
    foreach ($plugins as $name => $callback) {
      $callback($this);
    }
    return $this;
  }

}
