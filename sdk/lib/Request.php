<?php

declare(strict_types=1);

namespace PWall;

class Request
{
  private $request;
  private $order_id     = null;
  private $currency     = null;
  private $group_id     = null;
  private $original_url = null;
  private $notify       = null;

  public function __construct(
    $jsonRequest,
    $isAdmin
  ){
    $this->request      = json_decode($jsonRequest, true);
    $this->order_id     = $isAdmin ? str_pad("0", 12, "0", STR_PAD_LEFT) : null;
    $this->currency     = $isAdmin ? "" : null;
    $this->amount       = $isAdmin ? "0" : null;
    $this->group_id     = $isAdmin ? 0 : null;
    $this->original_url = $isAdmin ? "" : null;
  }
  
  /**
   * Returns the required JSON to proxy a request to Waiap
   *
   * @return  JSON request ready to proxy to Waiap
   */
  public function toJSON(){
    $json_request                               = $this->request;
    $json_request["params"]["order"]            = $this->order_id;
    $json_request["params"]["amount"]           = $this->amount;
    $json_request["params"]["currency"]         = $this->currency;
    $json_request["params"]["group_id"]         = $this->group_id;
    $json_request["params"]["original_url"]     = $this->original_url;
    $json_request["params"]["notify"]["result"] = $this->notify;
    return json_encode($json_request);
  }

  /**
   * Returns the required Array to proxy a request to Waiap
   *
   * @return  Array request ready to proxy to Waiap
   */
  public function toArray()
  {
    $json_request                               = $this->request;
    $json_request["params"]["order"]            = $this->order_id;
    $json_request["params"]["amount"]           = $this->amount;
    $json_request["params"]["currency"]         = $this->currency;
    $json_request["params"]["group_id"]         = $this->group_id;
    $json_request["params"]["original_url"]     = $this->original_url;
    $json_request["params"]["notify"]["result"] = $this->notify;
    return $json_request;
  }
  
  /**
   * Sets order id that will be sent to confirm payments
   * 
   *
   * @param  mixed $order_id Order id which the petition is built around 
   * @return void
   */
  public function setOrderId($order_id){
    if(strlen($order_id) > 12){
      throw new \PWall\Exception\InvalidArgumentException('Order id must not exceed 12 characters');
    }
    $this->order_id = str_pad(strval($order_id), 12, "0", STR_PAD_LEFT);
  }
  
    
  /**
   * Sets amount for the payment, defaults to 0 for backend operations
   *
   * @param  float $amount Amount with decimals that will be charged to customer
   * @return void
   */
  public function setAmount($amount){
    // if($amount <= floatval(0)){
    //   throw new \PWall\Exception\InvalidArgumentException('Order amount must be more than 0');
    // }
    $this->amount = strval($amount * 100);
  }


  /**
   * Sets currency of the sale operation
   *
   * @param   $currency ISO 4217 currency code
   * @return void
   */
  public function setCurrency($currency){
    $this->currency = $currency;
  }
  
  /**
   * Sets customer id for the request
   *
   * @param int $customer_id Customer id group
   * @return void
   */
  public function setGroupId($customer_id){
    $this->group_id = $customer_id;
  }

  /**
   * Sets original url for the request
   *
   * @param string $original_url Base url of commerce
   * @return void
   */
  public function setOriginalUrl($original_url){
    $parsed_url = parse_url($original_url);
    if (!$parsed_url) {
      throw new \PWall\Exception\InvalidArgumentException('Invalid url');
    }
    $this->original_url = $parsed_url["host"];
  }

  /**
   * Sets return url for redirect payment methods
   *
   * @param  $url url to redirect after payment gateway
   * @return void
   */
  public function setNotifyResult($url)
  {
    $this->notify = $url;
  }
    
  /**
   * Check if request is action sale, this can NOT be used to place order
   *
   * @return boolean true if request is for sale action, false otherwise
   */
  public function isActionSale(){
    if(is_array($this->request)
    && array_key_exists("action", $this->request)
    && $this->request["action"] === \PWall\Helper\Constants::PWALL_ACTION_SALE){
      return true;
    }

    return false;
  }

  /**
   * Check if request is and action of express checkbox
   * 
   * @return boolean true if request is for sale action, false otherwise
   */
  public function isActionExpressCheckout(){
    if(is_array($this->request)
    && array_key_exists("params", $this->request) 
    && array_key_exists("express_checkout", $this->request["params"])
    && $this->request["params"]["express_checkout"] == true){
      return true;
    }
    return false;
  }

  /**
   * Check if request is getExtraData action
   * 
   * @return boolean true if request is for sale action, false otherwise
   */
  public function isActionGetExtraData()
  {
    if (
      is_array($this->request)
      && array_key_exists("action", $this->request)
      && $this->request["action"] == true
    ) {
      return true;
    }
    return false;
  }
}
