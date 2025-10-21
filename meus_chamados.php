<?php
include 'includes/auth.php';
include 'includes/db.php';
include 'includes/funcoes_chamado.php';

$filtro_status = isset($_GET['status']) ? $_GET['status'] : '';

// Usar a função centralizada para buscar chamados
$filtros = [];
if ($filtro_status) {
    $filtros['status'] = $filtro_status;
}

$result = buscarChamadosComFiltro($conn, $filtros, $_SESSION['usuario_id'], $_SESSION['usuario_tipo']);

if (!$result) {
    die("Erro ao buscar chamados");
}

// Obter IP atual apenas para admin/tecnico
$ip_atual = '';
if ($_SESSION['usuario_tipo'] === 'admin' || $_SESSION['usuario_tipo'] === 'tecnico') {
    $ip_atual = getUserIP();
}

require 'header.php';
?>
</head>
<body class="bg-light">
    <div class="container py-4">
        <h2 class="mb-4">📋 Meus Chamados</h2>

        <form method="get" class="row g-3 align-items-center mb-4">
            <div class="col-auto">
                <label for="status" class="col-form-label fw-semibold">Filtrar por status:</label>
            </div>
            <div class="col-auto">
                <select name="status" id="status" class="form-select">
                    <option value="">Todos</option>
                    <option value="aberto" <?= $filtro_status == 'aberto' ? 'selected' : '' ?>>Aberto</option>
                    <option value="em_andamento" <?= $filtro_status == 'em_andamento' ? 'selected' : '' ?>>Em Andamento</option>
                    <option value="fechado" <?= $filtro_status == 'fechado' ? 'selected' : '' ?>>Fechado</option>
                </select>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-primary">Filtrar</button>
            </div>
            <div class="col-auto">
                <a href="meus_chamados.php" class="btn btn-outline-secondary">Limpar</a>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-striped table-hover align-middle">
                <thead class="table-dark">
                    <tr>
                        <th>Fila</th>
                        <th>Usuário</th>
                        <th>Prioridade</th>
                        <th>Status</th>
                        <th>Data Abertura</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result->num_rows > 0): ?>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <?= filaPrioridadeAtendimento($conn, $row['prioridade'], $row['data_abertura'], $row['id']) ?>
                                </td>
                                <td>
                                    <?= formatarPatente($row['usuario_posto']) ?> <?= htmlspecialchars($row['usuario_nome_guerra'] ?? '')?>
                                    <?php if (!empty($row['usuario_posto'])): ?>
                                        <br><small class="text-muted"><?= htmlspecialchars($row['usuario_nome'])?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-<?= 
                                        $row['prioridade'] === 'alta' ? 'danger' : 
                                        ($row['prioridade'] === 'media' ? 'warning' : 'success') 
                                    ?>">
                                        <?= ucfirst($row['prioridade']) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge badge-status-<?= $row['status'] ?>">
                                        <?= ucfirst(str_replace('_', ' ', $row['status'])) ?>
                                    </span>
                                </td>
                                <td><?= date('d/m/Y H:i', strtotime($row['data_abertura'])) ?></td>
                                <td>
                                    <a href="detalhar_chamado.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-outline-primary">👁️ Ver</a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="<?= ($_SESSION['usuario_tipo'] === 'admin' || $_SESSION['usuario_tipo'] === 'tecnico') ? '8' : '7' ?>" class="text-center">Nenhum chamado encontrado.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <a href="index.php" class="btn btn-secondary mt-3">⬅ Voltar ao Menu</a>
    </div>
</body>
</html>