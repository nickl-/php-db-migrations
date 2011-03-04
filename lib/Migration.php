<?php
abstract class Migration {
	public $version;
	public $name;
	public $file;
	
	abstract public function up();
	abstract public function down();

	public function __construct($version = null, $name = null, $file = null) {
		$this->version = $version;
		$this->name = $name;
		$this->file = $file;
	}

}
?>
