<?php
include 'includes/auth.php';
include 'includes/db.php';

if ($_SESSION['usuario_tipo'] === 'usuario') {
    die("Acesso negado!");
}

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $stmt = $conn->prepare("DELETE FROM equipamentos WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
}

header("Location: equipamentos.php");
exit;
