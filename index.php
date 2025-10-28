<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

require_once 'includes/db.php';
require_once 'includes/funcoes_chamado.php';

// Buscar notificações do usuário
$total_notificacoes = buscarNotificacoesUsuario($conn, $_SESSION['usuario_id']);

// Buscar detalhes das notificações para o dropdown
$notificacoes_detalhes = buscarDetalhesNotificacoes($conn, $_SESSION['usuario_id'], 5);
require_once 'header.php';

?>
</head>
<body class="d-flex flex-row">
  <header>
    <nav id="sidebar" class="d-flex flex-column p-3">
      <h4 class="text-center mb-4">Assistência Técnica</h4>
      
      <!-- Perfil do usuário com notificações -->
      <div class="dropdown mb-4 px-3">
        <a href="#" class="d-flex align-items-center text-white text-decoration-none dropdown-toggle" data-bs-toggle="dropdown">
          <div>
            <strong>
              <?= htmlspecialchars(formatarPatente($_SESSION['usuario_posto_graduacao']) ?? '') . ' ' . htmlspecialchars($_SESSION['usuario_nome_guerra'] ?? '') ?>
            </strong><br/>
            <small><?= ucfirst($_SESSION['usuario_tipo'] ?? '') ?></small>
          </div>
        </a>
        <ul class="dropdown-menu dropdown-menu-dark text-small shadow">
          <li><a class="dropdown-item" href="meus_chamados.php">Meus Chamados</a></li>
          <li><hr class="dropdown-divider"></li>
          <li><a class="dropdown-item" href="logout.php">Sair</a></li>
        </ul>
      </div>

      <ul class="nav nav-pills flex-column mb-auto">
        <li class="nav-item">
          <a href="abrir_chamado.php" class="nav-link">📝 Abrir Chamado</a>
        </li>
        <li class="nav-item position-relative">
          <a href="meus_chamados.php" class="nav-link">📋 Meus Chamados</a>
          <?php if ($total_notificacoes > 0): ?>
          <span class="position-absolute top-50 start-100 translate-middle badge rounded-pill bg-danger notificacao-badge">+
            <?= $total_notificacoes ?>
            <span class="visually-hidden">notificações</span>
          </span>
          <?php endif; ?>
        </li>
        <li class="nav-item">
          <a href="visualizar_chamados.php" class="nav-link">📋 Visualizar Chamados</a>
        </li>

        <?php if ($_SESSION['usuario_tipo'] === 'admin' || $_SESSION['usuario_tipo'] === 'tecnico'): ?>
        <li class="nav-item">
          <a href="equipamentos.php" class="nav-link">💻 Equipamentos</a>
        </li>
        <li class="nav-item">
          <a href="usuarios.php" class="nav-link">👥 Usuários</a>
        </li>
        <?php endif; ?>        
        <li class="nav-item">
          <a href="relatorios.php" class="nav-link">📊 Relatórios</a>
        </li>
        <li class="nav-item">
          <a href="logout.php" class="nav-link mt-4">🚪 Sair</a>
        </li>
      </ul>
      <small class="text-white p-4 text-center">Sistema desenvolvido pelo 2° Sgt Eng <strong>DIEGO</strong> Rodrigues Cristaldo</small>
    </nav>
  </header>

  <main id="content">
    <h1>Bem-vindo, <?= htmlspecialchars(formatarPatente($_SESSION['usuario_posto_graduacao']) ?? '') . ' ' . htmlspecialchars($_SESSION['usuario_nome_guerra'] ?? '') ?>!</h1>
    <p>Use o menu à esquerda para navegar pelo sistema.</p>
    <div class="card shadow-sm border-0">
      <div class="card-body p-4">
        <h4 class="card-title mb-3">
          <i class="bi bi-ticket-detailed-fill text-primary"></i> Abrir um Chamado
        </h4>
        <p class="text-muted">
          Escolha o tipo de solicitação e descreva sua necessidade.  
          Nosso time vai atender o mais rápido possível.
        </p>

        <div class="row mt-4">
          <!-- Apoio -->
          <div class="col-md-4 mb-3">
            <div class="p-3 border rounded text-center h-100">
              <h5 class="text-warning"><i class="bi bi-hand-thumbs-up-fill"></i> Apoio</h5>
              <small class="text-muted d-block mb-3">
                Login de internet, VPN, videoconferência, solicitações gerais.
              </small>
              <a href="abrir_chamado.php" class="btn btn-warning w-100">Solicitar Apoio</a>
            </div>
          </div>

          <!-- Problema -->
          <div class="col-md-4 mb-3">
            <div class="p-3 border rounded text-center h-100">
              <h5 class="text-danger"><i class="bi bi-exclamation-triangle-fill"></i> Problema</h5>
              <small class="text-muted d-block mb-3">
                Computadores, notebooks, impressoras, rede, entre outros.
              </small>
              <a href="abrir_chamado.php" class="btn btn-danger w-100">Reportar Problema</a>
            </div>
          </div>

          <!-- Instalação -->
          <div class="col-md-4 mb-3">
            <div class="p-3 border rounded text-center h-100">
              <h5 class="text-dark"><i class="bi bi-tools"></i> Instalação</h5>
              <small class="text-muted d-block mb-3">
                Antivírus, Siscofis, Java (SIAFI), token, ponto de rede, softwares em geral etc.
              </small>
              <a href="abrir_chamado.php" class="btn btn-secondary w-100">Solicitar Instalação</a>
            </div>
          </div>
        </div>
      </div>
    </div>
  </main>
</body>
</html>
