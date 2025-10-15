CREATE DATABASE chamado;

CREATE TABLE usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100),
    email VARCHAR(100) UNIQUE,
    senha VARCHAR(255),
    tipo ENUM('admin', 'tecnico', 'usuario') DEFAULT 'usuario',
    posto_graduacao VARCHAR(10),
    nome_guerra VARCHAR(20),
    data_cadastro DATETIME
);

DELETE FROM usuarios WHERE email = 'admin@empresa.com';
INSERT INTO usuarios (nome, email, senha, tipo, posto_graduacao, nome_guerra)
VALUES ('Diego Rodrigues Cristaldo', 'diegorcristaldo@hotmail.com', 
        '$2y$10$0.nnefQKjxTufdCaqfJa4O5P5zAFECQ/pZJXgqq/HTqw3nWYyH76m', 
        'admin', '2°Sgt', 'Diego');
-- A senha acima é: 123456 (já criptografada com password_hash)
SELECT * FROM chamados;

UPDATE usuarios SET posto_graduacao = 'Sd EV' WHERE id = '2';
UPDATE usuarios SET nome_guerra = 'Diego' WHERE id = '3';

CREATE TABLE chamados (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(255) NOT NULL,
    descricao TEXT NOT NULL,
    arquivos TEXT,
    prioridade ENUM('baixa','media','alta') DEFAULT 'baixa',
    status ENUM('aberto','em_andamento','fechado') DEFAULT 'aberto',
    data_abertura DATETIME DEFAULT CURRENT_TIMESTAMP,
    id_usuario_abriu INT NOT NULL,
    id_tecnico_responsavel INT DEFAULT NULL,
    id_equipamento INT DEFAULT NULL,
    FOREIGN KEY (id_usuario_abriu) REFERENCES usuarios(id)
);

CREATE TABLE comentarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_chamado INT NOT NULL,
    id_usuario INT NOT NULL,
    comentario TEXT NOT NULL,
    data DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_chamado) REFERENCES chamados(id),
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id)
);

CREATE TABLE equipamentos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(255) NOT NULL,
    numero_patrimonio VARCHAR(50),
    setor VARCHAR(100)
);

CREATE TABLE chamados_arquivos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    chamado_id INT NOT NULL,
    nome_arquivo VARCHAR(255) NOT NULL,
    caminho_arquivo VARCHAR(500) NOT NULL,
    data_upload DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (chamado_id) REFERENCES chamados(id) ON DELETE CASCADE
);

CREATE TABLE historico_chamados (
    id INT AUTO_INCREMENT PRIMARY KEY,
    chamado_id INT NOT NULL,
    tecnico_id INT DEFAULT NULL,
    acao VARCHAR(100) NOT NULL,
    observacao TEXT,
    data_acao DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (chamado_id) REFERENCES chamados(id) ON DELETE CASCADE,
    FOREIGN KEY (tecnico_id) REFERENCES usuarios(id)
);

CREATE TABLE recuperacao_senha (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_usuario INT NOT NULL,
    codigo VARCHAR(6) NOT NULL,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id) ON DELETE CASCADE
);

ALTER TABLE chamados 
ADD COLUMN tipo_solicitacao ENUM('apoio', 'problema', 'instalacao') AFTER descricao,
ADD COLUMN companhia ENUM('estado_maior', 'ccap', 'cm', 'cs') AFTER prioridade,
ADD COLUMN secao VARCHAR(100) AFTER companhia;

-- Atualizar dados existentes baseado no título
UPDATE chamados SET tipo_solicitacao = 'problema' WHERE titulo LIKE '%problema%';
UPDATE chamados SET tipo_solicitacao = 'apoio' WHERE titulo LIKE '%apoio%';
UPDATE chamados SET tipo_solicitacao = 'instalacao' WHERE titulo LIKE '%instala%';

ALTER TABLE equipamentos
ADD COLUMN ip VARCHAR(15) AFTER nome,
ADD COLUMN mac VARCHAR(20) AFTER ip;

ALTER TABLE equipamentos 
CHANGE COLUMN setor secao VARCHAR(50);

ALTER TABLE equipamentos 
DROP COLUMN numero_patrimonio;

