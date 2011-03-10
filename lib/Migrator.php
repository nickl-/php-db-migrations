<?php

include 'Migration.php';

class Migrator {
	public static $conn;
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
		$stmt = self::$conn->stmt_init();
		$stmt->prepare("CREATE DATABASE " . $this->db);
		$stmt->execute();
		if ($stmt->errno) {
			throw new Exception($stmt->error);
		}
	}
	
	public function drop_database() {
		$stmt = self::$conn->stmt_init();
		$stmt->prepare("DROP DATABASE " . $this->db);
		$stmt->execute();
		if ($stmt->errno) {
			throw new Exception($stmt->errno);
		}
	}
	
	public function migrate($new_version = null) {
		if (! $this->db) throw new Exception("A database name must be provided!\n");
		self::$conn->select_db($this->db);
		if (self::$conn->connect_errno) {
			throw new Exception(self::$conn->connect_error);
		}
		
		foreach ($this->migrations as $migration) {
			if (! $migration->applied()) {
				if (! isset($new_version) || $migration->version <= $new_version) {
					print "Applying " . $migration->name . "... ";
					$migration->up();
					$this->_add_to_migrations_table($migration);
					print "Done.\n";
				}
			}
		}

		if (isset($new_version)) {
			foreach (array_reverse($this->migrations) as $migration) {
				if ($migration->version > $new_version && $migration->applied()) {
					print "Reverting " . $migration->name . "... ";
					$this->_remove_from_migrations_table($migration);
					$migration->down();
					print "Done.\n";
				}
			}
		}
	}
	
	private function _add_to_migrations_table($migration) {
		$stmt = self::$conn->stmt_init();
		$stmt->prepare("INSERT INTO migrations (version, name) VALUES (?, ?)");
		if ($stmt->errno) {
			if ($stmt->errno == 1146) {
				if ($this->_create_migrations_table()) {
					$stmt->prepare("INSERT INTO migrations (version, name) VALUES (?, ?)");
				} else {
					throw new Exception($stmt->error);
				}
			} else {
				throw new Exception($stmt->error);
			}
		}
		$stmt->bind_param('is', $migration->version, $migration->name);
		$stmt->execute();
	}

	private function _connect($host, $user, $pass) {
		self::$conn = new mysqli($host, $user, $pass);
		if (self::$conn->connect_errno) {
			throw new Exception(self::$conn->connect_error);
		}
	}
	
	private function _create_migrations_table() {
		$stmt = self::$conn->stmt_init();
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
		return true;
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
	
	private function _remove_from_migrations_table($migration) {
		$stmt = self::$conn->stmt_init();
		$stmt->prepare("DELETE FROM migrations WHERE version = ? and name = ?");
		if ($stmt->errno) {
			throw new Exception($stmt->error);
		}
		$stmt->bind_param('is', $migration->version, $migration->name);
		$stmt->execute();
		return $stmt->affected_rows;
	}
}
?>
