<?php

class Database {
    private static $instance = null;
    private $connection;
    
    private function __construct() {
        try {
            $this->connection = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
            
            if ($this->connection->connect_error) {
                throw new Exception("Database connection failed: " . $this->connection->connect_error);
            }
            
            $this->connection->set_charset(DB_CHARSET);
            
        } catch (Exception $e) {
            error_log("Database connection error: " . $e->getMessage());
            throw $e;
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->connection;
    }
    
    public function query($sql, $params = []) {
        try {
            if (empty($params)) {
                $result = $this->connection->query($sql);
                if ($result === false) {
                    throw new Exception("Query failed: " . $this->connection->error);
                }
                return $result;
            }
            
            $stmt = $this->connection->prepare($sql);
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $this->connection->error);
            }
            
            if (!empty($params)) {
                $types = '';
                foreach ($params as $param) {
                    if (is_int($param)) {
                        $types .= 'i';
                    } elseif (is_float($param)) {
                        $types .= 'd';
                    } else {
                        $types .= 's';
                    }
                }
                $stmt->bind_param($types, ...$params);
            }
            
            $stmt->execute();
            $result = $stmt->get_result();
            $stmt->close();
            
            return $result;
            
        } catch (Exception $e) {
            error_log("Database query error: " . $e->getMessage());
            throw $e;
        }
    }
    
    public function insert($table, $data) {
        $columns = implode(', ', array_map(function($key) {
            // Handle backtick-wrapped column names
            return strpos($key, '`') === 0 ? $key : '`' . $key . '`';
        }, array_keys($data)));
        $placeholders = str_repeat('?,', count($data) - 1) . '?';
        
        $sql = "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})";
        
        // Add IGNORE for safer inserts
        if (strpos($sql, 'INSERT INTO') === 0) {
            $sql = str_replace('INSERT INTO', 'INSERT IGNORE INTO', $sql);
        }
        
        $stmt = $this->connection->prepare($sql);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $this->connection->error);
        }
        
        $types = '';
        $values = [];
        foreach ($data as $key => $value) {
            // Remove backticks from key for value processing
            $cleanKey = str_replace('`', '', $key);
            if (is_int($value)) {
                $types .= 'i';
            } elseif (is_float($value)) {
                $types .= 'd';
            } else {
                $types .= 's';
            }
            $values[] = $value;
        }
        
        $stmt->bind_param($types, ...$values);
        $result = $stmt->execute();
        $insertId = $this->connection->insert_id;
        $stmt->close();
        
        if (!$result) {
            throw new Exception("Insert failed: " . $this->connection->error);
        }
        
        return $insertId;
    }
    
    public function update($table, $data, $where, $whereParams = []) {
        $setParts = [];
        foreach (array_keys($data) as $column) {
            $setParts[] = "{$column} = ?";
        }
        $setClause = implode(', ', $setParts);
        
        $sql = "UPDATE {$table} SET {$setClause} WHERE {$where}";
        
        $stmt = $this->connection->prepare($sql);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $this->connection->error);
        }
        
        $types = '';
        $values = [];
        
        // Add data values
        foreach ($data as $value) {
            if (is_int($value)) {
                $types .= 'i';
            } elseif (is_float($value)) {
                $types .= 'd';
            } else {
                $types .= 's';
            }
            $values[] = $value;
        }
        
        // Add where values
        foreach ($whereParams as $value) {
            if (is_int($value)) {
                $types .= 'i';
            } elseif (is_float($value)) {
                $types .= 'd';
            } else {
                $types .= 's';
            }
            $values[] = $value;
        }
        
        $stmt->bind_param($types, ...$values);
        $result = $stmt->execute();
        $affectedRows = $stmt->affected_rows;
        $stmt->close();
        
        if (!$result) {
            throw new Exception("Update failed: " . $this->connection->error);
        }
        
        return $affectedRows;
    }
    
    public function delete($table, $where, $whereParams = []) {
        $sql = "DELETE FROM {$table} WHERE {$where}";
        
        $stmt = $this->connection->prepare($sql);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $this->connection->error);
        }
        
        if (!empty($whereParams)) {
            $types = '';
            foreach ($whereParams as $param) {
                if (is_int($param)) {
                    $types .= 'i';
                } elseif (is_float($param)) {
                    $types .= 'd';
                } else {
                    $types .= 's';
                }
            }
            $stmt->bind_param($types, ...$whereParams);
        }
        
        $result = $stmt->execute();
        $affectedRows = $stmt->affected_rows;
        $stmt->close();
        
        if (!$result) {
            throw new Exception("Delete failed: " . $this->connection->error);
        }
        
        return $affectedRows;
    }
    
    public function fetchAll($sql, $params = []) {
        $result = $this->query($sql, $params);
        $data = [];
        
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
        }
        
        return $data;
    }
    
    public function fetchOne($sql, $params = []) {
        $result = $this->query($sql, $params);
        
        if ($result && $result->num_rows > 0) {
            return $result->fetch_assoc();
        }
        
        return null;
    }
    
    public function beginTransaction() {
        $this->connection->autocommit(false);
    }
    
    public function commit() {
        $this->connection->commit();
        $this->connection->autocommit(true);
    }
    
    public function rollback() {
        $this->connection->rollback();
        $this->connection->autocommit(true);
    }
    
    public function getLastInsertId() {
        return $this->connection->insert_id;
    }
    
    public function escape($string) {
        return $this->connection->real_escape_string($string);
    }
    
    public function __destruct() {
        if ($this->connection) {
            $this->connection->close();
        }
    }
}