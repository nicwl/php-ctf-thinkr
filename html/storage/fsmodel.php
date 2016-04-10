<?php
require_once('model.php');

class FSModel extends Model {
   const MAX_ID_LENGTH = 32;

	static function getDir() {
		return "/var/www/html/data/".static::class."/";
	}

	static function getFields() {
		return ['id', 'data'];
	}

	private static function getObjFile($id) {
		return static::getDir() . $id;
	}

   private function getFile() {
      return static::getObjFile($this->getField('id'));
   }

	function save() {
      mkdir(static::getDir(), 0777, TRUE);
      $fh = fopen($this->getFile(), 'w');
      flock($fh, LOCK_EX);
      fwrite($fh, $this->getField('data'));
      fflush($fh);
      flock($fh, LOCK_UN);
      fclose($fh);
   }

   function del() {
      throw new Exception("Can't delete.");
   }

   static function load($id) {
      if (strlen($id) > self::MAX_ID_LENGTH) {
         throw new Exception("ID exceeds maximum length");
         return NULL;
      }
      mkdir(static::getDir(), 0777, TRUE);
      $data = file_get_contents(static::getObjFile($id));
      if ($data === FALSE) {
         throw new Exception("Could not find storage at ".static::getObjFile($id));
      }
      return self::fromFields([$id, $data]);
   }

   static function create() {
      return new static();
   }
}