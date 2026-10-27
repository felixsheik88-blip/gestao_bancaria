CREATE DATABASE IF NOT EXISTS gestao_bancaria CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE gestao_bancaria;

CREATE TABLE utilizadores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    perfil ENUM('Administrador','Gerente','Caixa','Auditor','Cliente') NOT NULL,
    estado ENUM('activo','bloqueado') DEFAULT 'activo',
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE clientes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    codigo_cliente VARCHAR(20) UNIQUE NOT NULL,
    nome_completo VARCHAR(150) NOT NULL,
    data_nascimento DATE NOT NULL,
    nuit VARCHAR(20) UNIQUE NOT NULL,
    bi_numero VARCHAR(30) UNIQUE NOT NULL,
    telefone VARCHAR(20) NOT NULL,
    email VARCHAR(100),
    endereco TEXT,
    estado ENUM('activo','inactivo') DEFAULT 'activo',
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE tipos_conta (id INT AUTO_INCREMENT PRIMARY KEY, nome VARCHAR(50), taxa_juro DECIMAL(5,2) DEFAULT 0);
INSERT INTO tipos_conta VALUES (1,'Corrente',0),(2,'Poupanca',3.5);

CREATE TABLE contas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    numero_conta VARCHAR(20) UNIQUE NOT NULL,
    cliente_id INT NOT NULL,
    tipo_conta_id INT NOT NULL,
    moeda ENUM('MZN','USD') DEFAULT 'MZN',
    saldo DECIMAL(15,2) DEFAULT 0.00,
    estado ENUM('activa','bloqueada','encerrada') DEFAULT 'activa',
    criada_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (cliente_id) REFERENCES clientes(id),
    FOREIGN KEY (tipo_conta_id) REFERENCES tipos_conta(id)
);

CREATE TABLE transacoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    referencia VARCHAR(30) UNIQUE NOT NULL,
    tipo ENUM('deposito','levantamento','transferencia_debito','transferencia_credito') NOT NULL,
    conta_id INT NOT NULL,
    montante DECIMAL(15,2) NOT NULL,
    saldo_antes DECIMAL(15,2) NOT NULL,
    saldo_depois DECIMAL(15,2) NOT NULL,
    descricao VARCHAR(255),
    estado ENUM('concluida','pendente','rejeitada') DEFAULT 'concluida',
    utilizador_id INT,
    idempotency_key VARCHAR(50) UNIQUE,
    criada_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (conta_id) REFERENCES contas(id)
);

CREATE TABLE transferencias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    referencia VARCHAR(30) UNIQUE NOT NULL,
    conta_origem_id INT NOT NULL,
    conta_destino_id INT NOT NULL,
    montante DECIMAL(15,2) NOT NULL,
    estado ENUM('concluida','pendente','rejeitada') DEFAULT 'concluida',
    motivo VARCHAR(255),
    criada_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (conta_origem_id) REFERENCES contas(id),
    FOREIGN KEY (conta_destino_id) REFERENCES contas(id)
);

CREATE TABLE cartoes (id INT AUTO_INCREMENT PRIMARY KEY, numero_cartao VARCHAR(20) UNIQUE NOT NULL, conta_id INT NOT NULL, estado ENUM('activo','bloqueado') DEFAULT 'activo', data_validade DATE NOT NULL, FOREIGN KEY (conta_id) REFERENCES contas(id));
CREATE TABLE auditoria (id INT AUTO_INCREMENT PRIMARY KEY, utilizador_id INT, accao VARCHAR(100), detalhes TEXT, ip VARCHAR(45), criada_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP);

-- Utilizadores de teste - senha: password
INSERT INTO utilizadores (username,email,password_hash,perfil) VALUES 
('admin','admin@banco.co.mz','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','Administrador'),
('gerente','gerente@banco.co.mz','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','Gerente'),
('caixa','caixa@banco.co.mz','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','Caixa');
