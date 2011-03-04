<?php

include 'Migration.php';

class Migrator {
	private $conn;
	private $db;
	private $migrations = array();
	
	public function __construct($args = array()) {
		$host = $args['host'] ? $args['host'] : 'localhost';
		$user = $args['user'] ? $args['user'] : get_current_user();
		$pass = $args['pass'] ? $args['pass'] : '';
		$this->_connect($host, $user, $pass);
		
		$args['db'] && $this->db = $args['db'];

		$this->_load_migrations();
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
		
		foreach ($this->migrations as $migration) {
			print "$migration->name\n";
			print "  " . $migration->name . "\n";
			print "  " . $migration->version . "\n";
			print "  " . $migration->file . "\n";
		}
	}
	
	public function version() {
		$result = $this->conn->query("SELECT version, name FROM migrations");
		if ($result) {
			return $result;
		} elseif ($this->conn->errno == 1146) {
			$this->_create_migrations_table();
			return $result;
		} else {
			throw new Exception("Unable to create migrations table!");
		}
	}
	
#	private function _applied_migrations() {
#		$migrations;
#		$query = 'SELECT version, name FROM migrations';
#		if ($result = $this->conn->query($query)) {
#			while ($row = $result->fetch_assoc()) {
#				$migrations[] = new $row['name']($row['version'], $row['name']);
#			}
#			return $migrations;
#		} else {
#			throw new Exception($stmt->error);
#		}
#	}

	private function _connect($host, $user, $pass) {
		$this->conn = new mysqli($host, $user, $pass);
		if ($this->conn->connect_errno) {
			throw new Exception($this->conn->connect_error);
		}
	}
	
	private function _create_migrations_table() {
		$stmt = $this->conn->stmt_init();
		$stmt->prepare(
			"CREATE TABLE migrations (\n" .
			"  version bigint not null,\n" .
			"  name tinytext not null,\n" .
			"  primary key (version, name(255))\n" .
			")"
		);
		$stmt->execute();
		if ($stmt->errno) {
			throw new Exception($stmt->error);
		}
	}
	
	private function _load_migrations() {
		$files = scandir('../db/migrations');
		foreach ($files as $file) {
		  if (! preg_match('/^[\d]{14}_.*.php/', $file)) continue;
			preg_match('/^([\d]{14})_(.*)\.php/', $file, $matches);
			$version = $matches[1];
			$name = join(array_map('ucfirst', preg_split('/_/', $matches[2])));
			foreach ($this->migrations as $migration) {
				if ($version == $migration->version) {
					throw new Exception("Duplicate migration version " . $version);
				}
				if ($name == $migration->name) {
					throw new Exception("Duplicate migration name " . $name);
				}
			}
			include '../db/migrations/' . $file;
			$this->migrations[$name] = new $name($version, $name, $file);
		}
	}
}
?>
