<?php
include 'includes/auth.php';
include 'includes/db.php';

// Verificar se o usuário tem permissão para acessar relatórios
if ($_SESSION['usuario_tipo'] !== 'admin' && $_SESSION['usuario_tipo'] !== 'tecnico') {
    header('Location: dashboard.php');
    exit();
}

// Definir período padrão (últimos 30 dias)
$data_inicio = date('Y-m-01'); // Primeiro dia do mês atual
$data_fim = date('Y-m-d');     // Data atual

// Processar filtros
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $filtro_tipo = $_POST['filtro_tipo'] ?? 'personalizado';
    $data_inicio = $_POST['data_inicio'] ?? $data_inicio;
    $data_fim = $_POST['data_fim'] ?? $data_fim;
    
    // Ajustar datas baseado no tipo de filtro
    switch ($filtro_tipo) {
        case 'hoje':
            $data_inicio = date('Y-m-d');
            $data_fim = date('Y-m-d');
            break;
        case 'ontem':
            $data_inicio = date('Y-m-d', strtotime('-1 day'));
            $data_fim = date('Y-m-d', strtotime('-1 day'));
            break;
        case 'semana':
            $data_inicio = date('Y-m-d', strtotime('-7 days'));
            $data_fim = date('Y-m-d');
            break;
        case 'mes':
            $data_inicio = date('Y-m-01');
            $data_fim = date('Y-m-d');
            break;
        case 'ano':
            $data_inicio = date('Y-01-01');
            $data_fim = date('Y-m-d');
            break;
    }
}

// Buscar dados dos chamados
$query_chamados = "
    SELECT 
        c.*,
        u.nome as usuario_nome,
        u.nome_guerra,
        u.posto_graduacao,
        t.nome as tecnico_nome
    FROM chamados c
    LEFT JOIN usuarios u ON c.id_usuario_abriu = u.id
    LEFT JOIN usuarios t ON c.id_tecnico_responsavel = t.id
    WHERE DATE(c.data_abertura) BETWEEN ? AND ?
    ORDER BY c.data_abertura DESC
";

$stmt = $conn->prepare($query_chamados);
$stmt->bind_param('ss', $data_inicio, $data_fim);
$stmt->execute();
$result_chamados = $stmt->get_result();
$chamados = $result_chamados->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Estatísticas para os gráficos
$estatisticas = [
    'total_chamados' => 0,
    'por_status' => ['aberto' => 0, 'em_andamento' => 0, 'fechado' => 0],
    'por_prioridade' => ['baixa' => 0, 'media' => 0, 'alta' => 0],
    'por_tipo' => [],
    'por_dia' => [],
    'por_companhia' => []
];

// Mapeamento para traduzir os valores do banco
$mapeamento_tipo = [
    'apoio' => 'Apoio',
    'problema' => 'Problema', 
    'instalacao' => 'Instalação'
];

$mapeamento_companhia = [
    'estado_maior' => 'Estado Maior',
    'ccap' => 'CCAp',
    'cm' => 'CM',
    'cs' => 'CS'
];

// Processar estatísticas
foreach ($chamados as $chamado) {
    $estatisticas['total_chamados']++;
    
    // Por status
    $estatisticas['por_status'][$chamado['status']]++;
    
    // Por prioridade
    $estatisticas['por_prioridade'][$chamado['prioridade']]++;
    
    // Por tipo - usando o campo do banco
    $tipo = $chamado['tipo_solicitacao'] ?? 'outros';
    $tipo_label = $mapeamento_tipo[$tipo] ?? 'Outros';
    $estatisticas['por_tipo'][$tipo_label] = ($estatisticas['por_tipo'][$tipo_label] ?? 0) + 1;
    
    // Por dia
    $data = date('d/m', strtotime($chamado['data_abertura']));
    $estatisticas['por_dia'][$data] = ($estatisticas['por_dia'][$data] ?? 0) + 1;
    
    // Por companhia - usando o campo do banco
    $companhia = $chamado['companhia'] ?? 'outros';
    $companhia_label = $mapeamento_companhia[$companhia] ?? 'Outros';
    $estatisticas['por_companhia'][$companhia_label] = ($estatisticas['por_companhia'][$companhia_label] ?? 0) + 1;
}

