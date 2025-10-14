<?php
include 'includes/auth.php';
include 'includes/db.php';

if ($_SESSION['usuario_tipo'] === 'usuario') {
    die("Acesso negado!");
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$nome = $patrimonio = $setor = "";

if ($id > 0) {
    $stmt = $conn->prepare("SELECT * FROM equipamentos WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $equip = $stmt->get_result()->fetch_assoc();
    if ($equip) {
        $nome = $equip['nome'];
        $patrimonio = $equip['numero_patrimonio'];
        $setor = $equip['setor'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = $_POST['nome'];
    $patrimonio = $_POST['patrimonio'];
    $setor = $_POST['setor'];

    if ($id > 0) {
        $stmt = $conn->prepare("UPDATE equipamentos SET nome=?, numero_patrimonio=?, setor=? WHERE id=?");
        $stmt->bind_param("sssi", $nome, $patrimonio, $setor, $id);
    } else {
        $stmt = $conn->prepare("INSERT INTO equipamentos (nome, numero_patrimonio, setor) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $nome, $patrimonio, $setor);
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

        <label>Setor:</label><br>
        <input type="text" name="setor" value="<?= htmlspecialchars($setor) ?>"><br><br>

        <button type="submit">Salvar</button>
    </form>
    <br>
    <a href="equipamentos.php">⬅ Voltar</a>
</body>
</html>
