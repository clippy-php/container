<?php
namespace Clippy;

use Invoker\Invoker;
use Invoker\ParameterResolver\AssociativeArrayResolver;
use Invoker\ParameterResolver\Container\ParameterNameContainerResolver;
use Invoker\ParameterResolver\DefaultValueResolver;
use Invoker\ParameterResolver\NumericArrayResolver;
use Invoker\ParameterResolver\ResolverChain;
use Pimple\Exception\ExpectedInvokableException;
use Pimple\ContainerTrait;
use Psr\Container\ContainerInterface;

class Container implements ContainerInterface, \ArrayAccess {

  use ContainerTrait {
    offsetSet as pimpleOffsetSet;
    __construct as pimpleConstruct;
  }

  /**
   * @var \Invoker\Invoker
   */
  private $invoker = NULL;

  /**
   * ClippyContainer constructor.
   */
  public function __construct(array $values = []) {
    $parameterResolver = new ResolverChain(array(
      new NumericArrayResolver(),
      new ParameterNameContainerResolver($this),
      new AssociativeArrayResolver(),
      new DefaultValueResolver(),
    ));
    $this->invoker = new Invoker($parameterResolver, $this);
    $this->pimpleConstruct($values);
  }

  public function offsetSet($id, $value) {
    $len = strlen($id);

    if ($len > 2 && $value instanceof \Closure) {
      // Interpret sigils
      // 'foo()' -> service method
      // 'foo++' -> service factory
      if ($id[$len - 2] === '(' && $id[$len - 1] === ')') {
        $id = substr($id, 0, $len - 2);
        $value = $this->method($value);
      }
      elseif ($id[$len - 2] === '+' && $id[$len - 1] === '+') {
        // Partial service definitions - these where some params are given at call-time.
        $id = substr($id, 0, $len - 2);
        $value = $this->factory($value);
      }
    }

    $this->pimpleOffsetSet($id, $value);
  }

  // Parameter injection support

  /**
   * Invoke a callable, passing a mix of parameters based on
   * $parameters and the contents of the container.
   *
   * @param callable $callable
   * @param array $parameters
   * @return mixed
   */
  public function call($callable, array $parameters = array()) {
    return $this->invoker->call($callable, $parameters);
  }

  // Service methods

  /**
   * Defines a service which is actually callable method, whose parameters
   * are based on a mix of runtime inputs and service ids.
   *
   * @param callable $callable
   * @return callable
   */
  public function method($callable) {
    if (!\method_exists($callable, '__invoke')) {
      throw new ExpectedInvokableException('Callable is not a Closure or invokable object.');
    }
    return $this->protect(function () use ($callable) {
      return $this->invoker->call($callable, func_get_args());
    });
  }

  // PSR-11 (++)

  /**
   * @param string $id
   * @return mixed
   *
   * @see ContainerInterface::get()
   */
  public function get($id) {
    return $this->offsetGet($id);
  }

  /**
   * @param string $id
   * @return bool
   * @see ContainerInterface::get()
   */
  public function has($id) {
    return $this->offsetExists($id);
  }

  /**
   * @param string $id
   * @param mixed $value
   * @return $this
   */
  public function set($id, $value) {
    $this->offsetSet($id, $value);
    return $this;
  }

  ///**
  // * Register an environment variable.
  // *
  // * @param string $id
  // * @param mixed $value
  // *   The default value or factory function
  // * @return $this
  // */
  //public function env($id, $value = NULL) {
  //  $this->offsetSet("_env_{$id}", $value);
  //  $this->offsetSet($id, function () use ($id) {
  //    return getenv($id) ? getenv($id) : $this->pimple["_env_{$id}"];
  //  });
  //  return $this;
  //}

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
