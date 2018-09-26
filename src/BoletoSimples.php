<?php

class BoletoSimples {
  const VERSION = "0.1.0a";
  public static $configuration = null;
  public static $last_request = null;

  public static function configure($params = array()) {
    BoletoSimples::$configuration = new BoletoSimples\Configuration($params);
  }

}

BoletoSimples::configure();
