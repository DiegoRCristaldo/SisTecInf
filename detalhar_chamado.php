<?php
include 'includes/auth.php';
include 'includes/db.php';
include 'includes/funcoes_chamado.php';

// Verificar se o ID do chamado foi passado
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: meus_chamados.php");
    exit;
}

$chamado_id = intval($_GET['id']);

// Buscar dados do chamado usando a função
$chamado = buscarChamadoPorId($conn, $chamado_id);

if (!$chamado) {
    header("Location: meus_chamados.php?msg=chamado_nao_encontrado");
    exit;
}

// Verificar permissão usando a função
if (!verificarPermissaoChamado($chamado, $_SESSION['usuario_id'], $_SESSION['usuario_tipo'])) {
    header("Location: meus_chamados.php?msg=acesso_negado");
    exit;
}

// Buscar lista de técnicos (apenas para admin)
$tecnicos = [];
if ($_SESSION['usuario_tipo'] === 'admin') {
    $tecnicos = buscarTecnicos($conn);
}

// Processar ação de atualização
if (($_SESSION['usuario_tipo'] === 'admin' || $_SESSION['usuario_tipo'] === 'tecnico') && 
    $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['atualizar_status'])) {
    
    if (atualizarChamado($conn, $chamado_id, $_POST, $_SESSION['usuario_id'], $_SESSION['usuario_tipo'])) {
        header("Location: detalhar_chamado.php?id=" . $chamado_id . "&msg=status_atualizado");
        exit;
    } else {
        $msg = "Erro ao atualizar chamado";
    }
}

// Buscar histórico do chamado
$historico_result = buscarHistoricoChamado($conn, $chamado_id);

// Buscar arquivos anexos
$arquivos = buscarArquivosChamado($conn, $chamado_id);

// Mensagens
$msg = '';
if (isset($_GET['msg'])) {
    $mensagens = [
        'status_atualizado' => 'Status atualizado com sucesso!',
        'acesso_negado' => 'Acesso negado a este chamado.',
        'chamado_nao_encontrado' => 'Chamado não encontrado.'
    ];
    $msg = $mensagens[$_GET['msg']] ?? '';
}

require 'header.php';

