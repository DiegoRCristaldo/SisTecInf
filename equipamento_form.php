<?php
include 'includes/auth.php';
include 'includes/db.php';

if ($_SESSION['usuario_tipo'] === 'usuario') {
    die("Acesso negado!");
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$nome = $patrimonio = $secao = "";

if ($id > 0) {
    $stmt = $conn->prepare("SELECT * FROM equipamentos WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $equip = $stmt->get_result()->fetch_assoc();
    if ($equip) {
        $nome = $equip['nome'];
        $patrimonio = $equip['ip'];
        $secao = $equip['secao'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = $_POST['nome'];
    $patrimonio = $_POST['patrimonio'];
    $secao = $_POST['secao'];

    if ($id > 0) {
        $stmt = $conn->prepare("UPDATE equipamentos SET nome=?, ip=?, secao=? WHERE id=?");
        $stmt->bind_param("sssi", $nome, $patrimonio, $secao, $id);
    } else {
        $stmt = $conn->prepare("INSERT INTO equipamentos (nome, ip, secao) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $nome, $patrimonio, $secao);
    }

    if ($stmt->execute()) {
        header("Location: equipamentos.php");
        exit;
    }
}

require 'header.php';

?>
</head>
<body>
    <h2><?= $id > 0 ? 'Editar' : 'Novo' ?> Equipamento</h2>
    <form method="post">
        <label>Nome:</label><br>
        <input type="text" name="nome" value="<?= htmlspecialchars($nome) ?>" required><br><br>

        <label>Número de Patrimônio:</label><br>
        <input type="text" name="patrimonio" value="<?= htmlspecialchars($patrimonio) ?>"><br><br>

        <label>Seção:</label><br>
        <input type="text" name="secao" value="<?= htmlspecialchars($secao) ?>"><br><br>

        <button type="submit">Salvar</button>
    </form>
    <br>
    <a href="equipamentos.php">⬅ Voltar</a>
</body>
</html>
