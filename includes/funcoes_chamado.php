<?php
/**
 * Funções compartilhadas para gerenciamento de chamados
 */

/**
 * Busca os dados completos de um chamado pelo ID
 */
function buscarChamadoPorId($conn, $chamado_id) {
    $sql = "SELECT c.*, 
                   u.nome AS usuario_nome, 
                   u.email AS usuario_email, 
                   u.posto_graduacao, 
                   u.nome_guerra,
                   t.nome AS tecnico_nome,
                   t.posto_graduacao AS tecnico_posto,
                   t.nome_guerra AS tecnico_nome_guerra
            FROM chamados c 
            JOIN usuarios u ON c.id_usuario_abriu = u.id
            LEFT JOIN usuarios t ON c.id_tecnico_responsavel = t.id
            WHERE c.id = ?";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return false;
    }
    
    $stmt->bind_param("i", $chamado_id);
    if (!$stmt->execute()) {
        return false;
    }
    
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

/**
 * Busca lista de técnicos (admin e técnicos)
 */
function buscarTecnicos($conn) {
    $sql = "SELECT id, nome, posto_graduacao, nome_guerra 
            FROM usuarios 
            WHERE tipo IN ('admin', 'tecnico') 
            ORDER BY posto_graduacao";
    
    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        return $result->fetch_all(MYSQLI_ASSOC);
    }
    
    return [];
}

/**
 * Busca histórico de um chamado
 */
function buscarHistoricoChamado($conn, $chamado_id) {
    $sql = "SELECT h.*, 
                   u.nome AS tecnico_nome,
                   u.nome_guerra AS tecnico_nome_guerra, 
                   u.posto_graduacao AS tecnico_posto_graduacao
            FROM historico_chamados h 
            LEFT JOIN usuarios u ON h.tecnico_id = u.id 
            WHERE h.chamado_id = ? 
            ORDER BY h.data_acao DESC";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return false;
    }
    
    $stmt->bind_param("i", $chamado_id);
    if (!$stmt->execute()) {
        return false;
    }
    
    return $stmt->get_result();
}

/**
 * Verifica se usuário tem permissão para ver o chamado
 */
function verificarPermissaoChamado($chamado, $usuario_id, $usuario_tipo) {
    if ($usuario_tipo === 'admin' || $usuario_tipo === 'tecnico') {
        return true;
    }
    
    if ($chamado['id_usuario_abriu'] === $usuario_id) {
        return true;
    }
    
    return false;
}

/**
 * Atualiza um chamado (status, prioridade e técnico)
 */
function atualizarChamado($conn, $chamado_id, $dados, $usuario_id, $usuario_tipo) {
    $novo_status = $dados['status'];
    $prioridade = $dados['prioridade'];
    $tecnico_id = $usuario_id;

    $comentario = $dados['comentario'] ?? '';
    
    // Se for admin e tiver selecionado um técnico, usar o técnico selecionado
    if ($usuario_tipo === 'admin' && !empty($dados['tecnico_responsavel'])) {
        $tecnico_id = intval($dados['tecnico_responsavel']);
    }
    
    // Preparar query de update
    if ($usuario_tipo === 'admin') {
        $sql_update = "UPDATE chamados SET status = ?, prioridade = ?, id_tecnico_responsavel = ? WHERE id = ?";
        $stmt_update = $conn->prepare($sql_update);
        if ($stmt_update) {
            $stmt_update->bind_param("ssii", $novo_status, $prioridade, $tecnico_id, $chamado_id);
        }
    } else {
        $sql_update = "UPDATE chamados SET status = ?, prioridade = ? WHERE id = ?";
        $stmt_update = $conn->prepare($sql_update);
        if ($stmt_update) {
            $stmt_update->bind_param("ssi", $novo_status, $prioridade, $chamado_id);
        }
    }
    
    if ($stmt_update && $stmt_update->execute()) {
        // Registrar no histórico
        $acao = "Chamado atualizado";
        $observacao = "Prioridade: " . $prioridade . ", Status: " . $novo_status;
        
        if ($usuario_tipo === 'admin' && !empty($dados['tecnico_responsavel'])) {
            $observacao .= ", Técnico atribuído";
        }
        
        $sql_historico = "INSERT INTO historico_chamados (chamado_id, tecnico_id, acao, observacao) 
                        VALUES (?, ?, ?, ?)";
        $stmt_historico = $conn->prepare($sql_historico);
        if ($stmt_historico) {
            $stmt_historico->bind_param("iiss", $chamado_id, $usuario_id, $acao, $observacao);
            $stmt_historico->execute();
            $stmt_historico->close();
        }

        // Adicionar comentário se existir
        if (!empty(trim($comentario))) {
            adicionarComentarioChamado($conn, $chamado_id, $usuario_id, $comentario);
        }
        
        return true;
    }
    
    return false;
}

