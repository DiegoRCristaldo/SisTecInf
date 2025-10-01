<?php
require 'includes/db.php';
require 'includes/auth.php';

// Verificar se o formulário foi submetido
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: abrir_chamado.php");
    exit;
}

// Coletar dados do formulário
$titulo = trim($_POST['titulo']);
$descricao = trim($_POST['descricao']);
$prioridade = $_POST['prioridade'] ?? 'baixa';
$tipo_solicitacao = $_POST['tipo_solicitacao'] ?? '';
$companhia = $_POST['companhia'] ?? '';
$categoria = $_POST['categoria'] ?? '';
$secao = $_POST['secao'] ?? '';
$usuario_id = $_SESSION['usuario_id'];

// Validar campos obrigatórios
if (empty($tipo_solicitacao) || empty($companhia)) {
    header("Location: abrir_chamado.php?msg=erro&detail=" . urlencode("Tipo de solicitação e companhia são obrigatórios"));
    exit;
}

// Combinar informações adicionais na descrição
$descricao_completa = $descricao . "\n\n";
$descricao_completa .= "--- INFORMAÇÕES ADICIONAIS ---\n";
$descricao_completa .= "Tipo de Solicitação: " . ucfirst($tipo_solicitacao) . "\n";
if (!empty($categoria)) {
    $descricao_completa .= "Categoria: " . $categoria . "\n";
}
$descricao_completa .= "Companhia: " . ucfirst(str_replace('_', ' ', $companhia)) . "\n";
if (!empty($secao)) {
    $descricao_completa .= "Seção: " . $secao . "\n";
}

// Processar upload de arquivo
$arquivos_nomes = '';
if (isset($_FILES['anexar_arquivo']) && $_FILES['anexar_arquivo']['error'][0] === UPLOAD_ERR_OK) {
    $arquivos = $_FILES['anexar_arquivo'];
    $nomes_arquivos = [];
    
    // Cria a pasta se não existir
    if (!is_dir('uploads')) {
        mkdir('uploads', 0777, true);
    }
    
    // Processar cada arquivo
    for ($i = 0; $i < count($arquivos['name']); $i++) {
        if ($arquivos['error'][$i] === UPLOAD_ERR_OK) {
            $nome_temp = $arquivos['tmp_name'][$i];
            $nome_original = basename($arquivos['name'][$i]);
            $nome_unico = time() . '_' . $i . '_' . $nome_original;
            $destino = 'uploads/' . $nome_unico;
            
            if (move_uploaded_file($nome_temp, $destino)) {
                $nomes_arquivos[] = $nome_unico;
            }
        }
    }
    $arquivos_nomes = implode(',', $nomes_arquivos);
}

// Preparar e executar a query - AGORA COM OS NOVOS CAMPOS
$sql = "INSERT INTO chamados (titulo, descricao, tipo_solicitacao, companhia, secao, prioridade, id_usuario_abriu, arquivos) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

$stmt = $conn->prepare($sql);

if ($stmt) {
    $stmt->bind_param("ssssssis", 
        $titulo, 
        $descricao_completa, 
        $tipo_solicitacao, 
        $companhia, 
        $secao, 
        $prioridade, 
        $usuario_id, 
        $arquivos_nomes
    );
    
    if ($stmt->execute()) {
        $chamado_id = $stmt->insert_id;
        
        // Registrar no histórico
        $acao = "Chamado aberto";
        $observacao = "Chamado criado pelo usuário. Tipo: " . $tipo_solicitacao . ", Companhia: " . $companhia;
        
        $sql_historico = "INSERT INTO historico_chamados (chamado_id, acao, observacao) 
                        VALUES (?, ?, ?)";
        $stmt_historico = $conn->prepare($sql_historico);
        if ($stmt_historico) {
            $stmt_historico->bind_param("iss", $chamado_id, $acao, $observacao);
            $stmt_historico->execute();
            $stmt_historico->close();
        }
        
        header("Location: abrir_chamado.php?msg=sucesso");
        exit;
    } else {
        header("Location: abrir_chamado.php?msg=erro&detail=" . urlencode($stmt->error));
        exit;
    }
} else {
    header("Location: abrir_chamado.php?msg=erro&detail=" . urlencode($conn->error));
    exit;
}

$stmt->close();
$conn->close();
?>