<?php

include('Migration.php');

class Migrator {
	private $conn;
	private $db;
	
	public function __construct($args = array()) {
		$host = $args['host'] ? $args['host'] : 'localhost';
		$user = $args['user'] ? $args['user'] : get_current_user();
		$pass = $args['pass'] ? $args['pass'] : '';
		$this->_connect($host, $user, $pass);

		$args['db'] && $this->db = $args['db'];
	}
	
	public function create_database() {
		$stmt = $this->conn->stmt_init();
		$stmt->prepare("CREATE DATABASE " . $this->db);
		$stmt->execute();
		if ($stmt->errno) {
			throw new Exception($stmt->error);
		}
	}
	
	public function drop_database() {
		$stmt = $this->conn->stmt_init();
		$stmt->prepare("DROP DATABASE " . $this->db);
		$stmt->execute();
		if ($stmt->errno) {
			throw new Excpetion($stmt->errno);
		}
	}
	
	public function migrate($new_version = NULL) {
		if (! $this->db) throw new Exception("A database name must be provided!\n");
		$this->conn->select_db($this->db);
		if ($this->conn->connect_errno) {
			throw new Exception($this->conn->connect_error);
		}

		## TODO move the _create_migrations_table call into the version method
		$current_version = $this->version() or $this->_create_migrations_table();

		$this->migrations();
	}

	public function migrations() {
		$migrations = array();
		$files = scandir('../db/migrations');
		foreach ($files as $file) {
		  if (! preg_match('/^[\d]{14}_.*.php/', $file)) continue;
			preg_match('/^([\d]{14})_(.*)\.php/', $file, $matches);
			$version = $matches[1];
			$name = join(array_map('ucfirst', preg_split('/_/', $matches[2])));
			foreach ($migrations as $migration) {
				if ($version == $migration->version) {
					throw new Exception("Duplicate migration version " . $version);
				}
				if ($name == $migration->name) {
					throw new Exception("Duplicate migration name " . $name);
				}
			}
			$migrations[] = new Migration($version, $name, $file);
		}
	}

	public function version() {
		$result = $this->conn->query("SELECT version, name FROM migrations");
	}
	
	private function _connect($host, $user, $pass) {
		$this->conn = new mysqli($host, $user, $pass);
		if ($this->conn->connect_errno) {
			throw new Exception($this->conn->connect_error . "\n");
		}
	}
	
	private function _create_migrations_table() {
		$stmt = $this->conn->stmt_init();
		$stmt->prepare(
			"CREATE TABLE IF NOT EXISTS migrations (\n" .
			"  version bigint not null,\n" .
			"  name tinytext not null,\n" .
			"  primary key (version, name(255))\n" .
			")"
		);
		$stmt->execute();
		if ($stmt->errno) {
			die($stmt->error . "\n");
		}
	}
}
?>