function buscarChamadosComFiltro($conn, $filtros = [], $usuario_id = null, $usuario_tipo = 'usuario') {
    $whereConditions = [];
    $params = [];
    $types = "";
    
    // Se for usuário comum em meus_chamados, filtrar apenas seus chamados
    if ($usuario_tipo === 'usuario' && strpos($_SERVER['PHP_SELF'], 'meus_chamados.php') !== false) {
        $whereConditions[] = "c.id_usuario_abriu = ?";
        $params[] = $usuario_id;
        $types .= "i";
    }
    
    // Aplicar filtros de status
    if (isset($filtros['status']) && $filtros['status'] !== '') {
        $whereConditions[] = "c.status = ?";
        $params[] = $filtros['status'];
        $types .= "s";
    } else {
        // Por padrão, ocultar chamados fechados (exceto quando especificamente filtrados)
        $whereConditions[] = "c.status != 'fechado'";
    }
    
    // Outros filtros (prioridade, busca, técnico)
    if (isset($filtros['prioridade']) && $filtros['prioridade'] !== '') {
        $whereConditions[] = "c.prioridade = ?";
        $params[] = $filtros['prioridade'];
        $types .= "s";
    }
    
    if (isset($filtros['search']) && $filtros['search'] !== '') {
        $search = "%{$filtros['search']}%";
        $whereConditions[] = "(c.titulo LIKE ? OR c.descricao LIKE ? OR u.nome LIKE ? OR u.nome_guerra LIKE ?)";
        $params[] = $search;
        $params[] = $search;
        $params[] = $search;
        $params[] = $search;
        $types .= "ssss";
    }
    
    if (isset($filtros['tecnico']) && $filtros['tecnico'] !== '') {
        $whereConditions[] = "c.id_tecnico_responsavel = ?";
        $params[] = $filtros['tecnico'];
        $types .= "i";
    }
    
    $whereClause = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "";
    
    $sql = "SELECT c.*, 
                   u.nome as usuario_nome, 
                   u.nome_guerra as usuario_nome_guerra,
                   u.posto_graduacao as usuario_posto,
                   t.nome as tecnico_nome,
                   t.nome_guerra as tecnico_nome_guerra,
                   t.posto_graduacao as tecnico_posto
            FROM chamados c
            LEFT JOIN usuarios u ON c.id_usuario_abriu = u.id
            LEFT JOIN usuarios t ON c.id_tecnico_responsavel = t.id
            $whereClause
            ORDER BY 
                CASE 
                    WHEN c.prioridade = 'alta' THEN 1
                    WHEN c.prioridade = 'media' THEN 2
                    WHEN c.prioridade = 'baixa' THEN 3
                    ELSE 4
                END,
                c.data_abertura ASC";
    
    $stmt = $conn->prepare($sql);
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    if ($stmt->execute()) {
        return $stmt->get_result();
    } else {
        return false;
    }
}

/**
 * Retorna estatísticas de chamados (excluindo fechados por padrão)
 */
function buscarEstatisticasChamados($conn, $incluir_fechados = false) {
    $sql = "SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'aberto' THEN 1 ELSE 0 END) as abertos,
        SUM(CASE WHEN status = 'em_andamento' THEN 1 ELSE 0 END) as em_andamento,
        SUM(CASE WHEN status = 'fechado' THEN 1 ELSE 0 END) as fechados,
        SUM(CASE WHEN prioridade = 'alta' THEN 1 ELSE 0 END) as alta,
        SUM(CASE WHEN prioridade = 'media' THEN 1 ELSE 0 END) as media,
        SUM(CASE WHEN prioridade = 'baixa' THEN 1 ELSE 0 END) as baixa
    FROM chamados";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt || !$stmt->execute()) {
        return false;
    }
    
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

/**
 * Busca arquivos anexos de um chamado
 */
function buscarArquivosChamado($conn, $chamado_id) {
    $sql = "SELECT arquivos FROM chamados WHERE id = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [];
    }
    
    $stmt->bind_param("i", $chamado_id);
    if (!$stmt->execute()) {
        return [];
    }
    
    $result = $stmt->get_result();
    $chamado = $result->fetch_assoc();
    
    if (!$chamado || empty($chamado['arquivos'])) {
        return [];
    }
    
    $nomes_arquivos = explode(',', $chamado['arquivos']);
    $arquivos = [];
    
    foreach ($nomes_arquivos as $arquivo) {
        $arquivo = trim($arquivo);
        if (!empty($arquivo)) {
            $caminho_completo = 'uploads/' . $arquivo;
            if (file_exists($caminho_completo)) {
                $arquivos[] = [
                    'nome' => $arquivo,
                    'caminho' => $caminho_completo,
                    'tamanho' => filesize($caminho_completo),
                    'tipo' => function_exists('mime_content_type') ? mime_content_type($caminho_completo) : 'application/octet-stream'
                ];
            }
        }
    }
    
    return $arquivos;
}

