<?php
// comentarios.php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

// Receber dados enviados por POST
$id_chamado = isset($_POST['id_chamado']) ? intval($_POST['id_chamado']) : 0;
$comentario = trim($_POST['comentario'] ?? '');
$id_usuario = $_SESSION['usuario_id'] ?? null;

if (!$id_usuario || !$id_chamado || empty($comentario)) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Dados inválidos!']);
    exit;
}

// Inserir comentário no banco
$stmt = $pdo->prepare("INSERT INTO comentarios (id_chamado, id_usuario, comentario, data_comentario) VALUES (?, ?, ?, NOW())");
if ($stmt->execute([$id_chamado, $id_usuario, $comentario])) {
    echo json_encode(['status' => 'sucesso', 'mensagem' => 'Comentário adicionado com sucesso!']);
} else {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Erro ao salvar comentário!']);
}