// Garantir que todos os tipos e companhias apareçam nos gráficos (mesmo com zero)
$tipos_esperados = ['Apoio', 'Problema', 'Instalação', 'Outros'];
foreach ($tipos_esperados as $tipo) {
    if (!isset($estatisticas['por_tipo'][$tipo])) {
        $estatisticas['por_tipo'][$tipo] = 0;
    }
}

$companhias_esperadas = ['Estado Maior', 'CCAp', 'CM', 'CS', 'Outros'];
foreach ($companhias_esperadas as $companhia) {
    if (!isset($estatisticas['por_companhia'][$companhia])) {
        $estatisticas['por_companhia'][$companhia] = 0;
    }
}

// Ordenar os arrays
ksort($estatisticas['por_tipo']);
ksort($estatisticas['por_companhia']);

if (empty($estatisticas['por_dia'])) {
    $estatisticas['por_dia']['Nenhum'] = 0;
}

// Preparar dados para os gráficos
function prepararDadosGrafico($dados) {
    $labels = [];
    $valores = [];
    
    foreach ($dados as $label => $valor) {
        $labels[] = $label;
        $valores[] = $valor;
    }
    
    return [
        'labels' => $labels,
        'valores' => $valores
    ];
}

$grafico_status = prepararDadosGrafico($estatisticas['por_status']);
$grafico_prioridade = prepararDadosGrafico($estatisticas['por_prioridade']);
$grafico_tipo = prepararDadosGrafico($estatisticas['por_tipo']);
$grafico_dia = prepararDadosGrafico($estatisticas['por_dia']);
$grafico_companhia = prepararDadosGrafico($estatisticas['por_companhia']);
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Relatórios de Chamados</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .card-chart {
            height: 300px;
            margin-bottom: 20px;
        }
        .card-chart .card-body {
            position: relative;
            height: 250px;
        }
        .stat-card {
            border-left: 4px solid #0d6efd;
        }
        .table-responsive {
            max-height: 400px;
        }
        canvas {
            width: 100% !important;
            height: 100% !important;
        }
    </style>
