# 🚀 Automação de Atividades Google Classroom (Laravel + FilamentPHP)

Este repositório contém uma solução de automação para o Google Classroom, desenvolvida com **Laravel** e **FilamentPHP**. Ela oferece uma interface de usuário mais robusta, escalável e com recursos de gerenciamento de acesso.

## 🎯 Objetivo do Projeto

O principal objetivo desta aplicação é **automatizar o processo de criação e distribuição de atividades personalizadas no Google Classroom** com envio em massa dessas atividades.

A solução permite que o usuário:

1.  **Selecione o conteúdo** (arquivos) a ser anexado à atividade a partir de uma pasta do Google Drive.
2.  **Defina o título e a descrição** da atividade.
3.  **Escolha o tema** (tópico) no Classroom onde a atividade será publicada.
4.  **Publique a atividade** em uma ou mais turmas.
5.  **Personalize a atribuição** da atividade para alunos específicos dentro de cada turma, com base em critérios definidos em uma planilha (como a versão anterior fazia, usando cores ou IDs para exclusão/inclusão).

A principal melhoria em relação à versão anterior (AppScript) é a migração para uma arquitetura **Laravel + FilamentPHP**, que proporciona:

*   **Interface Administrativa Completa:** Utilização do Filament para um painel de controle moderno e fácil de usar.
*   **Escalabilidade e Performance:** Benefícios de um framework PHP robusto para lidar com mais dados e requisições.
*   **Controle de Acesso (ACL):** Gerenciamento de usuários, papéis e permissões usando o pacote `spatie/laravel-permission`, garantindo que apenas usuários autorizados possam realizar as automações.
*   **Login Social:** Integração facilitada com o Google para autenticação, essencial para interagir com as APIs do Google Classroom e Drive.

---

## 🛠️ Configuração e Instalação

Este projeto é baseado em Laravel e utiliza o FilamentPHP para o painel administrativo. As instruções a seguir são adaptadas do repositório de base (`GabrielCapoia-Dev/Automacao-Classroom`) e detalham o que é necessário para colocar a aplicação em funcionamento.

### Pré-requisitos

Certifique-se de que seu ambiente de desenvolvimento atenda aos seguintes requisitos:

*   **PHP:** Versão 8.2 ou superior.
*   **Composer:** Gerenciador de dependências para PHP.
*   **Node.js e NPM/Yarn:** Para compilação de assets do frontend (embora o Filament cuide da maior parte).
*   **Banco de Dados:** Um SGBD compatível com Laravel (MySQL, PostgreSQL, SQLite, etc.).
*   **Google Cloud Project:** Necessário para obter credenciais de API para o Google Classroom e Login Social.

### Passos para Instalação

1.  **Clonar o Repositório:**
    ```bash
    git clone [URL_DO_SEU_REPOSITORIO]
    cd [NOME_DO_SEU_REPOSITORIO]
    ```

2.  **Instalar Dependências PHP:**
    ```bash
    composer install
    ```

3.  **Configurar Variáveis de Ambiente (`.env`):**
    Copie o arquivo de exemplo e gere a chave da aplicação.
    ```bash
    cp .env.example .env
    php artisan key:generate
    ```
    
    Edite o arquivo `.env` para configurar:
    
    *   **Banco de Dados:** `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`.
    *   **URL da Aplicação:** `APP_URL` (ex: `http://localhost:8000`).

4.  **Configuração do Google Cloud (Login Social e APIs):**

    Para interagir com o Google Classroom e permitir o Login Social, você precisará configurar um projeto no [Google Cloud Console](https://console.cloud.google.com/).

    *   **Habilite as APIs:** Certifique-se de que as APIs **Google Classroom API** e **Google Drive API** estejam habilitadas para o seu projeto.
    *   **Crie Credenciais OAuth 2.0:**
        *   Crie uma credencial do tipo "ID do cliente OAuth" para "Aplicativo da Web".
        *   Configure as "Origens JavaScript autorizadas" (ex: `http://localhost:8000`).
        *   Configure os "URIs de redirecionamento autorizados". Adicione a URL de callback para o login social: `[SUA_APP_URL]/oauth/google/callback` (ex: `http://localhost:8000/oauth/google/callback`).
    *   **Adicione as Credenciais ao `.env`:**
        ```dotenv
        # Credenciais para Login Social (Filament Socialite)
        GOOGLE_CLIENT_ID=SEU_CLIENT_ID_AQUI
        GOOGLE_CLIENT_SECRET=SEU_CLIENT_SECRET_AQUI
        GOOGLE_REDIRECT_URI=SUA_URL_DE_REDIRECIONAMENTO_AQUI 
        
        # Credenciais para a API do Google Classroom/Drive (Pode ser necessário configurar um Service Account 
        # ou usar as credenciais do usuário logado, dependendo da implementação final)
        # Se for usar Service Account, adicione as variáveis correspondentes aqui.
        ```

5.  **Executar Migrações e Seeders:**
    Execute as migrações para criar as tabelas e os seeders para popular o banco com dados iniciais (incluindo o usuário administrador e as permissões iniciais do Spatie).
    ```bash
    php artisan migrate:refresh --seed
    ```
    
    *   **Credenciais Padrão:** O seeder provavelmente cria um usuário administrador padrão. Verifique o código do seeder no repositório de base, mas as credenciais comuns são:
        *   **Email:** `admin@admin.com`
        *   **Senha:** `123456`

6.  **Compilar Assets (Opcional, mas recomendado):**
    Embora o Filament gerencie a maioria dos assets, é uma boa prática garantir que tudo esteja compilado.
    ```bash
    npm install
    npm run dev 
    # ou npm run build para produção
    ```

7.  **Iniciar o Servidor:**
    ```bash
    php artisan serve
    ```

### Acesso ao Painel Administrativo

Acesse a URL da sua aplicação seguida de `/admin` (ex: `http://127.0.0.1:8000/admin`) e faça login com as credenciais do usuário administrador ou através do Login Social (Google), se configurado.

---

## 💡 Próximos Passos de Desenvolvimento

O repositório de base fornece a estrutura de ACL. Para completar a solução de automação, os seguintes componentes precisarão ser desenvolvidos:

1.  **Integração com Google Classroom/Drive:**
    *   Implementar a lógica de comunicação com as APIs do Google Classroom e Drive (usando o SDK do Google para PHP).
    *   Criar a interface no Filament para o usuário inserir o link da pasta do Drive e selecionar o tema.
2.  **Lógica de Processamento:**
    *   Recriar a lógica de leitura da planilha (que na versão AppScript definia os alunos individuais) dentro do Laravel. Isso pode ser feito lendo um arquivo CSV/XLSX enviado pelo usuário ou integrando com a Google Sheets API.
    *   Implementar a função de envio de atividade, replicando a funcionalidade de `enviarAtividadeParaTodasTurmas` da versão AppScript.
3.  **Recursos do Filament:**
    *   Criar os *Resources* e *Pages* necessários no Filament para gerenciar a automação (ex: uma página para a interface de envio de atividades).
    *   Utilizar os recursos de *Forms* e *Actions* do Filament para criar uma experiência de usuário intuitiva.
