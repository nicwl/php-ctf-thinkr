<?php
require_once('model.php');

abstract class CSVModel extends Model {
   static function getStorage() {
      return "/var/www/html/data/".static::class.".csv";
   }

   protected function getRow() {
      $row = array();
      $fields = $this->getFields();
      for ($i = 0; $i < count($fields); $i++) {
         $row[] = $this->getField($fields[$i]);
      }
      return $row;
   }

   function save() {
      touch($this->getStorage());
      $fh = fopen($this->getStorage(), 'a');
      flock($fh, LOCK_EX);
      fputcsv($fh, $this->getRow());
      fflush($fh);
      flock($fh, LOCK_UN);
      fclose($fh);
   }

   function del() {
      throw new Exception("Can't delete.");
   }

   static function load($id) {
      touch(static::getStorage());
      $fh = fopen(static::getStorage(), 'r');
      $key_idx = array_search("id", static::getFields());
      if ($key_idx === FALSE) {
         throw new Exception("CSV models must have an ID field");
      }
      flock($fh, LOCK_SH);

      $most_recent = NULL;
      $row = fgetcsv($fh);
      while ($row !== FALSE && $row !== NULL) {
         if ($row[$key_idx] === $id) {
            $most_recent = $row;
         }
         $row = fgetcsv($fh);
      }

      flock($fh, LOCK_UN);
      fclose($fh);

      if ($most_recent == NULL) {
         return NULL;
      }
      return self::fromFields($most_recent);
   }

   static function all($limit = NULL) {
      touch(static::getStorage());
      $fh = fopen(static::getStorage(), 'r');
      $key_idx = array_search("id", static::getFields());
      if ($key_idx === FALSE) {
         throw new Exception("CSV models must have an ID field");
      }
      flock($fh, LOCK_SH);

      $most_recent = array();
      $row = fgetcsv($fh);
      while ($row !== FALSE && $row !== NULL) {
         $most_recent[$row[$key_idx]] = $row;
         $row = fgetcsv($fh);
      }

      flock($fh, LOCK_UN);
      fclose($fh);

      $result = [];
      foreach ($most_recent as $key=>$fields) {
         $result[$key] = static::fromFields($fields);
      }
      shuffle($result);
      if ($limit !== NULL) {
         $result = array_slice($result, 0, $limit);
      }
      return $result;
   }

   static function create() {
      return new static();
   }
}