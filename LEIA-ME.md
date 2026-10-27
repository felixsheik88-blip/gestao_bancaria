# Sistema de Gestão Bancária - Moçambique (Fase 1 + Fase 2)

## Instalação no XAMPP (passo a passo)

1. Abre o **XAMPP Control Panel** e clica em **Start** no **Apache** e no **MySQL**.
2. Extrai este zip e copia a pasta `gestao_bancaria` para `C:\xampp\htdocs\`
   (o caminho final deve ser `C:\xampp\htdocs\gestao_bancaria\login.php`).
3. Abre `http://localhost/phpmyadmin`.
4. Clica em **Importar** (sem seleccionar nenhuma BD) e escolhe `database/gestao_bancaria.sql`. Clica em **Executar**.
5. Abre `http://localhost/gestao_bancaria/` e entra com:

| Utilizador | Senha    | Perfil        |
|------------|----------|---------------|
| admin      | password | Administrador |
| gerente    | password | Gerente       |
| caixa      | password | Caixa         |

## Já tens a Fase 1 instalada?

Não precisas de mexer na base de dados (a Fase 2 não altera tabelas).
Basta copiar esta pasta por cima da antiga em `htdocs`, substituindo os ficheiros.

## Funcionalidades

**Clientes** (menu *Clientes*)
- Registar cliente (nome, data de nascimento, NUIT, BI, telefone, email, endereço) com código automático `CLI-000001`
- Validações: maior de 18 anos, NUIT com 9 dígitos, telefone moçambicano (82-87), NUIT e BI únicos
- Pesquisar, editar, ver ficha com as contas, activar/desactivar

**Contas** (menu *Contas*)
- Abrir conta Corrente ou Poupança, em MZN ou USD, com depósito inicial opcional
- Número de conta automático e único (`MZ00-0000-000000`)
- Extracto com filtros por data e tipo, impressão
- Bloquear, desbloquear e encerrar (só com saldo zero)

**Operações** (menu *Operações*)
- Depósito, levantamento e transferência entre contas (mesma moeda)
- Transações atómicas com `SELECT ... FOR UPDATE`: ou grava tudo, ou nada
- Protecção contra duplo clique / reenvio (chave de idempotência)
- Histórico com filtros e comprovativo para imprimir

**Auditoria** (Administrador, Gerente e Auditor): registo de logins e de todas as acções.

## Quem pode fazer o quê

| Acção                               | Admin | Gerente | Caixa | Auditor |
|-------------------------------------|:-----:|:-------:|:-----:|:-------:|
| Ver clientes, contas, operações     |  ✔   |   ✔    |  ✔   |   ✔    |
| Registar/editar clientes, abrir contas, fazer operações | ✔ | ✔ | ✔ | — |
| Activar/desactivar clientes, bloquear/encerrar contas | ✔ | ✔ | — | — |
| Ver auditoria                       |  ✔   |   ✔    |  —   |   ✔    |

## Configuração

- Dados da BD: `config/database.php` (por defeito `root` sem senha).
- URL base: `config/config.php` (`BASE_URL`). Se mudares o nome da pasta, altera aqui.

## Fase 3 (sugestão)

Gestão de utilizadores e perfis, cartões, relatórios e extractos em PDF.

> Nota: as senhas de teste são só para desenvolvimento. Altera-as antes de qualquer uso real.
