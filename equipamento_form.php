<?php
include 'includes/auth.php';
include 'includes/db.php';

if ($_SESSION['usuario_tipo'] === 'usuario') {
    die("Acesso negado!");
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$nome = $ip = $secao = $mac = "";
$mensagem_erro = "";

// Carregar dados se estiver editando
if ($id > 0) {
    $stmt = $conn->prepare("SELECT * FROM equipamentos WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $equip = $result->fetch_assoc();
        $nome = $equip['nome'];
        $ip = $equip['ip'];
        $secao = $equip['secao'];
        $mac = $equip['mac'];
    } else {
        $mensagem_erro = "Equipamento não encontrado!";
    }
    $stmt->close();
}

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome']);
    $ip = trim($_POST['ip']);
    $secao = trim($_POST['secao']);
    $mac = trim($_POST['mac']);

    // Validações básicas
    if (empty($nome)) {
        $mensagem_erro = "O nome do equipamento é obrigatório!";
    } elseif ($ip && !filter_var($ip, FILTER_VALIDATE_IP)) {
        $mensagem_erro = "Endereço IP inválido!";
    } else {
        try {
            if ($id > 0) {
                // UPDATE com campo MAC
                $stmt = $conn->prepare("UPDATE equipamentos SET nome=?, ip=?, secao=?, mac=? WHERE id=?");
                $stmt->bind_param("ssssi", $nome, $ip, $secao, $mac, $id);
            } else {
                // INSERT com campo MAC
                $stmt = $conn->prepare("INSERT INTO equipamentos (nome, ip, secao, mac) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("ssss", $nome, $ip, $secao, $mac);
            }

            if ($stmt->execute()) {
                header("Location: equipamentos.php?sucesso=" . ($id > 0 ? 'editado' : 'criado'));
                exit;
            } else {
                $mensagem_erro = "Erro ao salvar equipamento: " . $conn->error;
            }
            $stmt->close();
            
        } catch (Exception $e) {
            $mensagem_erro = "Erro: " . $e->getMessage();
        }
    }
}

require 'header.php';
?>
</head>
<body class="bg-light">
    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="mb-0"><?= $id > 0 ? '✏️ Editar' : '➕ Novo' ?> Equipamento</h2>
            <a href="equipamentos.php" class="btn btn-outline-secondary">← Voltar</a>
        </div>

        <?php if ($mensagem_erro): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <strong>❌ Erro:</strong> <?= htmlspecialchars($mensagem_erro) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <div class="card border-0 shadow">
            <div class="card-body p-4">
                <form method="post">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Nome do Equipamento <span class="text-danger">*</span></label>
                            <input type="text" name="nome" class="form-control" 
                                   value="<?= htmlspecialchars($nome) ?>" 
                                   placeholder="Ex: Computador Administrativo, Impressora RH..." 
                                   maxlength="100" required>
                            <div class="form-text">Nome descritivo do equipamento</div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Endereço IP</label>
                            <input type="text" name="ip" class="form-control" 
                                   value="<?= htmlspecialchars($ip) ?>" 
                                   placeholder="Ex: 192.168.1.100"
                                   pattern="^([0-9]{1,3}\.){3}[0-9]{1,3}$">
                            <div class="form-text">Formato: 000.000.000.000</div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Endereço MAC</label>
                            <input type="text" name="mac" class="form-control" 
                                   value="<?= htmlspecialchars($mac) ?>" 
                                   placeholder="Ex: 00:1B:44:11:3A:B7"
                                   pattern="^([0-9A-Fa-f]{2}[:-]){5}([0-9A-Fa-f]{2})$">
                            <div class="form-text">Formato: 00:00:00:00:00:00</div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Seção/Setor</label>
                            <input type="text" name="secao" class="form-control" 
                                   value="<?= htmlspecialchars($secao) ?>" 
                                   placeholder="Ex: Administração, RH, TI..."
                                   maxlength="50">
                            <div class="form-text">Seção responsável pelo equipamento</div>
                        </div>
                    </div>

                    <div class="d-flex gap-2 mt-4">
                        <button class="btn btn-success" type="submit">
                            <i class="bi bi-check-lg"></i> <?= $id > 0 ? 'Atualizar' : 'Salvar' ?> Equipamento
                        </button>
                        <a class="btn btn-secondary" href="equipamentos.php">
                            <i class="bi bi-arrow-left"></i> Cancelar
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>