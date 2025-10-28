<?php
include 'includes/auth.php';
include 'includes/db.php';
include 'includes/funcoes_chamado.php';

// Filtros
$filtro_status = isset($_GET['status']) ? $_GET['status'] : '';
$filtro_prioridade = isset($_GET['prioridade']) ? $_GET['prioridade'] : '';
$filtro_search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filtro_tecnico = isset($_GET['tecnico']) ? $_GET['tecnico'] : '';

// Preparar filtros para a função
$filtros = [];
if ($filtro_status) $filtros['status'] = $filtro_status;
if ($filtro_prioridade) $filtros['prioridade'] = $filtro_prioridade;
if ($filtro_search) $filtros['search'] = $filtro_search;
if ($filtro_tecnico) $filtros['tecnico'] = $filtro_tecnico;

// Buscar TODOS os chamados (sem filtrar por usuário)
$result = buscarChamadosComFiltro($conn, $filtros);

if (!$result) {
    die("Erro ao buscar chamados");
}

// Buscar estatísticas - incluir fechados apenas se estiver filtrando por eles
$incluir_fechados = ($filtro_status === 'fechado');
$stats = buscarEstatisticasChamados($conn, $incluir_fechados);

if (!$stats) {
    die("Erro ao buscar estatísticas");
}

// Buscar técnicos para filtro
$tecnicos_filtro = [];
if ($_SESSION['usuario_tipo'] === 'admin' || $_SESSION['usuario_tipo'] === 'tecnico') {
    $tecnicos_filtro = buscarTecnicos($conn);
}

require 'header.php';

