<?php

class DataFeed {

  /**
   * User Variables
   */
  public $localFile;
  public $feedName;
  public $node;
  public $config;
  public $batchSize;
  public $limit;

  /**
   * Class Variables
   */
  public $data = array();
  public $callbacks = array();
  public $args = array();
  public $configs;

  /**
   * Config Variables
   */
  public $skipFields = array();
  public $renameFields = array();
  public $skipEmptyFlag = false;

  function __construct($feedName = null, $localFile = null, $config = null, $batchSize = null) {
    if($feedName) $this->$feedName = $feedName;
    if($localFile) $this->$localFile = $localFile;
    if($config) $this->$config = $config;
    if($batchSize) $this->batchSize = $batchSize;
  } 

  /**
   * Set the starting node
   */
  function setNode($node) {
    $this->node = $node;
  }

  /**
   * Set the feed name
   */
  function setFeed($feedName) {
    $this->feedName = $feedName;
  }

  /**
   * Set the file name
   */
  function setFile($localFile) {
    $this->localFile = $localFile;
  }

  /**
   * Read in the json config file and set the class variables with the settings and data
   */
  function setConfig($config) {
    $string = file_get_contents($config);
    $json_a = json_decode($string, true);
    $this->configs = $json_a;

    $this->skipEmptyFlag = $this->configs['skipEmptyFields'];
    
    foreach($this->configs['renameFields'] as $index => $array) {
      // Turn into an associative index: path -> rename_value
      $this->renameFields[array_key_first($array)] = array_values($array)[0];
    }

    foreach($this->configs['skipFields'] as $field) {
      $this->skipFields[] = $field;
    }
  }

  /**
   * Set the chunk size for walking the xml
   */
  function setBatchSize($batchSize) {
    $this->batchSize = $batchSize;
  }

  function setLimit($limit) {
    $this->limit = $limit;
  }

  /**
   * Loads the entire feed to memory as an assoc
   */
  function getFeed() {
    $reader = new XMLReader;
    $reader->open($this->localFile);

    while($reader->read()) {
      if($reader->nodeType == XMLReader::ELEMENT && $reader->name == $this->node) {
        $doc = new DOMDocument('1.0', 'UTF-8');
        $xml = simplexml_import_dom($doc->importNode($reader->expand(),true));
        $this->data[] = $this->xmlToAssoc($xml);
      }
    }
    return $this->data;
  }

  /**
   * Walk the feed in batchSize amounts, calling the callback function
   */
  function walkFeed($callback = null) {
    $reader = new XMLReader;
    $reader->open($this->localFile);

    $count = 0;
    $lastBatch = 0;

    while($reader->read()) {
      if($count >= $this->batchSize || $count >= $this->limit) {
        $this->applyFixes($this->data);
        if($lastBatch >= $this->batchSize && isset($callback) && is_callable($callback)) {
          $callback($this->data);
          $lastBatch = 0;
        } 
        $this->data = array();
        if($count >= $this->limit)
         break;
      }
      if($reader->nodeType == XMLReader::ELEMENT && $reader->name == $this->node) {
        $doc = new DOMDocument('1.0', 'UTF-8');
        $xml = simplexml_import_dom($doc->importNode($reader->expand(),true));
        $this->data[] = $this->xmlToAssoc($xml);
        $count++;
        $lastBatch++;
      }
    }
    // Apply fixes and callbacks for last batch (or first batch if under batch limit)
    $this->applyFixes($this->data);
    if(isset($callback) && is_callable($callback)) {
      $callback($this->data);
    } 
    $this->data = array();
  }

  /**
   * Parses the xml entity to a assoc array
   */
  function xmlToAssoc($xml) {
    $items = array();
    foreach($xml as $key => $value) {
      $items[$key] = $this->getNode($value);
    }
    $this->applyConfigs($items);  // Apply config options to the current item
    // Create item class and store it here
    // Move all other functions to the item class
    return $items;
  }

  /**
   * Recursively get the node value
   */
  function getNode($value) {
    $items = array();
    $count = count($value);
    $item_count = array();
    if($count > 0) {
      foreach($value as $subKey => $subVal) {
        if(array_key_exists((string)$subKey, $items)) {
          $i = array_key_exists((string)$subKey, $item_count) ? $item_count[(string)$subKey] : 1;
          $items[(string)$subKey.$i] = $this->getNode($subVal);
          $item_count[(string)$subKey] = ++$i;
        } else {
          $items[(string)$subKey] = $this->getNode($subVal);
        }
      }
      return $items;
    } else {
      return(string)$value;
    }
  }

