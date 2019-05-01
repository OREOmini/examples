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

$clientTrace = $config->initTrace('redis');

if (isset($_GET['cmd']) === true) {
  $host = 'redis-master';
  if (getenv('GET_HOSTS_FROM') == 'env') {
    $host = getenv('REDIS_MASTER_SERVICE_HOST');
  }

  // when get the message
  if ($_GET['cmd'] == 'set') {
    // init injection for redis-master service
    $injectTarget2 = [];
    $clientSpan2 = $clientTrace->startSpan($host, ['child_of' => $spanContext]);
    $clientTrace->inject($clientSpan2->spanContext, Formats\TEXT_MAP, $injectTarget2);

    $client = new Predis\Client([
      'scheme' => 'tcp',
      'host'   => $host,
      'port'   => 6379,
    ]);
    foreach ($injectTarget2 as $k => $v) {
      $client->set($k, $v);
    }
    $client->set($_GET['key'], $_GET['value']);

    // set tags and logs for the span
    $url = $host."6379";
    $clientSpan2->setTags(['http.status_code' => 200, 'http.method' => 'GET', 'http.url' => $url]);
    $clientSpan2->log(['message' => $host.' '.'GET '. $url .' end !']);
    $clientSpan2->finish();
    print('{"message": "Updated"}');
  } else {
    $host = 'redis-slave';
    if (getenv('GET_HOSTS_FROM') == 'env') {
      $host = getenv('REDIS_SLAVE_SERVICE_HOST');
    }

    // init injection for redis-slave service
    $injectTarget1 = [];
    $clientSpan1 = $clientTrace->startSpan($host, ['child_of' => $spanContext]);
    $clientTrace->inject($clientSpan1->spanContext, Formats\TEXT_MAP, $injectTarget1);

    $arr = [];
    $arr['scheme'] = 'tcp';
    $arr['host'] = $host;
    $arr['port'] = 6379;
    foreach ($injectTarget1 as $k => $v) {
      $arr[$k] = $v;
    }
    $client = new Predis\Client($arr);
    $value = $client->get($_GET['key']);

    // set tags and logs for the span
    $url = $host."6379";
    $clientSpan1->setTags(['http.status_code' => 200, 'http.method' => 'GET', 'http.url' => $url]);
    $clientSpan1->log(['message' => $host.' '.'GET '. $url .' end ! value: '.$value]);
    $clientSpan1->finish();

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