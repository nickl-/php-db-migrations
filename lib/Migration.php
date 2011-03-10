<?php
include_once('Migrator.php');

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

	public function applied() {
		$stmt = Migrator::$conn->stmt_init();
		$stmt->prepare(
			"SELECT version, name \n" .
			"FROM migrations \n" .
			"WHERE version = ? and name = ?"
		);
		if ($stmt->errno == 1146) {
			return false;
		}
		$stmt->bind_param('is', $this->version, $this->name);
		$stmt->execute();
		$stmt->store_result();
		return $stmt->num_rows();
	}
}
?>
