<?php
require_once '../config.php';

if (isset($_GET['id'])) {
    $id = $_GET['id'];
    $stmt = $pdo->prepare("DELETE FROM tutorials WHERE id = :id");
    $stmt->execute(['id' => $id]);

    header("Location: list_tutoriais.php");
    exit;
} else {
    header("Location: list_tutoriais.php?error=ID não encontrado");
    exit;
}
?>
