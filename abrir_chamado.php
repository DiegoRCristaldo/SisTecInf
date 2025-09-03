<?php
include 'includes/auth.php';
include 'includes/db.php';
include 'includes/funcoes_chamado.php';

$msg = '';

// Pegando nome do usuário logado
$nome_usuario = formatarPatente($_SESSION['usuario_posto_graduacao']) . ' ' . $_SESSION['usuario_nome_guerra'] ?? 'Usuário';

// Pegando IP do cliente
function getUserIP() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
    } else {
        return $_SERVER['REMOTE_ADDR'];
    }
}

// Array com todas as categorias organizadas por tipo de solicitação
$categorias = [
    'apoio' => [
        'Login de acesso a internet',
        'Infraestrutura de rede',
        'Solicitar VPN',
        'Montagem de equipamento de som',
        'Videoconferência',
        'Abrir conta no SPED',
        'Solicitar de material',
        'Outros'
    ],
    'problema' => [
        'Sem acesso a internet/intranet',
        'Problemas com VPN',
        'Máquina fazendo barulho',
        'Tela do computador sem vídeo',
        'Máquina desligando',
        'Impressora não imprime',
        'Bateria rádio viciada',
        'Outros'
    ],
    'instalacao' => [
        'Instalar de Antivírus',
        'Instalar de Siscofis',
        'Instalar de Java (SIAFI)',
        'Instalar de Token',
        'Instalar de Ponto de rede',
        'Outros'
    ]
];

// Array com todas as seções organizadas por companhia
$secoes = [
    'estado_maior' => [
        'Comandante do Batalhão',
        'Sub-Comandante do Batalhão', 
        '1ªSeção',
        '2ªSeção',
        '3ªSeção',
        '4ªSeção',
        'Relações Públicas'
    ],
    'ccap' => [
        'Comandante CCAp',
        'Sargenteação CCAp',
        'Subtenência CCAp'
    ],
    'cm' => [
        'Comandante CM',
        'Sargenteação CM', 
        'Subtenência CM'
    ],
    'cs' => [
        'Comandante CS',
        'Sargenteação CS',
        'Subtenência CS'
    ]
];

$ip_usuario = getUserIP();
$titulo_padrao = $nome_usuario . ' - ' . $ip_usuario;

// Verifica se há mensagem de sucesso
if (isset($_GET['msg']) && $_GET['msg'] === 'sucesso') {
    $msg = "Chamado aberto com sucesso!";
} elseif (isset($_GET['msg']) && $_GET['msg'] === 'erro') {
    $msg = "Erro ao abrir chamado. Tente novamente.";
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Abrir Chamado</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container mt-5">
    <h2 class="mb-4">Abrir Chamado</h2>

    <?php if ($msg): ?>
        <div class="alert alert-<?= strpos($msg, 'sucesso') !== false ? 'success' : 'danger' ?>"><?= $msg ?></div>
    <?php endif; ?>

    <form method="POST" action="processa_chamado.php" enctype="multipart/form-data">
        <div class="mb-3">
            <label class="form-label">Título *</label>
            <input type="text" name="titulo" class="form-control" value="<?= htmlspecialchars($titulo_padrao) ?>" required readonly>
        </div>

        <div class="mb-3">
            <label class="form-label">Tipo de Solicitação *</label>
            <select name="tipo_solicitacao" id="tipo_solicitacao" class="form-select" required>
                <option value="">Selecione...</option>
                <option value="apoio">Solicitar Apoio</option>
                <option value="problema">Relatar Problema</option>
                <option value="instalacao">Solicitar Instalação</option>
            </select>
        </div>

        <!-- Container dinâmico para as categorias -->
        <div id="container-categorias">
            <!-- As categorias serão carregadas aqui via JavaScript -->
        </div>

        <div class="mb-3">
            <label class="form-label">Descrição *</label>
            <textarea name="descricao" class="form-control" rows="4" required placeholder="Descreva detalhadamente o problema ou solicitação"></textarea>
        </div>

        <?php if ($_SESSION['usuario_tipo'] === 'admin' || $_SESSION['usuario_tipo'] === 'tecnico'): ?>
        <div class="mb-3">
            <label class="form-label">Prioridade</label>
            <select name="prioridade" class="form-select">
                <option value="baixa">Baixa</option>
                <option value="media">Média</option>
                <option value="alta">Alta</option>
            </select>
        </div>

        <?php endif; ?>
        
        <div class="mb-3">
            <label class="form-label">Companhia *</label>
            <select name="companhia" id="companhia" class="form-select" required>
                <option value="">Selecione...</option>
                <option value="estado_maior">Estado Maior</option>
                <option value="ccap">Companhia de Comando e Apoio</option>
                <option value="cm">Companhia de Manutenção</option>
                <option value="cs">Companhia de Suprimento</option>
            </select>
        </div>
        
        <!-- Container dinâmico para as seções -->
        <div id="container-secoes">
            <!-- As seções serão carregadas aqui via JavaScript -->
        </div>

        <div class="mb-3">
            <label class="form-label">Anexar arquivo</label>
            <input type="file" name="anexar_arquivo[]" class="form-control" multiple>
            <small class="text-muted">Você pode selecionar múltiplos arquivos (opcional)</small>
        </div>

        <button type="submit" class="btn btn-success">📝 Abrir Chamado</button>
        <a href="dashboard.php" class="btn btn-secondary">⬅ Voltar ao Dashboard</a>
    </form>
</div>

<script>
// Dados dinâmicos
const dadosDinamicos = {
    categorias: <?= json_encode($categorias) ?>,
    secoes: <?= json_encode($secoes) ?>
};

// Gerenciador de elementos dinâmicos
const DynamicFormManager = {
    init() {
        this.setupEventListeners();
    },
    
    setupEventListeners() {
        document.getElementById('tipo_solicitacao').addEventListener('change', (e) => {
            this.carregarElemento('categorias', e.target.value, 'Categoria de');
        });
        
        document.getElementById('companhia').addEventListener('change', (e) => {
            this.carregarElemento('secoes', e.target.value, 'Seção');
        });
    },
    
    carregarElemento(tipo, valor, labelText) {
        const container = document.getElementById(`container-${tipo}`);
        container.innerHTML = '';
        
        if (valor && dadosDinamicos[tipo][valor]) {
            const options = dadosDinamicos[tipo][valor].map(item => 
                `<option value="${item}">${item}</option>`
            ).join('');
            
            const label = tipo === 'categorias' 
                ? `${labelText} ${valor.charAt(0).toUpperCase() + valor.slice(1)}`
                : labelText;
                
            container.innerHTML = `
                <div class="mb-3">
                    <label class="form-label">${label}</label>
                    <select name="${tipo === 'categorias' ? 'categoria' : 'secao'}" 
                            class="form-select" ${tipo === 'categorias' ? 'required' : ''}>
                        <option value="">Selecione...</option>
                        ${options}
                    </select>
                </div>
            `;
        }
    }
};

// Inicializar quando o DOM estiver pronto
document.addEventListener('DOMContentLoaded', () => {
    DynamicFormManager.init();
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>