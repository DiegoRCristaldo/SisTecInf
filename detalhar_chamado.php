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
$pode_atualizar = false;

// Verificar se o usuário pode atualizar (admin OU técnico responsável por este chamado)
if ($_SESSION['usuario_tipo'] === 'admin') {
    $pode_atualizar = true;
} elseif ($_SESSION['usuario_tipo'] === 'tecnico') {
    // Verificar se é o técnico responsável por este chamado
    if ($chamado['id_tecnico_responsavel'] == $_SESSION['usuario_id']) {
        $pode_atualizar = true;
    }
}

if ($pode_atualizar && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['atualizar_status'])) {
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
                    <h2 class="h5 mb-0">
                        <?php
                        // Formatar o título: remover o IP se for usuário comum
                        $titulo = htmlspecialchars($chamado['titulo']);
                        if ($_SESSION['usuario_tipo'] === 'usuario') {
                            // Remove tudo após o último " - " (incluindo o IP)
                            $titulo = preg_replace('/ - [^-]+$/', '', $titulo);
                        }
                        echo $titulo;
                        ?>
                    </h2>
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
                        <h5 class="mb-0">🕒 Histórico e Comentários</h5>
                    </div>
                    <div class="card-body">
                        <?php 
                        // Buscar histórico
                        $historico_result = buscarHistoricoChamado($conn, $chamado_id);
                        // Buscar comentários
                        $comentarios_result = buscarComentariosChamado($conn, $chamado_id);
                        
                        // Combinar histórico e comentários em uma única timeline
                        $timeline = [];
                        
                        // Adicionar histórico
                        if ($historico_result && $historico_result->num_rows > 0) {
                            while ($item = $historico_result->fetch_assoc()) {
                                $timeline[] = [
                                    'tipo' => 'historico',
                                    'data' => $item['data_acao'],
                                    'acao' => $item['acao'],
                                    'observacao' => $item['observacao'],
                                    'posto_graduacao' => $item['tecnico_posto_graduacao'] ?? null,
                                    'nome_guerra' => $item['tecnico_nome_guerra'] ?? null,
                                    'tecnico_nome' => $item['tecnico_nome'] ?? null // Mantendo para compatibilidade
                                ];
                            }
                        }
                        
                        // Adicionar comentários
                        if ($comentarios_result && $comentarios_result->num_rows > 0) {
                            while ($item = $comentarios_result->fetch_assoc()) {
                                $timeline[] = [
                                    'tipo' => 'comentario',
                                    'data' => $item['data'],
                                    'usuario_nome' => $item['nome'],
                                    'usuario_guerra' => $item['nome_guerra'],
                                    'usuario_posto' => $item['posto_graduacao'],
                                    'comentario' => $item['comentario']
                                ];
                            }
                        }
                        
                        // Ordenar por data (mais recente primeiro)
                        usort($timeline, function($a, $b) {
                            return strtotime($b['data']) - strtotime($a['data']);
                        });
                        ?>
                        
                        <?php if (!empty($timeline)): ?>
                            <div class="timeline">
                                <?php foreach ($timeline as $item): ?>
                                    <div class="timeline-item mb-4">
                                        <div class="d-flex">
                                            <div class="timeline-icon me-3">
                                                <?php if ($item['tipo'] === 'comentario'): ?>
                                                    <span class="badge bg-info">💬</span>
                                                <?php else: ?>
                                                    <span class="badge bg-primary">📝</span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="timeline-content flex-grow-1">
                                                <div class="d-flex justify-content-between align-items-start">
                                                    <div>
                                                        <?php if ($item['tipo'] === 'comentario'): ?>
                                                        <strong>
                                                            <?= formatarPatente($item['usuario_posto']) ?> <?= htmlspecialchars($item['usuario_guerra']) ?>
                                                        </strong>
                                                        <span class="text-muted">comentou:</span>
                                                    <?php else: ?>
                                                        <strong><?= htmlspecialchars($item['acao']) ?></strong>
                                                        <?php if (!empty($item['posto_graduacao'])): ?>
                                                            <span class="text-muted">por <?= formatarPatente($item['posto_graduacao']) ?> <?= htmlspecialchars($item['nome_guerra']) ?></span>
                                                        <?php elseif (!empty($item['tecnico_nome'])): ?>
                                                            <!-- Fallback para compatibilidade -->
                                                            <span class="text-muted">por <?= htmlspecialchars($item['tecnico_nome']) ?></span>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                    </div>
                                                    <small class="text-muted">
                                                        <?= date('d/m/Y H:i', strtotime($item['data'])) ?>
                                                    </small>
                                                </div>
                                                
                                                <?php if ($item['tipo'] === 'comentario'): ?>
                                                    <div class="comentario-box mt-2 p-3 bg-light rounded">
                                                        <?= nl2br(htmlspecialchars($item['comentario'])) ?>
                                                    </div>
                                                <?php elseif (!empty($item['observacao'])): ?>
                                                    <p class="mb-1 mt-1"><?= htmlspecialchars($item['observacao']) ?></p>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="text-muted">Nenhum histórico ou comentário registrado.</p>
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
                <?php 
                // Verificar se o usuário pode editar (admin OU técnico responsável)
                $pode_editar = false;
                if ($_SESSION['usuario_tipo'] === 'admin') {
                    $pode_editar = true;
                } elseif ($_SESSION['usuario_tipo'] === 'tecnico' && $chamado['id_tecnico_responsavel'] == $_SESSION['usuario_id']) {
                    $pode_editar = true;
                }

                if ($pode_editar): 
                ?>
                <div class="card">
                    <div class="card-header bg-dark text-white">
                        <h5 class="mb-0">⚡ Editar Chamado</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <?php if ($_SESSION['usuario_tipo'] === 'tecnico' && $chamado['id_tecnico_responsavel'] == $_SESSION['usuario_id']): ?>
                            <div class="alert alert-info mb-3">
                                <small>💡 <strong>Você é o técnico responsável por este chamado.</strong> Atualize o status conforme o andamento do atendimento.</small>
                            </div>
                            <?php endif; ?>
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
                            <?php else: ?>
                            <!-- Para técnicos não-admin, mostrar apenas informações do técnico responsável -->
                            <div class="mb-3">
                                <label class="form-label">Técnico Responsável</label>
                                <div class="form-control bg-light">
                                    <?php if ($chamado['tecnico_nome']): ?>
                                        <?= htmlspecialchars(formatarPatente($chamado['tecnico_posto']) . ' ' . $chamado['tecnico_nome_guerra']) ?> 
                                        (<?= htmlspecialchars($chamado['tecnico_nome']) ?>)
                                    <?php else: ?>
                                        <span class="text-danger">Não atribuído</span>
                                    <?php endif; ?>
                                </div>
                                <input type="hidden" name="tecnico_responsavel" value="<?= $chamado['id_tecnico_responsavel'] ?>">
                            </div>
                            <?php endif; ?>
                            <div class="mb-3">
                                <label class="form-label">Comentário (Resposta ao usuário)</label>
                                <textarea name="comentario" class="form-control" rows="4" placeholder="Digite aqui sua resposta ou comentário para o usuário..."></textarea>
                                <small class="text-muted">Este comentário ficará visível no histórico do chamado.</small>
                            </div>
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