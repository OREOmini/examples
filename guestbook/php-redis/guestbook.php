<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

require 'Predis/Autoloader.php';
require_once dirname(__FILE__).'/vendor/autoload.php';

// use Jaeger\Config;
// use GuzzleHttp\Client;
// use OpenTracing\Formats;
// use OpenTracing\Reference;

// init server span start
// $config = Config::getInstance();
// $config->gen128bit();

// $tracer = $config->initTrace('guestbook', '0.0.0.0:6831');

// $injectTarget = [];
// $spanContext = $tracer->extract(Formats\TEXT_MAP, $_SERVER);
// $serverSpan = $tracer->startSpan('example HTTP', ['child_of' => $spanContext]);
// $serverSpan->addBaggageItem("version", "1.8.9");

// $tracer->inject($serverSpan->getContext(), Formats\TEXT_MAP, $_SERVER);
// //init server span end
// $clientTrace = $config->initTrace('HTTP');

// //client span1 start
// $injectTarget1 = [];
// $spanContext = $clientTrace->extract(Formats\TEXT_MAP, $_SERVER);
// $clientSapn1 = $clientTrace->startSpan('HTTP1', ['child_of' => $spanContext]);
// $clientTrace->inject($clientSapn1->spanContext, Formats\TEXT_MAP, $injectTarget1);


use Jaeger\Factory;
use GuzzleHttp\Client;
use OpenTracing\Formats;

// init server span
$factory = Factory::getInstance();
$tracer = $factory->initTracer('redis', '127.0.0.1', 6831);
$trace_id = $_SERVER['HTTP_MY_TRACE_ID'];
$spanContext = $tracer->extract(Formats\BINARY, $trace_id);
$serverSpan = $tracer->startSpan('bar HTTP', ['child_of' => $spanContext]);


Predis\Autoloader::register();

if (isset($_GET['cmd']) === true) {
  $host = 'redis-master';
  if (getenv('GET_HOSTS_FROM') == 'env') {
    $host = getenv('REDIS_MASTER_SERVICE_HOST');
  }
  header('Content-Type: application/json');
  // header($injectTarget, false);

  if ($_GET['cmd'] == 'set') {
    $client = new Predis\Client([
      'scheme' => 'tcp',
      'host'   => $host,
      'port'   => 6379,
    ]);

    $client->set($_GET['key'], $_GET['value']);
    print('{"message": "Updated"}');


    $clientSapn1 = $tracer->startSpan('HTTP1', ['child_of' => $serverSpan->getContext()]);
    $method = 'GET';
    $url = 'http://192.168.99.101:30193/';
    $client = new Client();
    $res = $client->request($method, $url);
    $clientSapn1->setTags(['http.status_code' => $res->getStatusCode()
        , 'http.method' => 'GET', 'http.url' => $url]);
    $clientSapn1->finish();

  } else {
    $host = 'redis-slave';
    if (getenv('GET_HOSTS_FROM') == 'env') {
      $host = getenv('REDIS_SLAVE_SERVICE_HOST');
    }
    $client = new Predis\Client([
      'scheme' => 'tcp',
      'host'   => $host,
      'port'   => 6379,
    ]);

    $value = $client->get($_GET['key']);
    print('{"data": "' . $value . '"}');
  }
} else {
  phpinfo();
} 

//server span end
$serverSpan->finish();
$tracer->flush();

?>
