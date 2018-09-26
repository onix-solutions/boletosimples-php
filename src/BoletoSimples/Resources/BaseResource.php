<?php

namespace BoletoSimples;

use GuzzleHttp\Client;
use kamermans\OAuth2\GrantType\NullGrantType;
use kamermans\OAuth2\OAuth2Middleware;
use GuzzleHttp\HandlerStack;

class BaseResource {
  /**
   * The GuzzleHttp\Client object
   */
  public static $client = null;

  /**
   * Default options used on Guzzle requests
   */
  public static $default_options = null;

  /**
   * The attributes of the current object, accessed via the anonymous get/set methods.
   */
  private $_attributes = array();

  /**
   * Array with all errors returned from last request.
   */
  public $response_errors = array();

  /**
   * Constructor method.
   */
  public function __construct($attributes = []) {
    $this->_attributes = $attributes;
    self::configure();
  }

  /**
   * Getter for internal object data.
   */
  public function __get($k) {
    if (isset ($this->_attributes[$k])) {
      return $this->_attributes[$k];
    }
  }

  /**
   * Setter for internal object data.
   */
  public function __set($k, $v) {
    $this->_attributes[$k] = $v;
  }

  public function attributes() {
    return $this->_attributes;
  }

  public function isNew() {
    return !isset($this->_attributes['id']) || $this->_attributes['id'] == null;
  }

  public function isPersisted() {
    return !$this->isNew();
  }

  public function path($action = null) {
    $class = get_called_class();
    $path = $this->isNew() ? $class::element_name_plural() : $class::element_name_plural()."/".$this->_attributes['id'];
    if ($action) {
      $path .= '/'.$action;
    }
    return $path;
  }

  public function save() {
    $action = $this->isNew() ? 'create' : 'update';
    return $this->_request($action);
  }

  public function parseResponse($response) {
    $status = $response->getStatusCode();
    if ($status >= 200 && $status <= 299) {
      if(json_decode($response->getBody(),true)) {
        $this->_attributes = json_decode($response->getBody(),true);
      }
      return true;
    } else {
      if (isset(json_decode($response->getBody(),true)['errors'])) {
        $this->response_errors = json_decode($response->getBody(),true)['errors'];
      }
      return false;
    }
  }

  private function _request($action) {
    $class = get_called_class();
    $method = self::methodFor($action);
    $path = $this->path();
    $options = [];
    if ($action == 'create' || $action == 'update') {
      $attributes = [$class::element_name() => $this->_attributes];
      $options = ['json' => $attributes];
    }

    $response = self::sendRequest($method, $path, $options);
    return $this->parseResponse($response);
  }

  public static function methodFor($action) {
    $methods = array(
      'create' => 'POST',
      'update' => 'PUT',
      'find' => 'GET',
      'destroy' => 'DELETE',
      'new' => 'GET'
    );
    return $methods[$action];
  }

  private static function _find($id) {
    if (!$id) {
      throw new \Exception("Couldn't find ".get_called_class()." without an ID.");
    }
    $class = get_called_class();
    $object = new $class(['id' => $id]);
    $object->_request('find');
    return $object;
  }

  private static function _create($attributes = array()) {
    $class = get_called_class();
    $object = new $class($attributes);
    $object->save();
    return $object;
  }

  private static function _all($params = array()) {
    $class = get_called_class();
    $response = self::sendRequest('GET', $class::element_name_plural(), ['query' => $params]);
    $collection = [];
    foreach (json_decode($response->getBody(),true) as $attributes) {
      $collection[] = new $class($attributes);
    }
    return $collection;
  }

  private static function _sendRequest($method, $path, $options = []) {
    $options = array_merge(self::$default_options, $options);
    $response = self::$client->request($method,$path,$options);
    if ($response->getStatusCode() >= 400 && $response->getStatusCode() <= 599) {
      new ResponseError($response);
    }
    return $response;
  }

  public static function element_name() {
    return Util::underscorize(get_called_class());
  }

  public static function element_name_plural() {
    return Util::pluralize(self::element_name());
  }

  public static function __callStatic($name, $arguments) {
    self::configure();
    return call_user_func_array("self::_".$name, $arguments);
  }

  /**
   * Configure the GuzzleHttp\Client with default options.
   */
  public static function configure() {
    $config = \BoletoSimples::$configuration;

    $oauth2 = new OAuth2Middleware(new NullGrantType);
    $oauth2->setAccessToken([
      'access_token' => $config->access_token,
    ]);

    $stack = HandlerStack::create();
    $stack->push($oauth2);

    self::$client = new Client([
      'handler' => $stack,
      'base_uri' => $config->baseUri(),
      'auth'    => 'oauth',

      'headers' => [
          'User-Agent' => $config->userAgent(),
      ],
      'verify' => false,
    ]);

    self::$default_options = ['headers' => ['Content-Type'=> 'application/json'], 'exceptions' => false];
  }

}