</head>
<body class="bg-light">
<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2>📊 Relatórios de Chamados</h2>
                <a href="dashboard.php" class="btn btn-secondary">⬅ Voltar ao Dashboard</a>
            </div>

            <!-- Filtros -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Filtros</h5>
                </div>
                <div class="card-body">
                    <form method="POST" class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Tipo de Filtro</label>
                            <select name="filtro_tipo" class="form-select" id="filtroTipo">
                                <option value="personalizado" <?= ($_POST['filtro_tipo'] ?? '') === 'personalizado' ? 'selected' : '' ?>>Data Personalizada</option>
                                <option value="hoje" <?= ($_POST['filtro_tipo'] ?? '') === 'hoje' ? 'selected' : '' ?>>Hoje</option>
                                <option value="ontem" <?= ($_POST['filtro_tipo'] ?? '') === 'ontem' ? 'selected' : '' ?>>Ontem</option>
                                <option value="semana" <?= ($_POST['filtro_tipo'] ?? '') === 'semana' ? 'selected' : '' ?>>Últimos 7 Dias</option>
                                <option value="mes" <?= ($_POST['filtro_tipo'] ?? '') === 'mes' ? 'selected' : '' ?>>Este Mês</option>
                                <option value="ano" <?= ($_POST['filtro_tipo'] ?? '') === 'ano' ? 'selected' : '' ?>>Este Ano</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Data Início</label>
                            <input type="date" name="data_inicio" class="form-control" value="<?= $data_inicio ?>" id="dataInicio">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Data Fim</label>
                            <input type="date" name="data_fim" class="form-control" value="<?= $data_fim ?>" id="dataFim">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">&nbsp;</label>
                            <button type="submit" class="btn btn-primary w-100">Filtrar</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Cards de Estatísticas -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card stat-card">
                        <div class="card-body">
                            <h5 class="card-title">Total de Chamados</h5>
                            <h2 class="text-primary"><?= $estatisticas['total_chamados'] ?></h2>
                            <small class="text-muted">Período: <?= date('d/m/Y', strtotime($data_inicio)) ?> - <?= date('d/m/Y', strtotime($data_fim)) ?></small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stat-card">
                        <div class="card-body">
                            <h5 class="card-title">Abertos</h5>
                            <h2 class="text-warning"><?= $estatisticas['por_status']['aberto'] ?></h2>
                            <small class="text-muted"><?= $estatisticas['total_chamados'] > 0 ? round(($estatisticas['por_status']['aberto'] / $estatisticas['total_chamados']) * 100, 1) : 0 ?>% do total</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stat-card">
                        <div class="card-body">
                            <h5 class="card-title">Em Andamento</h5>
                            <h2 class="text-info"><?= $estatisticas['por_status']['em_andamento'] ?></h2>
                            <small class="text-muted"><?= $estatisticas['total_chamados'] > 0 ? round(($estatisticas['por_status']['em_andamento'] / $estatisticas['total_chamados']) * 100, 1) : 0 ?>% do total</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stat-card">
                        <div class="card-body">
                            <h5 class="card-title">Fechados</h5>
                            <h2 class="text-success"><?= $estatisticas['por_status']['fechado'] ?></h2>
                            <small class="text-muted"><?= $estatisticas['total_chamados'] > 0 ? round(($estatisticas['por_status']['fechado'] / $estatisticas['total_chamados']) * 100, 1) : 0 ?>% do total</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Gráficos -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card card-chart">
                        <div class="card-header">
                            <h6 class="mb-0">Chamados por Status</h6>
                        </div>
                        <div class="card-body">
                            <canvas id="chartStatus"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card card-chart">
                        <div class="card-header">
                            <h6 class="mb-0">Chamados por Prioridade</h6>
                        </div>
                        <div class="card-body">
                            <canvas id="chartPrioridade"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card card-chart">
                        <div class="card-header">
                            <h6 class="mb-0">Chamados por Tipo</h6>
                        </div>
                        <div class="card-body">
                            <canvas id="chartTipo"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card card-chart">
                        <div class="card-header">
                            <h6 class="mb-0">Chamados por Companhia</h6>
                        </div>
                        <div class="card-body">
                            <canvas id="chartCompanhia"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-12">
                    <div class="card card-chart">
                        <div class="card-header">
                            <h6 class="mb-0">Chamados por Dia (Evolução)</h6>
                        </div>
                        <div class="card-body">
                            <canvas id="chartDia"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabela de Chamados -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Chamados do Período (<?= count($chamados) ?> encontrados)</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Título</th>
                                    <th>Tipo</th>
                                    <th>Companhia</th>
                                    <th>Status</th>
                                    <th>Prioridade</th>
                                    <th>Data Abertura</th>
                                    <th>Usuário</th>
                                    <th>Técnico</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($chamados as $chamado): ?>
                                <tr>
                                    <td><?= $chamado['id'] ?></td>
                                    <td><?= htmlspecialchars($chamado['titulo']) ?></td>
                                    <td>
                                        <span class="badge bg-info">
                                            <?= $mapeamento_tipo[$chamado['tipo_solicitacao']] ?? 'Não definido' ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary">
                                            <?= $mapeamento_companhia[$chamado['companhia']] ?? 'Não definida' ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?= 
                                            $chamado['status'] === 'aberto' ? 'warning' : 
                                            ($chamado['status'] === 'em_andamento' ? 'info' : 'success')
                                        ?>">
                                            <?= ucfirst($chamado['status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?= 
                                            $chamado['prioridade'] === 'alta' ? 'danger' : 
                                            ($chamado['prioridade'] === 'media' ? 'warning' : 'secondary')
                                        ?>">
                                            <?= ucfirst($chamado['prioridade']) ?>
                                        </span>
                                    </td>
                                    <td><?= date('d/m/Y H:i', strtotime($chamado['data_abertura'])) ?></td>
                                    <td><?= htmlspecialchars($chamado['nome_guerra']) ?></td>
                                    <td><?= $chamado['tecnico_nome'] ? htmlspecialchars($chamado['tecnico_nome']) : 'Não atribuído' ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($chamados)): ?>
                                <tr>
                                    <td colspan="9" class="text-center">Nenhum chamado encontrado para o período selecionado</td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Dados dos gráficos
const dadosGraficos = {
    status: {
        labels: <?= json_encode($grafico_status['labels']) ?>,
        data: <?= json_encode($grafico_status['valores']) ?>
    },
    prioridade: {
        labels: <?= json_encode($grafico_prioridade['labels']) ?>,
        data: <?= json_encode($grafico_prioridade['valores']) ?>
    },
    tipo: {
        labels: <?= json_encode($grafico_tipo['labels']) ?>,
        data: <?= json_encode($grafico_tipo['valores']) ?>
    },
    dia: {
        labels: <?= json_encode($grafico_dia['labels']) ?>,
        data: <?= json_encode($grafico_dia['valores']) ?>
    },
    companhia: {
        labels: <?= json_encode($grafico_companhia['labels']) ?>,
        data: <?= json_encode($grafico_companhia['valores']) ?>
    }
};

// Configuração dos gráficos
document.addEventListener('DOMContentLoaded', function() {
    console.log('Iniciando gráficos...');
    console.log('Dados disponíveis:', dadosGraficos);

    const cores = {
        status: ['#ffc107', '#0dcaf0', '#198754'],
        prioridade: ['#6c757d', '#ffc107', '#dc3545'],
        tipo: ['#0d6efd', '#fd7e14', '#20c997', '#6f42c1'],
        companhia: ['#6610f2', '#198754', '#fd7e14', '#20c997', '#6c757d']
    };

    // Gráfico de Status
    if (document.getElementById('chartStatus')) {
        new Chart(document.getElementById('chartStatus'), {
            type: 'doughnut',
            data: {
                labels: dadosGraficos.status.labels,
                datasets: [{
                    data: dadosGraficos.status.data,
                    backgroundColor: cores.status
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    }

    // Gráfico de Prioridade
    if (document.getElementById('chartPrioridade')) {
        new Chart(document.getElementById('chartPrioridade'), {
            type: 'pie',
            data: {
                labels: dadosGraficos.prioridade.labels,
                datasets: [{
                    data: dadosGraficos.prioridade.data,
                    backgroundColor: cores.prioridade
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    }

    // Gráfico de Tipo
    if (document.getElementById('chartTipo')) {
        new Chart(document.getElementById('chartTipo'), {
            type: 'bar',
            data: {
                labels: dadosGraficos.tipo.labels,
                datasets: [{
                    label: 'Quantidade',
                    data: dadosGraficos.tipo.data,
                    backgroundColor: cores.tipo
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });
    }

    // Gráfico de Companhia
    if (document.getElementById('chartCompanhia')) {
        new Chart(document.getElementById('chartCompanhia'), {
            type: 'bar',
            data: {
                labels: dadosGraficos.companhia.labels,
                datasets: [{
                    label: 'Quantidade',
                    data: dadosGraficos.companhia.data,
                    backgroundColor: cores.companhia
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });
    }

    // Gráfico de Evolução por Dia
    if (document.getElementById('chartDia')) {
        new Chart(document.getElementById('chartDia'), {
            type: 'line',
            data: {
                labels: dadosGraficos.dia.labels,
                datasets: [{
                    label: 'Chamados por Dia',
                    data: dadosGraficos.dia.data,
                    borderColor: '#0d6efd',
                    backgroundColor: 'rgba(13, 110, 253, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });
    }

    // Controle dos campos de data
    const filtroTipo = document.getElementById('filtroTipo');
    const dataInicio = document.getElementById('dataInicio');
    const dataFim = document.getElementById('dataFim');

    function atualizarCamposData() {
        if (filtroTipo.value === 'personalizado') {
            dataInicio.disabled = false;
            dataFim.disabled = false;
        } else {
            dataInicio.disabled = true;
            dataFim.disabled = true;
        }
    }

    filtroTipo.addEventListener('change', atualizarCamposData);
    atualizarCamposData();
});

window.addEventListener('resize', function() {
    Chart.helpers.each(Chart.instances, function(instance) {
        instance.resize();
    });
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>