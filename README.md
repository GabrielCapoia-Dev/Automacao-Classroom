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

A principal melhoria é a migração para uma arquitetura **Laravel + FilamentPHP**, que proporciona:

*   **Interface Administrativa Completa:** Utilização do Filament para um painel de controle moderno e fácil de usar.
*   **Escalabilidade e Performance:** Benefícios de um framework PHP robusto para lidar com mais dados e requisições.
*   **Controle de Acesso (ACL):** Gerenciamento de usuários, papéis e permissões usando o pacote `spatie/laravel-permission`, garantindo que apenas usuários autorizados possam realizar as automações.
*   **Login Social:** Integração facilitada com o Google para autenticação, essencial para interagir com as APIs do Google Classroom e Drive.

---
