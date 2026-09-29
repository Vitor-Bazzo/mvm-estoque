# 🐜 MVM Estoque & Logística

> **Sistema Inteligente de Controle de Armazém, Auditoria Kardex e Gestão de Estoque**  
> Trabalho de Conclusão de Curso (TCC) — Desenvolvimento Web & Banco de Dados.

---

## 📋 Sobre o Projeto

O **MVM Estoque** é uma plataforma web completa desenvolvida para solucionar desafios reais de pequenas e médias empresas na gestão de mercadorias: rupturas de estoque, perda de produtos por validade, controle de custos e falta de auditoria de movimentações.

Construído com foco em **agilidade operacional**, o sistema conta com uma interface limpa, rápida e intuitiva, eliminando a sobrecarga de planilhas complexas e trazendo inteligência de dados para a rotina diária.

---

## ✨ Principais Funcionalidades

- **📊 Dashboard BI Executivo:**
  - Métricas de valor total do estoque, itens críticos, giro e movimentações semanais.
  - Alerta de validade inteligente pelo método **FEFO** (*First-Expired, First-Out*).
- **📑 Ficha Kardex Intuitiva:**
  - Auditoria contábil completa por item, calculando o **Custo Médio Ponderado (CMP)** e o saldo acumulado histórico.
- **🔄 Movimentações de Estoque:**
  - Registro ágil de **Entradas**, **Saídas** e **Devoluções**.
  - Drawer rápido de expedição com animação interativa de envio do caminhão.
- **🧾 Recibos Térmicos e Etiquetas:**
  - Emissão de comprovantes de conferência/expedição formatados para impressoras térmicas (58mm/80mm).
  - Geração de etiquetas de prateleira/gôndola com dados de lote e validade.
- **👥 Cadastros Integrados:**
  - Controle de Produtos (com fotos), Clientes e Fornecedores.
- **🌓 Design Moderno & Experiência de Uso:**
  - Modo Escuro e Modo Claro com chaveamento instantâneo.
  - PWA (Progressive Web App) com Service Worker para navegação suave e cache offline.
  - Identidade visual com o mascote oficial **A Formiga Operadora MVM**.

---

## 🛠️ Tecnologias Utilizadas

- **Back-end:** PHP 8.x (Arquitetura Procedural/Modular com PDO & MySQLi)
- **Banco de Dados:** MySQL / MariaDB
- **Front-end:** HTML5 Semântico, CSS3 Moderno (Vanilla CSS), JavaScript Vanilla (ES6+)
- **Bibliotecas:** Chart.js (gráficos), PHPMailer (notificações)
- **Servidor Local:** Apache (XAMPP / WampServer)

---

## 🚀 Instalação e Execução Local

### Pré-requisitos:
- [XAMPP](https://www.apachefriends.org/) (com PHP 8.0+ e MySQL ativos).

### Passo a Passo:

1. **Clonar o Repositório:**
   ```bash
   git clone https://github.com/SEU_USUARIO/NOME_DO_REPOSITORIO.git
   ```
2. **Copiar para a pasta do Apache:**
   Coloque a pasta do projeto dentro de `c:/xampp/htdocs/` (ex: `c:/xampp/htdocs/mvm/`).

3. **Configurar o Banco de Dados:**
   - Abra o `phpMyAdmin` (`http://localhost/phpmyadmin/`).
   - Crie uma base de dados chamada `bdmvm` com collation `utf8mb4_general_ci`.
   - Clique em **Importar** e selecione o arquivo:
     ```
     sql/bdmvm_backup_completo.sql
     ```
   - Execute a importação.

4. **Acessar o Sistema:**
   - Acesse no navegador: `http://localhost/mvm/` (ou caminho equivalente configurado).
   - Efetue o login ou utilize o formulário de cadastro de operador.

---

## 👥 Autores & Créditos

- **Projeto de TCC:** MVM Estoque & Logística
- **Desenvolvimento:** Vitor Bazzo & Equipe
- **Orientação:** Curso Técnico / Superior em Desenvolvimento de Sistemas
