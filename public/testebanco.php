<?php
try {
    $pdo = new PDO(
        "mysql:host=ferramentas_mysql;dbname=ferramentas;charset=utf8",
        "mysql",
        "9a1e44e81a936202c3e",
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
} catch (PDOException $e) {
    error_log("Erro de conexão: " . $e->getMessage());
    die("Erro de conexão com o banco de dados");
}
?>