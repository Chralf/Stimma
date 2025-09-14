<?php
require_once 'config.php';

/**
 * Databasanslutningsklass
 */
class Database {
    private static $instance = null;
    private $conn;
    
    private function __construct() {
        try {
            $dsn = DB_CONNECTION . ':host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_DATABASE . ';charset=utf8mb4';
            $this->conn = new PDO($dsn, DB_USERNAME, DB_PASSWORD);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $this->conn->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        } catch (PDOException $e) {
            die("Database connection failed: " . $e->getMessage());
        }
    }
    
    /**
     * Hämta databasanslutningsinstans (singleton-mönster)
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance->conn;
    }
    
    /**
     * Förhindra kloning av instansen
     */
    private function __clone() {}
}

/**
 * Hämta databasanslutning
 * 
 * @return PDO Databasanslutning
 */
function getDb() {
    return Database::getInstance();
}

/**
 * Utför en fråga och returnera alla resultat
 * 
 * @param string $sql SQL-fråga med platshållare
 * @param array $params Parametrar för frågan
 * @return array Resultat av frågan
 */
function query($sql, $params = []) {
    try {
        $db = getDb();
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Utför en fråga och returnera en enda rad
 * 
 * @param string $sql SQL-fråga med platshållare
 * @param array $params Parametrar för frågan
 * @return array|null En rad eller null om ingen hittades
 */
function queryOne($sql, $params = []) {
    try {
        $db = getDb();
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch();
        return $result === false ? null : $result;
    } catch (PDOException $e) {
        return null;
    }
}

/**
 * Utför en fråga och returnera infogat ID
 * 
 * @param string $sql SQL-fråga med platshållare
 * @param array $params Parametrar för frågan
 * @return int|null ID för den senast infogade raden eller null vid fel
 */
function execute($sql, $params = []) {
    try {
        $db = getDb();
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $db->lastInsertId();
    } catch (PDOException $e) {
        return null;
    }
}

/**
 * Utför en fråga och returnera alla resultat (alias för query)
 * 
 * @param string $sql SQL-fråga med platshållare
 * @param array $params Parametrar för frågan
 * @return array Resultat av frågan
 */
function queryAll($sql, $params = []) {
    return query($sql, $params);
}
