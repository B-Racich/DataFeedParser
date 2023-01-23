<?php

require('../Class/DataFeed.class.php');

$rustart = getrusage();

$dataFeed = new DataFeed();

$args = [];

$dataFeed->setConfig('./data/config.json');


$dataFeed->setFeed('feed.xml');
$dataFeed->setFile('data/feed.xml');
$dataFeed->setNode('book');
$dataFeed->setBatchSize(100);
$dataFeed->setLimit(1000);

// Here we attach a callback to a specific node found in the xml. IE publish_date
$dataFeed->setCallback('publish_date',
  function(&$data, $index, $value) {
    $date = new DateTime($value);
    $new_date = $date->format('Y-m-d H:i:s');
    return strtoupper($new_date);
  }
);

// Here we can supply a function to be called as the feed is walked (useful for debugging here)
$dataFeed->walkFeed(function(&$data) {
  // echo "chunk".PHP_EOL;
  print_r($data);
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