INSERT INTO equipamentos(nome, ip, mac) VALUES('ALMOX-ADJ-DESK', '10.12.124.111', 'a8:a1:59:6a:31:2c');
INSERT INTO equipamentos(nome, ip, mac) VALUES('ALMOX-AUX02-DESK', '10.12.124.112', '70:71:bc:f7:2b:3f');
INSERT INTO equipamentos(nome, ip, mac) VALUES('ALMOX-AUX03-DESK', '10.12.124.113', 'd4:30:7e:c2:59:bc');
INSERT INTO equipamentos(nome, ip, mac) VALUES('ALMOX-AUX04-DESK', '10.12.124.114', '80:fa:5b:a7:31:a2');
INSERT INTO equipamentos(nome, ip, mac) VALUES('ALMOX-AUX05-2BLOG', '10.12.124.115', '10:78:d2:b4:92:6f');
INSERT INTO equipamentos(nome, ip, mac) VALUES('ALMOX-CH-DESK', '10.12.124.110', '80:fa:5b:a6:ff:15');
INSERT INTO equipamentos(nome, ip, mac) VALUES('CCAP-CMT-DESK', '10.12.124.77', '38:60:77:1a:d5:7a');
INSERT INTO equipamentos(nome, ip, mac) VALUES('CMNT-ADJ-DESK', '10.12.124.105', '1e:c9:d3:54:48:29');
INSERT INTO equipamentos(nome, ip, mac) VALUES('CMNT-CH-DESK', '10.12.125.104', 'e0:69:95:19:5f:cf');
INSERT INTO equipamentos(nome, ip, mac) VALUES('CMT-CLM-DESK', '10.12.125.68', '00:e4:4c:89:16:53');
INSERT INTO equipamentos(nome, ip, mac) VALUES('CMT-NOTE', '10.12.125.26', '8c:b0:e9:f2:80:60');
INSERT INTO equipamentos(nome, ip, mac) VALUES('COL-AUX01-DESK', '10.12.124.177', '70:71:bc:85:d9:40');
INSERT INTO equipamentos(nome, ip, mac) VALUES('COL-AUX02-DESK', '10.12.124.178', 'e0:69:95:10:7e:cc');
INSERT INTO equipamentos(nome, ip, mac) VALUES('COL-AUX03-DESK', '10.12.124.179', '90:2b:34:f3:a4:eb');
INSERT INTO equipamentos(nome, ip, mac) VALUES('COL-AUX04-DESK', '10.12.124.180', 'e0:69:95:19:5f:fd');
INSERT INTO equipamentos(nome, ip, mac) VALUES('COL-CH-DESK', '10.12.124.176', '1c:69:7a:ef:d5:d2');
INSERT INTO equipamentos(nome, ip, mac) VALUES('CONF-AUX02-DESK', '10.12.124.234', '00:1c:c4:10:9e:5d');
INSERT INTO equipamentos(nome, ip, mac) VALUES('CONF-CH-DESK', '10.12.124.233', '1c:69:7a:e7:29:9c');
INSERT INTO equipamentos(nome, ip, mac) VALUES('DESPOCLASS5-CH-DESK', '10.12.125.73', '00:24:1d:f8:f0:38');
INSERT INTO equipamentos(nome, ip, mac) VALUES('FISC-AUX02-DESK', '10.12.124.192', '1c:69:7a:ef:d5:73');
INSERT INTO equipamentos(nome, ip, mac) VALUES('FISC-AUX03-DESK', '10.12.124.193', '70:71:bc:f7:2e:83');
INSERT INTO equipamentos(nome, ip, mac) VALUES('FISC-AUX04-DESK', '10.12.124.194', '6c:f0:49:f1:5f:33');
INSERT INTO equipamentos(nome, ip, mac) VALUES('FISC-AUX05-DESK', '10.12.124.195', '70:71:bc:85:e1:63');
INSERT INTO equipamentos(nome, ip, mac) VALUES('FISC-CH-DESK', '10.12.124.191', '12:0b:a9:47:d2:48');
INSERT INTO equipamentos(nome, ip, mac) VALUES('GRCP-AUX07-DESK', '10.12.125.85', '50:3e:aa:0d:91:a1');
INSERT INTO equipamentos(nome, ip, mac) VALUES('GRCP-CH-DESK', '10.12.125.84', '84:2b:2b:7c:06:ef');
INSERT INTO equipamentos(nome, ip, mac) VALUES('JUR-CH-DESK', '10.12.124.241', '00:24:1d:f9:4d:05');
INSERT INTO equipamentos(nome, ip, mac) VALUES('PCA-AUX01-DESK', '10.12.125.35', '6c:f0:49:f7:c9:59');
INSERT INTO equipamentos(nome, ip, mac) VALUES('PCA-AUX02-DESK', '10.12.125.36', '4c:72:b9:73:9c:0a');
INSERT INTO equipamentos(nome, ip, mac) VALUES('PCA-AUX03-DESK', '10.12.125.37', '64:1c:67:90:29:65');
INSERT INTO equipamentos(nome, ip, mac) VALUES('PCA-CH-DESK', '10.12.125.34', '50:3e:aa:06:c7:cb');
INSERT INTO equipamentos(nome, ip, mac) VALUES('PELCOM-ADJ-DESK', '10.12.125.2', '1c:69:7a:ec:57:e0');
INSERT INTO equipamentos(nome, ip, mac) VALUES('PELCOM-AUX01-DESK', '10.12.125.7', 'bc:5f:f4:46:ba:55');
INSERT INTO equipamentos(nome, ip, mac) VALUES('PELCOM-AUX02-DESK', '10.12.125.6', '50:3e:aa:0e:1f:60');
INSERT INTO equipamentos(nome, ip, mac) VALUES('PELCOM-AUX03-DESK', '10.12.125.5', 'd8:5e:d3:f0:40:69');
INSERT INTO equipamentos(nome, ip, mac) VALUES('PELCOM-CH-DESK', '10.12.125.1', '1c:69:7a:ef:cf:b4');
INSERT INTO equipamentos(nome, ip, mac) VALUES('PELCOM-ENCMAT-DESK', '10.12.125.3', 'd8:5e:d3:f0:40:e1');
INSERT INTO equipamentos(nome, ip, mac) VALUES('PELPESADO-AUX01-DESK', '10.12.124.250', 'e0:69:95:10:78:8f');
INSERT INTO equipamentos(nome, ip, mac) VALUES('PELPESADO-AUX02-DESK', '10.12.124.251', '00:40:a7:1e:13:fa');
INSERT INTO equipamentos(nome, ip, mac) VALUES('PELPESADO-AUX03-DESK', '10.12.124.252', '34:e6:d7:56:0f:b3');
INSERT INTO equipamentos(nome, ip, mac) VALUES('PELPESADO-CH-DESK', '10.12.124.249', '90:2b:34:f3:a4:7b');
INSERT INTO equipamentos(nome, ip, mac) VALUES('PO-CH-DESK', '10.12.124.106', '38:60:77:13:ef:23');
INSERT INTO equipamentos(nome, ip, mac) VALUES('PST-AUX04-DESK', '10.12.124.69', '06:f0:49:f7:c0:40');
INSERT INTO equipamentos(nome, ip, mac) VALUES('PST-AUX05-DESK', '10.12.124.70', '00:24:1d:f9:50:91');
INSERT INTO equipamentos(nome, ip, mac) VALUES('PST-CH-DESK', '10.12.124.68', '38:60:77:1a:e5:a9');
INSERT INTO equipamentos(nome, ip, mac) VALUES('RP-AUX07-DESK', '10.12.125.44', '84:99:ba:58:95:21');
/* Tava o Mac do S1-Ch
INSERT INTO equipamentos(nome, ip, mac) VALUES('RP-CH-DESK', '10.12.125.43', 'e0:69:95:3b:20:fe');
*/
INSERT INTO equipamentos(nome, ip, mac) VALUES('S1-AUX01-DESK', '10.12.124.124', '90:2b:34:f3:a5:8c');
INSERT INTO equipamentos(nome, ip, mac) VALUES('S1-AUX02-DESK', '10.12.124.125', '6c:f0:49:f0:f0:97');
INSERT INTO equipamentos(nome, ip, mac) VALUES('S1-AUX03-DESK', '10.12.124.126', '38:60:77:1a:d3:9f');
INSERT INTO equipamentos(nome, ip, mac) VALUES('S1-BDA-DESK', '10.12.124.127', '90:2b:34:f3:a3:ea');
INSERT INTO equipamentos(nome, ip, mac) VALUES('S1-CH-DESK', '10.12.124.123', 'e0:69:95:3b:20:fe');
INSERT INTO equipamentos(nome, ip, mac) VALUES('S2-AUX1-NOTE', '10.12.124.56', '0c:cc:47:e6:ba:b6');
INSERT INTO equipamentos(nome, ip, mac) VALUES('S2-AUX2-NOTE', '10.12.124.57', 'a8:1e:84:d9:b0:1c');
INSERT INTO equipamentos(nome, ip, mac) VALUES('S2-CH-NOTE', '10.12.124.55', '0c:cc:47:e7:c3:3c');
INSERT INTO equipamentos(nome, ip, mac) VALUES('S3-AUX02-DESK', '10.12.124.151', 'c8:9c:dc:40:2c:14');
INSERT INTO equipamentos(nome, ip, mac) VALUES('S3-AUX04-DESK', '10.12.124.152', '40:61:86:ff:5d:1b');
INSERT INTO equipamentos(nome, ip, mac) VALUES('S3-CH-DESK', '10.12.124.150', '1c:69:7a:ec:5f:6a');
INSERT INTO equipamentos(nome, ip, mac) VALUES('S4-AUX01-DESK', '10.12.124.164', 'e0:69:95:10:7d:23');
INSERT INTO equipamentos(nome, ip, mac) VALUES('S4-AUX02-DESK', '10.12.124.165', '00:24:1d:f9:4f:1f');
INSERT INTO equipamentos(nome, ip, mac) VALUES('S4-AUX03-DESK', '10.12.124.166', '84:2b:2b:7c:0b:b2');
INSERT INTO equipamentos(nome, ip, mac) VALUES('S4-CH-DESK', '10.12.124.163', '1c:69:7a:ec:56:69');
INSERT INTO equipamentos(nome, ip, mac) VALUES('SALC-ADJ-DESK', '10.12.124.208', '80:fa:5b:a6:ff:3a');
INSERT INTO equipamentos(nome, ip, mac) VALUES('SALC-AUX01-DESK', '10.12.124.212', 'a8:a1:59:96:54:d9');
INSERT INTO equipamentos(nome, ip, mac) VALUES('SALC-AUX02-DESK', '10.12.124.211', '90:2b:34:f3:a5:6b');
INSERT INTO equipamentos(nome, ip, mac) VALUES('SALC-AUX03-DESK', '10.12.124.209', '90:2b:34:f3:a5:35');
INSERT INTO equipamentos(nome, ip, mac) VALUES('SALC-AUX04-DESK', '10.12.124.210', '70:71:bc:f7:36:62');
INSERT INTO equipamentos(nome, ip, mac) VALUES('SALC-CH-DESK', '10.12.124.207', '02:d7:6d:27:31:70');
INSERT INTO equipamentos(nome, ip, mac) VALUES('SAU-AUX01-NOTE', '10.12.124.51', '74:e6:e2:ce:c5:aa');
INSERT INTO equipamentos(nome, ip, mac) VALUES('SAU-AUX02-NOTE', '10.12.124.52', 'e0:64:95:10:7e:80');
INSERT INTO equipamentos(nome, ip, mac) VALUES('SAU-AUX03-DESK', '10.12.124.53', '38:60:77:1a:dc:60');
INSERT INTO equipamentos(nome, ip, mac) VALUES('SAU-AUX04-DESK', '10.12.124.54', '00:25:11:b8:67:20');
INSERT INTO equipamentos(nome, ip, mac) VALUES('SAU-CH-NOTE', '10.12.124.50', '1c:39:47:0c:27:37');
INSERT INTO equipamentos(nome, ip, mac) VALUES('SEC-AUX01-DESK', '10.12.125.52', '70:71:bc:f7:28:bf');
INSERT INTO equipamentos(nome, ip, mac) VALUES('SEC-AUX02-DESK', '10.12.125.53', '00:24:10:f9:4b:4a');
INSERT INTO equipamentos(nome, ip, mac) VALUES('SEC-AUX03-DESK', '10.12.125.54', '00:1f:d0:ff:a9:98');
INSERT INTO equipamentos(nome, ip, mac) VALUES('SEC-CH-DESK', '10.12.125.51', '40:61:86:ff:59:77');
INSERT INTO equipamentos(nome, ip, mac) VALUES('SGTECCAP-AUX01-DESK', '10.12.124.79', '00:24:1d:f9:46:90');
INSERT INTO equipamentos(nome, ip, mac) VALUES('SGTECCAP-AUX02-DESK', '10.12.124.80', '38:60:77:1a:d4:d3');
INSERT INTO equipamentos(nome, ip, mac) VALUES('SGTECCAP-AUX03-DESK', '10.12.124.81', 'e0:69:95:19:60:69');
INSERT INTO equipamentos(nome, ip, mac) VALUES('SGTECCAP-CH-DESK', '10.12.124.78', '84:2b:2b:7c:07:41');
INSERT INTO equipamentos(nome, ip, mac) VALUES('SGTECLM-AUX01-DESK', '10.12.125.70', '70:71:bc:f7:2e:77');
INSERT INTO equipamentos(nome, ip, mac) VALUES('SGTECLM-AUX02-DESK', '10.12.125.71', 'e0:69:95:3d:1d:e6');
INSERT INTO equipamentos(nome, ip, mac) VALUES('SGTECLM-CH-DESK', '10.12.125.69', '40:61:86:ff:5b:0a');
INSERT INTO equipamentos(nome, ip, mac) VALUES('SGTECLS-AUX01-DESK', '10.12.124.95', 'e0:69:95:19:46:14');
INSERT INTO equipamentos(nome, ip, mac) VALUES('SGTECLS-AUX02-DESK', '10.12.124.96', '70:71:bc:f7:28:fb');
INSERT INTO equipamentos(nome, ip, mac) VALUES('SGTECLS-AUX04-DESK', '10.12.124.97', '84:2b:2b:70:07:68');
INSERT INTO equipamentos(nome, ip, mac) VALUES('SGTECLS-CH-DESK', '10.12.124.94', 'd8:5e:d3:f0:41:29');
INSERT INTO equipamentos(nome, ip, mac) VALUES('SIMB-CH-NOTE', '10.12.124.63', '80:fa:5b:a7:31:9a');
INSERT INTO equipamentos(nome, ip, mac) VALUES('SPP-AUX01-DESK', '10.12.125.111', '1c:69:7a:ec:56:10');
INSERT INTO equipamentos(nome, ip, mac) VALUES('SPP-AUX02-DESK', '10.12.125.112', '1c:39:47:0c:2e:e5');
INSERT INTO equipamentos(nome, ip, mac) VALUES('SPP-AUX03-DESK', '10.12.125.113', 'e0:69:95:10:7d:67');
INSERT INTO equipamentos(nome, ip, mac) VALUES('SPP-AUX04-DESK', '10.12.125.114', 'e0:69:95:19:46:6c');
INSERT INTO equipamentos(nome, ip, mac) VALUES('SPP-CH-DESK', '10.12.125.110', '00:e4:4c:84:1e:e2');
INSERT INTO equipamentos(nome, ip, mac) VALUES('STI-NOTE', '10.12.125.8', '80:fa:5b:a7:31:a1');
INSERT INTO equipamentos(nome, ip, mac) VALUES('SUBTENCLM-AUX01-DESK', '10.12.125.72', '40:61:86:ff:5c:35');
INSERT INTO equipamentos(nome, ip, mac) VALUES('TES-ADJ-DESK', '10.12.124.223', '1c:69:7a:e9:88:6f');
INSERT INTO equipamentos(nome, ip, mac) VALUES('TES-AUX01-DESK', '10.12.124.224', 'e0:69:95:ed:09:16');
INSERT INTO equipamentos(nome, ip, mac) VALUES('TES-AUX02-DESK', '10.12.124.225', 'e0:69:95:3b:21:fd');
INSERT INTO equipamentos(nome, ip, mac) VALUES('TES-AUX03-DESK', '10.12.124.226', '90:2b:34:f3:a5:f0');
INSERT INTO equipamentos(nome, ip, mac) VALUES('TES-AUX04-DESK', '10.12.124.227', '1c:39:47:0b:fb:1f');
INSERT INTO equipamentos(nome, ip, mac) VALUES('TES-CH-NOTE', '10.12.124.222', '5c:c9:d3:eb:2f:7f');
INSERT INTO equipamentos(nome, ip, mac) VALUES('intranet', '10.12.124.7', 'f6:89:3b:3a:9d:00');
INSERT INTO equipamentos(nome, ip, mac) VALUES('sisbar', '10.12.124.15', '06:1c:8c:42:58:cc');