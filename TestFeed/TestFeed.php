<?php

require('../Class/DataFeed.class.php');

$rustart = getrusage();

$dataFeed = new DataFeed();

// $stuff = 'yeet';
$args = ['yup' => 'neat'];

$dataFeed->setConfig('data/config.json');

// $dataFeed->getFeed();


$dataFeed->setFeed('psd7003.xml');
$dataFeed->setFile('data/psd7003.xml');
$dataFeed->setNode('ProteinEntry');
$dataFeed->setBatchSize(100);
$dataFeed->setLimit(1000);

$dataFeed->setCallback('status',
  function(&$data, $index, $value) {
    return strtoupper($value);
  }
);

$dataFeed->setCallback('source',
  function(&$data, $index, $value) use ($args) {
    return $value.' '.(array_key_exists('common', $data[$index]['organism']) ? $data[$index]['organism']['common'] : '').' '.$args['yup'];
  },
);

$dataFeed->walkFeed(function(&$data) use ($args) {
  // echo "chunk".PHP_EOL;
  print_r($data);
  print_r($args);
  test();
});

echo "done".PHP_EOL;

$ru = getrusage();

echo "This process used " . rutime($ru, $rustart, "utime") .
    " ms for its computations".PHP_EOL;
echo "It spent " . rutime($ru, $rustart, "stime") .
    " ms in system calls".PHP_EOL;

function rutime($ru, $rus, $index) {
  return ($ru["ru_$index.tv_sec"]*1000 + intval($ru["ru_$index.tv_usec"]/1000))
    -  ($rus["ru_$index.tv_sec"]*1000 + intval($rus["ru_$index.tv_usec"]/1000));
}

function test() {
  echo 'wow'.PHP_EOL;
}