/**
 * Formata o tamanho do arquivo para leitura
 */
function formatarTamanhoArquivo($bytes) {
    if ($bytes == 0) return '0 Bytes';
    
    $k = 1024;
    $tamanhos = ['Bytes', 'KB', 'MB', 'GB'];
    $i = floor(log($bytes, $k));
    
    return number_format($bytes / pow($k, $i), 2) . ' ' . $tamanhos[$i];
}

/**
 * Retorna o label formatado de uma patente
 */
function formatarPatente($codigo_patente) {
    $patentes = [
        "cel" => "Cel", 
        "tc" => "TC", 
        "maj" => "Maj", 
        "cap" => "Cap", 
        "1ten" => "1°Ten", 
        "2ten" => "2°Ten", 
        "asp" => "Asp", 
        "s_ten" => "S Ten", 
        "1sgt" => "1°Sgt", 
        "2sgt" => "2°Sgt", 
        "3sgt" => "3°Sgt", 
        "cb" => "Cb", 
        "sd_ep" => "Sd EP",
        "sd_ev" => "Sd EV"
    ];
    
    return $patentes[$codigo_patente] ?? $codigo_patente;
}

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

/**
 * Calcula a posição na fila de prioridade para atendimento
 */
function calcularPosicaoFila($conn, $prioridade, $data_abertura, $chamado_id = null) {
    // Definir pesos para prioridades
    $pesos_prioridade = [
        'alta' => 3,
        'media' => 2, 
        'baixa' => 1
    ];
    
    // Buscar todos os chamados ativos (não fechados) ordenados por prioridade e data
    $sql = "SELECT id, prioridade, data_abertura 
            FROM chamados 
            WHERE status != 'fechado' 
            ORDER BY 
                CASE prioridade 
                    WHEN 'alta' THEN 1 
                    WHEN 'media' THEN 2 
                    WHEN 'baixa' THEN 3 
                END,
                data_abertura ASC";
    
    $result = $conn->query($sql);
    
    if (!$result || $result->num_rows === 0) {
        return 1;
    }
    
    $posicao = 1;
    $encontrado = false;
    
    while ($chamado = $result->fetch_assoc()) {
        if ($chamado_id && $chamado['id'] == $chamado_id) {
            $encontrado = true;
            break;
        }
        $posicao++;
    }
    
    return $posicao;
}

/**
 * Função para exibir a posição na fila com ícone
 */
function exibirPosicaoFila($posicao) {
    if ($posicao == 1) {
        return '<span class="badge bg-success" title="Próximo a ser atendido">1º 🔥</span>';
    } elseif ($posicao <= 3) {
        return '<span class="badge bg-warning text-dark" title="Em breve">' . $posicao . 'º ⚡</span>';
    } else {
        return '<span class="badge bg-secondary" title="Na fila">' . $posicao . 'º 📋</span>';
    }
}

/**
 * Função completa para fila de prioridade (compatibilidade)
 */
function filaPrioridadeAtendimento($conn, $prioridade, $data_abertura, $chamado_id = null) {
    $posicao = calcularPosicaoFila($conn, $prioridade, $data_abertura, $chamado_id);
    return exibirPosicaoFila($posicao);
}

/**
 * Adiciona um comentário a um chamado
 */
function adicionarComentarioChamado($conn, $chamado_id, $usuario_id, $comentario) {
    $sql = "INSERT INTO comentarios (id_chamado, id_usuario, comentario) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        return false;
    }
    
    $stmt->bind_param("iis", $chamado_id, $usuario_id, $comentario);
    
    if ($stmt->execute()) {
        return true;
    }
    
    return false;
}

/**
 * Busca comentários de um chamado
 */
function buscarComentariosChamado($conn, $chamado_id) {
    $sql = "SELECT c.*, u.nome, u.nome_guerra, u.posto_graduacao 
            FROM comentarios c 
            JOIN usuarios u ON c.id_usuario = u.id 
            WHERE c.id_chamado = ? 
            ORDER BY c.data ASC";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return false;
    }
    
    $stmt->bind_param("i", $chamado_id);
    if (!$stmt->execute()) {
        return false;
    }
    
    return $stmt->get_result();
}
?>