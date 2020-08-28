<?php

declare(strict_types=1);

namespace PWall\Utils;

class Logger{

  private $log_file;

  public function __construct(
    $logFilePath
  ){
    $this->log_file = fopen($logFilePath, 'a');
  }
  
  /**
   * Logs message to file
   *
   * @param  String $message message to log
   * @return void
   */
  public function log($message){
    if($this->log_file !== false){
      fwrite($this->log_file, "[" . date('Y-m-d\TH:i:s\Z', time()) . "] " . $message . PHP_EOL);
    }
  }

}
