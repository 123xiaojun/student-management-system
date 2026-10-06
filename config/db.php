<?php
/**
 * 数据库连接类 - 支持 MySQL 和 SQLite
 * 使用 PDO 进行数据库操作
 */
class Database {
    private static $instance = null;
    private $pdo;

    private function __construct() {
        try {
            if (DB_TYPE === 'mysql') {
                $dsn = 'mysql:host=' . MYSQL_HOST . ';port=' . MYSQL_PORT . 
                       ';dbname=' . MYSQL_DBNAME . ';charset=' . MYSQL_CHARSET;
                $this->pdo = new PDO($dsn, MYSQL_USERNAME, MYSQL_PASSWORD, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]);
            } else {
                // SQLite
                $dbPath = SQLITE_PATH;
                if (!file_exists(dirname($dbPath))) {
                    mkdir(dirname($dbPath), 0755, true);
                }
                $this->pdo = new PDO('sqlite:' . $dbPath);
                $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
                // 启用外键约束
                $this->pdo->exec('PRAGMA foreign_keys = ON');
            }
        } catch (PDOException $e) {
            die('数据库连接失败: ' . $e->getMessage());
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->pdo;
    }

    // 快捷查询方法
    public function query($sql, $params = []) {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public function fetchAll($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }

    public function fetchOne($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetch() ?: null;
    }

    public function insert($sql, $params = []) {
        $this->query($sql, $params);
        return $this->pdo->lastInsertId();
    }

    public function execute($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }
}