?>
</head>
<body class="bg-light">
    <div class="container py-4">
        <!-- Cabeçalho do Chamado -->
        <div class="chamado-header">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="h3 mb-1">Chamado #<?= $chamado['id'] ?></h1>
                    <h2 class="h5 mb-0"><?= htmlspecialchars($chamado['titulo']) ?></h2>
                </div>
                <div class="col-md-4 text-md-end">
                    <span class="badge badge-prioridade bg-<?= 
                        $chamado['prioridade'] === 'alta' ? 'danger' : 
                        ($chamado['prioridade'] === 'media' ? 'warning' : 'success') 
                    ?>">
                        <?= ucfirst($chamado['prioridade']) ?>
                    </span>
                    <span class="badge badge-status-<?= $chamado['status'] ?>">
                        <?= ucfirst(str_replace('_', ' ', $chamado['status'])) ?>
                    </span>
                </div>
            </div>
        </div>

        <?php if ($msg): ?>
            <div class="alert alert-info alert-dismissible fade show" role="alert">
                <?= $msg ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="mb-4">
            <a href="index.php" class="btn btn-secondary">← Voltar para Tela Inicial</a>
            <?php if ($_SESSION['usuario_tipo'] === 'admin' || $_SESSION['usuario_tipo'] === 'tecnico'): ?>
            <a href="visualizar_chamados.php" class="btn btn-secondary">← Todos os Chamados</a>
            <?php endif?>
        </div>

        <div class="row">
            <!-- Informações do Chamado -->
            <div class="col-md-8">
                <div class="card mb-4">
                    <div class="card-header bg-dark text-white">
                        <h5 class="mb-0">📋 Descrição do Chamado</h5>
                    </div>
                    <div class="card-body">
                        <div class="descricao-box">
                            <?= nl2br(htmlspecialchars($chamado['descricao'])) ?>
                        </div>
                    </div>
                </div>

                <!-- Histórico do Chamado -->
                <div class="card">
                    <div class="card-header bg-dark text-white">
                        <h5 class="mb-0">🕒 Histórico</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($historico_result && $historico_result->num_rows > 0): ?>
                            <?php while ($historico = $historico_result->fetch_assoc()): ?>
                                <div class="historico-item">
                                    <div class="d-flex justify-content-between">
                                        <strong><?= htmlspecialchars($historico['acao']) ?></strong>
                                        <small class="text-muted">
                                            <?= date('d/m/Y H:i', strtotime($historico['data_acao'])) ?>
                                        </small>
                                    </div>
                                    <?php if (!empty($historico['observacao'])): ?>
                                        <p class="mb-1"><?= htmlspecialchars($historico['observacao']) ?></p>
                                    <?php endif; ?>
                                    <?php if (!empty($historico['tecnico_nome'])): ?>
                                        <small class="text-muted">
                                            Por: <?= htmlspecialchars($historico['tecnico_nome']) ?>
                                        </small>
                                    <?php endif; ?>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <p class="text-muted">Nenhum histórico registrado.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Sidebar com Informações e Formulário -->
            <div class="col-md-4">
                <div class="card mb-3">
                    <div class="card-header bg-dark text-white">
                        <h5 class="mb-0">📋 Informações do Chamado</h5>
                    </div>
                    <div class="card-body">
                        <div class="info-card">
                            <p class="mb-1"><strong>👤 Solicitante:</strong><br>
                                <?= htmlspecialchars(formatarPatente($chamado['posto_graduacao'])) ?></br>
                                <small class="text-muted"><?= htmlspecialchars($chamado['usuario_nome']) ?></small>
                            </p>
                        </div>
                        
                        <div class="info-card">
                            <p class="mb-1"><strong>📅 Data de Abertura:</strong><br>
                                <small><?= date('d/m/Y H:i', strtotime($chamado['data_abertura'])) ?></small>
                            </p>
                        </div>
                        
                        <div class="info-card">
                            <p class="mb-1"><strong>🔧 Técnico Responsável:</strong><br>
                                <?php if ($chamado['tecnico_nome']): ?>
                                    <span class="text-success">
                                        <?= htmlspecialchars(formatarPatente($chamado['tecnico_posto']) . ' ' . $chamado['tecnico_nome_guerra']) ?><br>
                                        <small>(<?= htmlspecialchars($chamado['tecnico_nome']) ?>)</small>
                                    </span>
                                <?php else: ?>
                                    <span class="text-danger">Não atribuído</span>
                                <?php endif; ?>
                            </p>
                        </div>
                        
                        <div class="info-card">
                            <p class="mb-1"><strong>📊 Status Atual:</strong><br>
                                <span class="badge badge-status-<?= $chamado['status'] ?>">
                                    <?= ucfirst(str_replace('_', ' ', $chamado['status'])) ?>
                                </span>
                            </p>
                        </div>
                        
                        <div class="info-card">
                            <p class="mb-1"><strong>🚨 Prioridade Atual:</strong><br>
                                <span class="badge bg-<?= 
                                    $chamado['prioridade'] === 'alta' ? 'danger' : 
                                    ($chamado['prioridade'] === 'media' ? 'warning' : 'success') 
                                ?>">
                                    <?= ucfirst($chamado['prioridade']) ?>
                                </span>
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Formulário de Edição -->
                <?php if ($_SESSION['usuario_tipo'] === 'admin' || $_SESSION['usuario_tipo'] === 'tecnico'): ?>
                <div class="card">
                    <div class="card-header bg-dark text-white">
                        <h5 class="mb-0">⚡ Editar Chamado</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Prioridade *</label>
                                        <select name="prioridade" class="form-select" required>
                                            <option value="baixa" <?= $chamado['prioridade'] === 'baixa' ? 'selected' : '' ?>>Baixa</option>
                                            <option value="media" <?= $chamado['prioridade'] === 'media' ? 'selected' : '' ?>>Média</option>
                                            <option value="alta" <?= $chamado['prioridade'] === 'alta' ? 'selected' : '' ?>>Alta</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Status *</label>
                                        <select name="status" class="form-select" required>
                                            <option value="aberto" <?= $chamado['status'] === 'aberto' ? 'selected' : '' ?>>Aberto</option>
                                            <option value="em_andamento" <?= $chamado['status'] === 'em_andamento' ? 'selected' : '' ?>>Em Andamento</option>
                                            <option value="fechado" <?= $chamado['status'] === 'fechado' ? 'selected' : '' ?>>Fechado</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <?php if ($_SESSION['usuario_tipo'] === 'admin'): ?>
                            <div class="mb-3">
                                <label class="form-label">Técnico Responsável</label>
                                <select name="tecnico_responsavel" class="form-select">
                                    <option value="">Selecione um técnico...</option>
                                    <?php foreach ($tecnicos as $tecnico): ?>
                                        <option value="<?= $tecnico['id'] ?>" 
                                            <?= $chamado['id_tecnico_responsavel'] == $tecnico['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars(formatarPatente($tecnico['posto_graduacao']) . ' ' . $tecnico['nome_guerra']) ?> 
                                            - <?= htmlspecialchars($tecnico['nome']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="text-muted">
                                    Atual: 
                                    <?php if ($chamado['tecnico_nome']): ?>
                                        <?= htmlspecialchars(formatarPatente($chamado['tecnico_posto']) . ' ' . $chamado['tecnico_nome_guerra']) ?> 
                                        (<?= htmlspecialchars($chamado['tecnico_nome']) ?>)
                                    <?php else: ?>
                                        <span class="text-danger">Não atribuído</span>
                                    <?php endif; ?>
                                </small>
                            </div>
                            <?php endif; ?>

                            <div class="d-grid gap-2 d-md-flex">
                                <button type="submit" name="atualizar_status" class="btn btn-salvar">💾 Salvar Alterações</button>
                                <a href="meus_chamados.php" class="btn btn-outline-secondary">📋 Meus Chamados</a>
                            </div>
                        </form>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Arquivos Anexos -->
                <?php if (!empty($arquivos)): ?>
                <div class="card mt-4">
                    <div class="card-header bg-dark text-white">
                        <h5 class="mb-0">📎 Arquivos Anexos</h5>
                    </div>
                    <div class="card-body">
                        <div class="column">
                            <?php foreach ($arquivos as $arquivo): ?>
                                <div class="col-md-12 mb-3">
                                    <div class="card arquivo-card">
                                        <div class="card-body">
                                            <div class="d-flex flex-column align-items-center">
                                                <div class="flex-shrink-0">
                                                    <?php
                                                    $icone = '📄'; // Padrão
                                                    $extensao = strtolower(pathinfo($arquivo['nome'], PATHINFO_EXTENSION));
                                                    
                                                    $icones = [
                                                        'pdf' => '📕',
                                                        'doc' => '📘', 'docx' => '📘',
                                                        'xls' => '📗', 'xlsx' => '📗',
                                                        'jpg' => '🖼️', 'jpeg' => '🖼️', 'png' => '🖼️', 'gif' => '🖼️',
                                                        'zip' => '📦', 'rar' => '📦',
                                                        'txt' => '📄',
                                                    ];
                                                    
                                                    $icone = $icones[$extensao] ?? '📄';
                                                    ?>
                                                    <span style="font-size: 2rem;"><?= $icone ?></span>
                                                </div>
                                                <div class="flex-grow-1 ms-3">
                                                    <h6 class="card-title mb-1" style="font-size: 0.9rem;">
                                                        <?= htmlspecialchars(substr($arquivo['nome'], 0, 20)) ?>
                                                        <?= strlen($arquivo['nome']) > 20 ? '...' : '' ?>
                                                    </h6>
                                                    <p class="card-text mb-0">
                                                        <small class="text-muted">
                                                            <?= formatarTamanhoArquivo($arquivo['tamanho']) ?>
                                                        </small>
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="card-footer bg-transparent">
                                            <div class="d-grid gap-2">
                                                <a href="<?= $arquivo['caminho'] ?>" 
                                                class="btn btn-sm btn-outline-primary" 
                                                target="_blank" 
                                                download="<?= htmlspecialchars($arquivo['nome']) ?>">
                                                📥 Download
                                                </a>
                                                <?php if (strpos($arquivo['tipo'], 'image/') === 0): ?>
                                                <button type="button" 
                                                        class="btn btn-sm btn-outline-info visualizar-imagem"
                                                        data-imagem="<?= $arquivo['caminho'] ?>"
                                                        data-nome="<?= htmlspecialchars($arquivo['nome']) ?>">
                                                    👁️ Visualizar
                                                </button>
                                                <?php elseif ($extensao === 'pdf'): ?>
                                                <a href="<?= $arquivo['caminho'] ?>" 
                                                class="btn btn-sm btn-outline-info"
                                                target="_blank">
                                                📖 Abrir PDF
                                                </a>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Modal para visualização de imagens -->
    <div class="modal fade" id="modalImagem" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title" id="modalImagemLabel">Visualizar Imagem</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <img id="imagemModal" src="" class="img-fluid" alt="Imagem do chamado">
                </div>
                <div class="modal-footer">
                    <a href="#" id="downloadImagem" class="btn btn-primary" download>📥 Download</a>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                </div>
            </div>
        </div>
    </div>

<script>
// Script para o modal de imagens
document.addEventListener('DOMContentLoaded', function() {
    const modal = new bootstrap.Modal(document.getElementById('modalImagem'));
    const modalImg = document.getElementById('imagemModal');
    const modalLabel = document.getElementById('modalImagemLabel');
    const downloadLink = document.getElementById('downloadImagem');
    
    document.querySelectorAll('.visualizar-imagem').forEach(button => {
        button.addEventListener('click', function() {
            const imagemSrc = this.getAttribute('data-imagem');
            const nomeArquivo = this.getAttribute('data-nome');
            
            modalImg.src = imagemSrc;
            modalLabel.textContent = 'Visualizar: ' + nomeArquivo;
            downloadLink.href = imagemSrc;
            downloadLink.download = nomeArquivo;
            
            modal.show();
        });
    });
});
</script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php
// Fechar conexões
if (isset($stmt)) $stmt->close();
if (isset($stmt_historico)) $stmt_historico->close();
$conn->close();
?>