  /**
   * Wrapper for the config functions
   * TODO RE-WRITE THIS, only nests 1 levels currently
   */
  function applyConfigs(&$items) {
    $fields = $this->skipFields;
    foreach($fields as $key) {
      $keys = explode('.', $key);
      $this->skipSetFields($items, $keys);
    }
    $this->skipEmptyFields($items);
  }

  /**
   * Skips the set fields recursively
   */
  function skipSetFields(&$node, $keys) {
    $key = array_shift($keys);
    if (count($keys) === 0) {
      unset($node[$key]);
    }
    else {
      // Check that this node has children
      if(is_array($node) && count($node) > 0 && array_key_exists($key, $node)) {
        $this->skipSetFields($node[$key], $keys);
      }
    }
  }

  /**
   * Skips the empty fields recursively
   */
  function skipEmptyFields(&$node) {
    if (count($node) === 0) {
      return;
    }
    else {
      // Check each child of the current node
      foreach($node as $key => $value) {
        if($this->skipEmptyFlag && empty($value)) {
          unset($node[$key]);
        }
      }
    }
  }

  /**
   * Wrapper for post config fixes + callbacks
   */
  function applyFixes(&$items) {
    $this->renameFields();
    foreach($items as $index => $item) {
      $this->applyCallbacks($items[$index], $index);
    }
  }

  /**
   * Gets the list of fields to rename from the configs file and loops through them matching the fields
   * to the data stored in memory.
   */
  function renameFields() {
    $fields = $this->renameFields;
    $fields = array_keys($fields);
    // For each field to rename
    foreach($fields as $row) { 
      $keys = explode('.', $row);
      $count = count($this->data);
      // For each data item
      for($i = 0; $i <= $count; $i++) {
        // Check if index exists or else it creates an empty one!
        if(array_key_exists($i, $this->data)) {
          // Pass the direct reference of the data to allow editing
          $this->renameField($this->data[$i], $keys, $this->renameFields[$row]);
        }
      }
    }
  }

  /**
   * Recursively searches the array for the given key.
   * Keys are shifted/popped each call, the data is re-indexed
   * at every call to the next child/index
   * 
   * Grabs the current value of the field, delete the key, puts the new key in with the old value
   * 
   * Order is not preserved
   */
  public function renameField(&$node, $keys, $newField) {
    $key = array_shift($keys);
    if (count($keys) === 0) {
      $value = $node[$key];
      unset($node[$key]);  //  Unset the old field
      $node[$newField] = $value; //  Set the new field
    }
    else {
      // Check that this node has children
      if(is_array($node) && count($node) > 0 && array_key_exists($key, $node)) {
        $this->renameField($node[$key], $keys, $newField);
      }
    }
  }

  /**
   * Recursively search for a field and delete it
   */
  public function deleteField(&$node, $keys) {
    $key = array_shift($keys);

    if (count($keys) == 0)
      unset($node[$key]);
    else {
      if(is_array($node) && count($node) > 0 && array_key_exists($key, $node)) {
        $this->deleteField($node[$key], $keys);
      }
    }
  }

  /**
   * Kicks off the callback stack to check the data for any callbacks on
   * keys
   */
  function applyCallbacks(&$node, $index) {
    if (count($node) === 0) {
      return;
    }
    else {
      // Check each child of the current node
      foreach($node as $key => $value) {
        // echo "Checking $key".PHP_EOL;
        if($this->hasCallback($key)) {
          // echo "Found callback, KEY: $key INDEX: $index VALUE: ".$this->applyCallback($node[$key], $key, $index).PHP_EOL;
          $node[$key] = $this->applyCallback($node[$key], $key, $index);
        }
        else if(is_array($node[$key]) && count($node[$key]) > 0) {
          $this->applyCallbacks($node[$key], $index);
        }
      }
    }
  }

  /**
   * A callback function takes 3 parameters
   * $data, the current data stored in memory
   * $index, the current index in the dataset we are applying the callback on
   * $value, the callback node value, for convience
   */
  function setCallback($key, $callback) {
    $this->callbacks[$key] = $callback;
  }

  /**
   * Clear a callback from a node
   */
  function clearCallback($key) {
    $this->callbacks[$key] = null;
  }

  /**
   * Check if a node has a callback
   */
  function hasCallback($key) {
    if(isset($this->callbacks[$key]) && !empty($this->callbacks[$key])) return true;
  }

  /**
   * applies the callback on the given index
   * 
   * Passes the data and index as reference to the callback function
   */
  function applyCallback($value, $key, $index) {
    return $this->callbacks[$key]($this->data, $index, $value);
  }

}