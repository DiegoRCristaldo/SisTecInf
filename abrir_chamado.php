<?php
include 'includes/auth.php';
include 'includes/db.php';
include 'includes/funcoes_chamado.php';

$msg = '';

// Pegando nome do usuário logado
$nome_usuario = formatarPatente($_SESSION['usuario_posto_graduacao']) . ' ' . $_SESSION['usuario_nome_guerra'] ?? 'Usuário';

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
        'Instalar Antivírus',
        'Instalar Siscofis',
        'Instalar Java/Tela Preta (SIAFI)',
        'Instalar Token (AC Defesa)',
        'Instalar Ponto de rede',
        'Outros'
    ]
];

// Array com todas as seções organizadas por companhia
$secoes = [
    'estado_maior' => [
        'Adjunto de Comando',
        'Chefe da 1ªSeção',
        'Chefe da 2ªSeção',
        'Chefe da 3ªSeção',
        'Chefe da 4ªSeção',
        'Chefe da Relações Públicas',
        'Chefe do COL',
        'Comandante do Batalhão',
        'Fiscal Adm',
        'Sub-Comandante do Batalhão'
    ],
    'ccap' => [
        '1ªSeção',
        '2ªSeção',
        '3ªSeção',
        '4ªSeção',
        'Almoxarifado',
        'Aprovisionamento',
        'Assessoria Juridica',
        'CF CON',
        'COL',
        'Comandante CCAp',
        'Conformidade e Gestão',
        'Fiscalização Administrativa',
        'Furriel CCAp',
        'Pelotão de Comunicações',
        'Relações Públicas',
        'SALC',
        'Sargenteação CCAp',
        'Seção de pagamento',
        'Seção de Saúde',
        'Secretária',
        'STI',
        'Subtenência CCAp',
        'Tesouraria'
    ],
    'cm' => [
        'Capotaria',
        'Centro de Manutenção',
        'Comandante CM',
        'Funilaria',
        'Furriel CM',
        'GCM',
        'Pelotão de Apoio',
        'Pelotão de Apoio CL V',
        'Pelotão de evacuação',
        'Sargenteação CM', 
        'Seção de armamento',
        'SIMB',
        'Subtenência CM'
    ],
    'cs' => [
        'Comandante CS',
        'Furriel CM',
        'Pelotão de Suprimento e Transporte',
        'PCA',
        'Sargenteação CS',
        'Subtenência CS'
    ]
];

$ip_usuario = getUserIP();
if ($_SESSION['usuario_tipo'] === 'admin' || $_SESSION['usuario_tipo'] === 'tecnico') {
    $titulo_padrao = $nome_usuario . ' - ' . $ip_usuario;
} else {
    $titulo_padrao = $nome_usuario;
}

// Verifica se há mensagem de sucesso
if (isset($_GET['msg']) && $_GET['msg'] === 'sucesso') {
    $msg = "Chamado aberto com sucesso!";
} elseif (isset($_GET['msg']) && $_GET['msg'] === 'erro') {
    $msg = "Erro ao abrir chamado. Tente novamente.";
}

require 'header.php';
?>
</head>
<body class="bg-light">
<div class="container mt-5">
    <h2 class="mb-4">📝 Abrir Chamado</h2>

    <?php if ($msg): ?>
        <div class="alert alert-<?= strpos($msg, 'sucesso') !== false ? 'success' : 'danger' ?>"><?= $msg ?></div>
    <?php endif; ?>

    <form method="POST" action="processa_chamado.php" enctype="multipart/form-data">
        <div class="mb-3">
            <label class="form-label">Título *</label>
            <input type="text" name="titulo" class="form-control" value="<?= htmlspecialchars($titulo_padrao) ?>" required readonly>
        </div>

        <div class="mb-3">
            <label class="form-label">Sistema Operacional *</label>
            <div class="d-flex gap-4">
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="sistema_operacional" id="windows" value="Windows" required>
                    <label class="form-check-label" for="windows">Windows</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="sistema_operacional" id="linux" value="Linux" required>
                    <label class="form-check-label" for="linux">Linux</label>
                </div>
            </div>
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
        <div id="container-categorias"></div>

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
        <a href="index.php" class="btn btn-secondary">⬅ Voltar ao Menu</a>
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
    
    // No arquivo abrir_chamado.php, substitua esta parte do JavaScript:

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
                
            // CORREÇÃO: Usar name="secao" em vez de name dinâmico
            const fieldName = tipo === 'categorias' ? 'categoria' : 'secao';
                
            container.innerHTML = `
                <div class="mb-3">
                    <label class="form-label">${label}</label>
                    <select name="${fieldName}" 
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