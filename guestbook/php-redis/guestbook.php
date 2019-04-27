<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

require 'Predis/Autoloader.php';
require_once dirname(__FILE__).'/vendor/autoload.php';

Predis\Autoloader::register();

use Jaeger\Factory;
// use GuzzleHttp\Client;
use OpenTracing\Formats;
$agent_port = '192.168.99.101';
// init server span
$factory = Factory::getInstance();
$tracer = $factory->initTracer('user', $agent_port, 6831);
$serverSpan = $tracer->startSpan('example HTTP', []);
// $client = new Client;
$clientSapn = $tracer->startSpan('get', ['child_of' => $serverSpan]);
$tracer->inject($clientSapn->getContext(), Formats\BINARY, $trace_id);


if (isset($_GET['cmd']) === true) {
  $host = 'redis-master';
  if (getenv('GET_HOSTS_FROM') == 'env') {
    $host = getenv('REDIS_MASTER_SERVICE_HOST');
  }
  header('Content-Type: application/json');
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

    $client = new Predis\Client([
      'scheme' => 'tcp',
      'host'   => $host,
      'port'   => 6379,
      // 'parameters' => [
      //   'headers': $trace_id,
      // ],
    ]);
    $value = $client->get($_GET['key']);
    // $url = 'http://127.0.0.1:8080';
    // $result = $client->get($url, ['headers' => [ 'My-Trace-Id' => $trace_id ]]);

    // $clientSapn->setTags(['http.url' => $url]);
    $clientSapn->setTags(['http.method' => 'GET']);
    // $clientSapn->setTags(['http.status' => $value->getStatusCode()]);

    // $clientSapn->setTags(['http.result' => $result->getBody()]);
    $clientSapn->finish();

    $clientSapn = $tracer->startSpan('get', ['child_of' => $serverSpan]);

    print('{"data": "' . $value . '"}');
  }
} else {
  phpinfo();
} ?>