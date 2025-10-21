<?php
include 'includes/auth.php';
include 'includes/db.php';

// Apenas admin ou técnico podem gerenciar
if ($_SESSION['usuario_tipo'] === 'usuario') {
    die("Acesso negado!");
}

$result = $conn->query("SELECT * FROM equipamentos ORDER BY ip ASC");

require 'header.php';

?>
</head>
<body class="bg-light">

<div class="container mt-4">
    <div class="d-flex flex-column mb-4">
        <h2 class="mb-0">💻 Gerenciar Equipamentos</h2>
        <div class="d-flex justify-content-between">
            <div class="mt-3">
                <a href="index.php" class="btn btn-secondary">⬅ Voltar ao Menu</a>
            </div>
            <!-- Adicionar Equipamento-->
            <a href="equipamento_form.php" class="btn btn-primary mb-3 w-25">+ Novo Equipamento</a>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>ID</th>
                            <th>Nome</th>
                            <th>IP</th>
                            <th>MAC</th>
                            <th>Seção</th>
                            <th class="text-center">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?= $row['id'] ?></td>
                            <td><?= htmlspecialchars($row['nome']) ?></td>
                            <td><?= htmlspecialchars($row['ip']) ?></td>
                            <td><?= htmlspecialchars(strtoupper($row['mac'])) ?></td>
                            <td><?= htmlspecialchars($row['secao'] ?? '') ?></td>
                            <td class="d-flex text-center">
                                <a class="btn btn-sm btn-primary w-100 m-1" href="equipamento_form.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-primary">
                                    <i class="bi bi-pencil"></i> Editar
                                </a>
                                <a href="equipamento_excluir.php?id=<?= $row['id'] ?>" 
                                   onclick="return confirm('Tem certeza que deseja excluir?')"
                                   class="btn btn-sm btn-danger w-100 m-1">
                                    <i class="bi bi-trash"></i> Excluir
                                </a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap Icons -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
