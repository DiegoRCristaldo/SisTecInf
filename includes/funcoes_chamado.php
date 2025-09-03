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
    $sql = "SELECT h.*, u.nome AS tecnico_nome 
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
        
        return true;
    }
    
    return false;
}

/**
 * Busca chamados com filtros
 */
function buscarChamadosComFiltro($conn, $filtros = [], $usuario_id = null, $usuario_tipo = null) {
    $where_conditions = [];
    $params = [];
    $types = '';
    
    if ($usuario_tipo !== 'admin' && $usuario_tipo !== 'tecnico') {
        $where_conditions[] = "c.id_usuario_abriu = ?";
        $params[] = $usuario_id;
        $types .= 'i';
    }
    
    // Por padrão, oculta chamados fechados a menos que seja explicitamente filtrado
    if (empty($filtros['status']) || $filtros['status'] !== 'fechado') {
        $where_conditions[] = "c.status != 'fechado'";
    }
    
    if (!empty($filtros['status'])) {
        $where_conditions[] = "c.status = ?";
        $params[] = $filtros['status'];
        $types .= 's';
    }
    
    if (!empty($filtros['prioridade'])) {
        $where_conditions[] = "c.prioridade = ?";
        $params[] = $filtros['prioridade'];
        $types .= 's';
    }
    
    if (!empty($filtros['search'])) {
        $where_conditions[] = "(c.titulo LIKE ? OR c.descricao LIKE ? OR u.nome LIKE ?)";
        $search_term = "%{$filtros['search']}%";
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
        $types .= 'sss';
    }
    
    if (!empty($filtros['tecnico']) && ($usuario_tipo === 'admin' || $usuario_tipo === 'tecnico')) {
        $where_conditions[] = "c.id_tecnico_responsavel = ?";
        $params[] = $filtros['tecnico'];
        $types .= 'i';
    }
    
    $sql = "SELECT c.*, u.nome AS usuario_nome, u.posto_graduacao, u.nome_guerra,
                   t.nome AS tecnico_nome
            FROM chamados c 
            JOIN usuarios u ON c.id_usuario_abriu = u.id
            LEFT JOIN usuarios t ON c.id_tecnico_responsavel = t.id";
    
    if (!empty($where_conditions)) {
        $sql .= " WHERE " . implode(" AND ", $where_conditions);
    }
    
    $sql .= " ORDER BY 
              CASE c.prioridade 
                WHEN 'alta' THEN 1 
                WHEN 'media' THEN 2 
                WHEN 'baixa' THEN 3 
              END,
              c.data_abertura DESC";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return false;
    }
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    if (!$stmt->execute()) {
        return false;
    }
    
    return $stmt->get_result();
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
    
    // Se não incluir fechados, adiciona where
    if (!$incluir_fechados) {
        $sql .= " WHERE status != 'fechado'";
    }
    
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
 * Formata o tamanho do arquivo para leitura humana
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
?>