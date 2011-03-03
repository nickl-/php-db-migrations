<?php
class Migration {
	public $version;
	public $name;
	public $file;

	public function __construct($version = NULL, $name = NULL, $file = NULL) {
		$this->version = $version;
		$this->name = $name;
		$this->file = $file;
	}
}
?>
