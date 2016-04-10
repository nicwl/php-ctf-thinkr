<?php

abstract class Model {
   private $data;

   function __construct() {
      $this->data = array();
   }

   abstract function save();
   abstract static function load($id);
   abstract function del();
   abstract static function getFields();

   static function create() {
      return new static;
   }

   static function loadEnforce($id) {
      $obj = static::load($id);
      if ($obj === NULL) {
         throw new Exception('No '.static::class.' with id '.$id);
      }
   }

   public function isField($name) {
      return in_array($name, $this->getFields(), TRUE);
   }

   public function setField($name, $value) {
      $this->data[$name] = $value;
   }

   public function getField($name) {
      return $this->data[$name];
   }

   protected static function fromFields($fields) {
      if (count(static::getFields()) != count($fields)) {
         throw new Exception("Number of fields in storage does not match number of fields in model.");
      }
      $obj = new static;
      foreach ($fields as $key => $value) {
         $obj->setField(static::getFields()[$key], $value);
      }
      return $obj;
   }
}