?>
</head>
<body class="bg-light">
    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="mb-0">📋 Todos os Chamados</h2>
            <a href="index.php" class="btn btn-outline-secondary">← Voltar ao Menu</a>
        </div>

        <!-- Aviso sobre chamados fechados ocultos -->
        <?php if (empty($filtro_status)): ?>
        <div class="alert alert-info alert-dismissible fade show mb-4" role="alert">
            <strong>💡 Chamados fechados estão ocultos</strong> - Use o filtro de status para visualizá-los
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- Estatísticas -->
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="card stats-card border-0 shadow-sm">
                    <div class="card-body text-center">
                        <h5 class="card-title text-primary"><?= $stats['total'] - $stats['fechados']?></h5>
                        <p class="card-text text-muted">Total<?= empty($filtro_status) ? ' (Ativos)' : '' ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card stats-card border-0 shadow-sm">
                    <div class="card-body text-center">
                        <h5 class="card-title text-danger"><?= $stats['abertos'] ?></h5>
                        <p class="card-text text-muted">Abertos</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card stats-card border-0 shadow-sm">
                    <div class="card-body text-center">
                        <h5 class="card-title text-warning"><?= $stats['em_andamento'] ?></h5>
                        <p class="card-text text-muted">Em Andamento</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card stats-card border-0 shadow-sm">
                    <div class="card-body text-center">
                        <h5 class="card-title text-success"><?= $stats['fechados'] ?></h5>
                        <p class="card-text text-muted">Fechados</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filtros -->
        <div class="filter-section">
            <h5 class="mb-3">🔍 Filtros</h5>
            <form method="get" class="row g-3">
                <div class="col-md-2">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="">Todos (ativos)</option>
                        <option value="aberto" <?= $filtro_status == 'aberto' ? 'selected' : '' ?>>Aberto</option>
                        <option value="em_andamento" <?= $filtro_status == 'em_andamento' ? 'selected' : '' ?>>Em Andamento</option>
                        <option value="fechado" <?= $filtro_status == 'fechado' ? 'selected' : '' ?>>Fechado</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Prioridade</label>
                    <select name="prioridade" class="form-select">
                        <option value="">Todas</option>
                        <option value="alta" <?= $filtro_prioridade == 'alta' ? 'selected' : '' ?>>Alta</option>
                        <option value="media" <?= $filtro_prioridade == 'media' ? 'selected' : '' ?>>Média</option>
                        <option value="baixa" <?= $filtro_prioridade == 'baixa' ? 'selected' : '' ?>>Baixa</option>
                    </select>
                </div>
                <?php if ($_SESSION['usuario_tipo'] === 'admin' || $_SESSION['usuario_tipo'] === 'tecnico'): ?>
                <div class="col-md-2">
                    <label class="form-label">Técnico</label>
                    <select name="tecnico" class="form-select">
                        <option value="">Todos</option>
                        <?php foreach ($tecnicos_filtro as $tecnico): ?>
                            <option value="<?= $tecnico['id'] ?>" <?= $filtro_tecnico == $tecnico['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($tecnico['nome_guerra']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                <div class="col-md-4">
                    <label class="form-label">Buscar</label>
                    <input type="text" name="search" class="form-control" placeholder="Título, descrição ou usuário..." value="<?= htmlspecialchars($filtro_search) ?>">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">Filtrar</button>
                </div>
            </form>
            <?php if ($filtro_status || $filtro_prioridade || $filtro_search || $filtro_tecnico): ?>
                <div class="mt-3">
                    <a href="visualizar_chamados.php" class="btn btn-sm btn-outline-secondary">Limpar Filtros</a>
                </div>
            <?php endif; ?>
        </div>

        <!-- Tabela de Chamados -->
        <div class="card border-0 shadow">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-dark">
                            <tr>
                                <th>Fila</th>
                                <th>Usuário</th>
                                <th>Prioridade</th>
                                <th>Status</th>
                                <th>Técnico</th>
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
                                            <?php
                                            // Formatar o título: remover o IP se for usuário comum
                                            $titulo_com_ip = htmlspecialchars($row['titulo']);
                                            // Remove tudo após o último " - " (incluindo o IP)
                                            $titulo_sem_ip = preg_replace('/ - [^-]+$/', '', $titulo_com_ip);
                                            $titulo = ($_SESSION['usuario_tipo'] === 'admin' || $_SESSION['usuario_tipo'] === 'tecnico') ? $titulo_com_ip : $titulo_sem_ip;
                                            ?>
                                            <div class="fw-semibold"><?= $titulo ?></div>
                                            <small class="text-muted"><?= substr(strip_tags($row['descricao']), 0, 50) ?>...</small>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?= 
                                                $row['prioridade'] === 'alta' ? 'danger' : 
                                                ($row['prioridade'] === 'media' ? 'warning' : 'success') 
                                            ?> badge-prioridade">
                                                <?= ucfirst($row['prioridade']) ?>
                                            </span>
                                        </td>
                                        <td>
                                             <span class="badge badge-status-<?= $row['status'] ?>">
                                                <?= ucfirst(str_replace('_', ' ', $row['status'])) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if (!empty($row['tecnico_nome'])): ?>
                                                <?= htmlspecialchars(formatarPatente($row['tecnico_posto'])) . ' ' . htmlspecialchars($row['tecnico_nome_guerra']);?>
                                            <?php else: ?>
                                                <span class="text-muted">Não atribuído</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <small><?= date('d/m/Y', strtotime($row['data_abertura'])) ?></small>
                                            <br>
                                            <small class="text-muted"><?= date('H:i', strtotime($row['data_abertura'])) ?></small>
                                        </td>
                                        <td>
                                            <a href="detalhar_chamado.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-outline-primary">👁️ Detalhar</a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center py-4">
                                        <div class="text-muted">
                                            <i class="bi bi-inbox" style="font-size: 3rem;"></i>
                                            <p class="mt-2">Nenhum chamado encontrado.</p>
                                            <?php if ($filtro_status || $filtro_prioridade || $filtro_search): ?>
                                                <small>Tente ajustar os filtros</small>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <a href="index.php" class="btn btn-secondary mt-3">⬅ Voltar ao Menu</a>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>