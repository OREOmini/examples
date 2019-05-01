<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

require 'Predis/Autoloader.php';
require_once dirname(__FILE__).'/vendor/autoload.php';

Predis\Autoloader::register();

use Jaeger\Config;
use OpenTracing\Formats;

unset($_SERVER['argv']);
//init server span start
$config = Config::getInstance();
$config->gen128bit();
$tracer = $config->initTrace('frontend', 'my-jaeger-agent:6831');

$spanContext = $tracer->extract(Formats\TEXT_MAP, $_SERVER);
$serverSpan = $tracer->startSpan('frontend HTTP', ['child_of' => $spanContext]);
$serverSpan->addBaggageItem("version", "1.8.9");
$tracer->inject($serverSpan->getContext(), Formats\TEXT_MAP, $_SERVER);
//init server span end


if (isset($_GET['cmd']) === true) {
  $host = 'redis-master';
  if (getenv('GET_HOSTS_FROM') == 'env') {
    $host = getenv('REDIS_MASTER_SERVICE_HOST');
  }
  // header('Content-Type: application/json');
  // header("my-trace-id".$trace_id);

  // when get the message
  if ($_GET['cmd'] == 'set') {
    $client = new Predis\Client([
      'scheme' => 'tcp',
      'host'   => $host,
      'port'   => 6379,
    ]);

    $client->set($_GET['key'], $_GET['value']);
    print('{"message": "Updated"}');
  } else {
    $host = 'redis-slave';
    if (getenv('GET_HOSTS_FROM') == 'env') {
      $host = getenv('REDIS_SLAVE_SERVICE_HOST');
    }

    $clientTrace = $config->initTrace('redis');
    $injectTarget1 = [];
    $clientSapn1 = $clientTrace->startSpan($host, ['child_of' => $spanContext]);
    $clientTrace->inject($clientSapn1->spanContext, Formats\TEXT_MAP, $injectTarget1);

    // echo sizeof($injectTarget1);

    $arr = [];
    $arr['scheme'] = 'tcp';
    $arr['host'] = $host;
    $arr['port'] = 6379;
    foreach ($injectTarget1 as $k => $v) {
      $arr[$k] = $v;
    }
    $url = $host."6379";

    $client = new Predis\Client($arr);

    $clientSapn1->setTags(['http.status_code' => 200, 'http.method' => 'GET', 'http.url' => $url]);
    // $clientSapn1->log(['message' => "HTTP1 ". $method .' '. $url .' end !']);
    $clientSapn1->finish();
    //client span1 end

    $value = $client->get($_GET['key']);
    print('{"data": "' . $value . '"}');
  }
} else {
  phpinfo();
} 
//server span end
$serverSpan->finish();
//trace flush
$config->flush();
?>