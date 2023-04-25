<?php

namespace nathanwooten\Application;

use Psr\Container\{

  ContainerInterface

};

use ReflectionClass;
use Exception;

class Injections
{

  const PROPERTY_INJECTOR = 'property';
  const METHOD_INJECTOR = 'method';

  public array $property = [];
  public array $method = [];

  public $obj = null;
  public $injector = null;
  public $name = null;
  public $value = null;
  public $condition = null;

  protected $put = [];

  public function __construct( $injections = null )
  {

    if ( null !== $injections ) {

	  if ( is_object( $injections ) ) {

        if ( ! $injections instanceof Injections ) {
          throw new Exception( 'Input to injections must be an array or an injections instance' );          
        }
        $injections->inject( $this );
      } else {

        if ( ! is_array( $injections ) ) {
          throw new Exception( 'Input to injections must be array or another injections' );
        }

        if ( isset( $injections[ static::PROPERTY_INJECTOR ] ) ) {
          $this->addMany( static::PROPERTY_INJECTOR, $injections[ static::PROPERTY_INJECTOR ] );
        }

        if ( isset( $injections[ static::METHOD_INJECTOR ] ) ) {
          $this->addMany( static::METHOD_INJECTOR, $injections[ static::METHOD_INJECTOR ] );
        }
      }
    }

  }

  public static function create( $injections = null )
  {

    return new static( $injections );

  }

  public function inject( $object, callable $condition = null )
  {

    if ( ! is_object( $object ) ) {
      $object = $this->applyConstructor( $object );
    }

    $this->addMany( 'property', $this->property, $condition );
    $this->addMany( 'method', $this->method, $condition );

    $object = $this->injectMany( $object, 'property', array_keys( $this->property ), $condition );
    $object = $this->injectMany( $object, 'method', array_keys( $this->method ), $condition );

    return $object;

  }

  public function addOne( $injector, $name, $value )
  {

    if ( ! in_array( $injector, [ 'property', 'method' ] ) ) {
      throw new Exception( 'Unknown injector type, ' . 'in method "add"' );
    }

    $this->$injector[ $name ] = $value;

    return $this;

  }

  public function addMany( $injector, array $injections )
  {

    foreach ( $injections as $name => $value ) {
      $this->addOne( $injector, $name, $value );
    }

    return $this;

  }

  public function injectOne( $obj, $injector, $name, $condition = null )
  {

    if ( ! array_key_exists( $name, $this->$injector ) ) {
      throw new Exception( 'Trying to injecting non-existant value, name: ' . $name );
    }
    $value = $this->$injector[ $name ];

    if ( ! is_object( $obj ) ) {
      $obj = $this->applyConstructor( $obj );
    } else {
      if ( 'method' === $injector && '__construct' === $name ) {
        throw new Exception( 'Can\'t use constructor injection on an existing object' );
      }
    }

    if ( ! in_array( $injector, [ 'property', 'method' ] ) ) {
      throw new Exception( 'Unknown injector type, ' . $injector . ' given' );
    }

    $id = [ $obj, $injector, $name, $value, $condition ];

    $this->put( ...$id );

    if ( null !== $condition ) {

      if ( ! is_array( $condition ) ) {
        $condition = [ $condition ];
      }

      foreach ( $condition as $callback ) {

        if ( ! is_callable( $callback ) ) {
          throw new Exception( 'Invalid (un-callable) condition provided' );
        }

        if ( ! $condition( $this ) ) {
          $this->subtract( $id );
          return $this;
        }        
      }
    }

    if ( 'property' === $injector ) {
      $obj->$name = $value;
    } elseif ( 'method' === $injector ) {

      if ( ! is_array( $value ) ) {
        throw new Exception( 'Method injection must be an array, ' . gettype( $value ) . ' given' );
      }
      $obj->$name( ...$value );
    }

    return $this;

  }

  public function injectMany( $obj, $injector, array $injections, $condition = null )
  {

    foreach ( $injections as $name ) {
      $this->injectOne( $obj, $injector, $name, $condition );
    }

    return $this;

  }

  public function put( $object, $injector, $name, $value, $condition )
  {

    $this->put[] = [ $this->obj, $this->injector, $this->name, $this->value, $this->condition ];

    $this->obj = $object;
    $this->injector = $injector;
    $this->name = $name;
    $this->value = $value;
    $this->condition = $condition;

    return $this;

  }

  public function retrieve( $identifier )
  {

    if ( ! is_array( $identifier ) ) {
      $identifier = [ $identifier ];
    }

    foreach ( $this->put as $key => $putArray ) {

      foreach ( $identifier as $id ) {

	    if ( ! in_array( $id, $putArray ) ) {
          continue 2;
        }
      }
      return $key;
    }

  }

  public function substract( $identifier )
  {

    unset( $this->put[ $this->retrieve( $identifier ) ] );    

  }

  public function applyConstructor( $class )
  {

    if ( ! is_object( $class ) ) {

      if ( is_string( $class ) && class_exists( $class ) ) {

        if ( isset( $this->method[ '__constructor' ] ) ) {
          $args = $this->method[ '__constructor' ];
        } else {
          $args = [];
        }

        $rc = new ReflectionClass( $class );

        if ( $rc->isInstantiable() ) {
          $obj = new $class( ...$args );
        } else {
          throw new Exception( 'Class provided is not instantiable, per reflection' );
        }
      } else {
        throw new Exception( 'Injecting objects only (and instantiable classes), type provided: ' . gettype( $class ) );
      }
    } else {
      $obj = $class;
    }

    return $obj;

  }

}