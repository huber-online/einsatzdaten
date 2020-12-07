<?php
/**
 * Created by PhpStorm.
 * User: uhh
 * Date: 24.02.2017
 * Time: 10:04
 */
/*
========================================================================================

 Klasse für MongoDB

========================================================================================
*/
class Mongo
{
    var $HOST = "localhost";
    var $PORT = "27017";
    var $DBNAME = "";
    var $options = array();

    public static $connection;
    public static $database;
    public $collection;

    public function __construct($host="",$port="",$dbname="",$options=array())
    {
        if (!empty(self::$connection)) return;
        if ($host > "") $this->HOST = $host;
        if ($port > "") $this->PORT = $port;
        if ($dbname > "") $this->DBNAME = self::$database = $dbname;

        $connectionString = sprintf('mongodb://%s:%d',
            $this->HOST,
            $this->PORT);

        try
        {
            self::$connection = new MongoDB\Driver\Manager($connectionString,$options);
        }
        catch (Exception $e)
        {
            throw $e;
        }
    }

    public function setCollection($name)
    {
        return $this->collection = $name ;
    }

    public function listCollections()
    {
        return $this->database->getCollectionNames();
    }
    public function  query($filter=array(),$options=null)
    {
        if (empty($options)) $options  = ['projection'=>['_id'=>0]];
        $options['writeConcern'] = ['w'=>2,'j'=>true,'wtimeout'=>5000];
        $query = new MongoDB\Driver\Query($filter,$options);
        $cursor = self::$connection->executeQuery(self::$database.".".$this->collection,$query);
        return $cursor;
    }
    public function findOne($filter=array(),$options=null)
    {
        $cur = $this->query($filter,$options);
        $cur = iterator_to_array($cur);
        if (empty($cur)) return false;
        return $cur[0];
    }
    public function insert($insert)
    {
        $this->initWriter();
        $this->writer->insert($insert);
        return $this->writeResult();
    }
    public function update($filter,$data,$options=array())
    {
        $this->initWriter();
        $this->writer->update($filter,$data,$options);
        return $this->writeResult();
    }
    public function delete($filter,$options=array())
    {
        $this->initWriter();
        $this->writer->delete($filter,$options);
        return $this->writeResult();
    }
    private function initWriter()
    {
        $this->writer = new MongoDB\Driver\BulkWrite();
    }
    private function writeResult()
    {
        return self::$connection->executeBulkWrite(self::$database.".".$this->collection,$this->writer);
    }
    public function distinct($field,$query=null)
    {
        $cmd = new MongoDB\Driver\Command (['distinct'=>$this->collection,'key'=>$field,'query'=>$query]);
        $cur = self::$connection->executeCommand(self::$database,$cmd);
        return current($cur->toArray())->values;
    }
    public function count($query)
    {
        $cmd = new MongoDB\Driver\Command(['count'=>$this->collection,'query'=>$query]);
        $cur =  self::$connection->executeCommand(self::$database,$cmd);
        return current($cur->toArray())->n;
    }
    public function createIndex($field,$dir)
    {
        $command = new MongoDB\Driver\Command([
            "createIndexes" => $this->collection,
            "indexes"       => [[
                "key"  => [ $field => (int)$dir],
                "ns"   => self::$database.".".$this->collection,
                "name" => $this->collection.'-'.$field
            ]],
        ]);
        self::$connection->executeCommand(self::$database, $command);
    }
    public function aggregate($pipeline)
    {
        $ret = [];
        $command = new MongoDB\Driver\Command([
            "aggregate" => $this->collection,
            "pipeline"  => $pipeline,
            "cursor" => [ "batchSize" => 0 ]
        ]);
        $cur = self::$connection->executeCommand(self::$database, $command);
        if (!empty($cur))
        {
            foreach ($cur as $data)
            {
                $ret[] = $data;
            }
            if (is_array($ret) && count($ret) > 0)
            {
                return $ret;
            }
        }
        return false;
    }
}

