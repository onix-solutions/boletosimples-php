<?php

namespace BoletoSimples;

class Extra {
  public static function userinfo() {
    $response = BaseResource::sendRequest('GET', 'userinfo');
    return json_decode($response->getBody(),true);
  }
}