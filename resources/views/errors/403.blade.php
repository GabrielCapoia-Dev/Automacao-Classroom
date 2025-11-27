{{-- resources/views/errors/403.blade.php --}}
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>403 — Acesso negado | Transporte Escolar Municipal</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800" rel="stylesheet" />
    <link rel="stylesheet" href="{{ asset('css/403.css') }}">
</head>

<body>
    <header class="header" id="header">
        <div class="container">
            <nav class="nav">
                <div class="logo">
                    <div class="logo-icon">🚌</div>
                    <div class="logo-text">
                        <div class="logo-title">Transporte Escolar</div>
                        <div class="logo-subtitle">Prefeitura de Umuarama</div>
                    </div>
                </div>
                @auth
                    <a href="/admin" class="login-btn"><span>🏠</span> Painel</a>
                @else
                    <a href="/admin/login" class="login-btn"><span>🔐</span> Login</a>
                @endauth
            </nav>
        </div>
    </header>

    <section class="hero">
        <div class="container">
            <div class="hero-grid">
                <div class="hero-content">
                    <div class="code-badge">⚠️ ERRO 403 - ACESSO NEGADO</div>
                    <h1 class="h1">
                        <span class="highlight">Acesso Negado</span><br>
                        Você não tem permissão para esta página
                    </h1>
                    <p class="lead">
                        Infelizmente, você não possui as permissões necessárias para acessar esta área do sistema. 
                        Esta é uma zona restrita do sistema de transporte escolar.
                    </p>

                    <div class="alert-box">
                        <div class="alert-icon">🔒</div>
                        <div class="alert-content">
                            <h3>Por que estou vendo esta mensagem?</h3>
                            <p>
                                Seu usuário não possui as permissões adequadas ou você tentou acessar uma área administrativa. 
                                Se você acredita que deveria ter acesso, entre em contato com o administrador do sistema.
                            </p>
                        </div>
                    </div>

                    <div class="pill">
                        <span class="chip">🚫 Área restrita</span>
                        <span class="chip">🔐 Sem permissão</span>
                        <span class="chip">👤 Usuário limitado</span>
                    </div>

                    <div class="actions">
                        <button class="btn btn-secondary" onclick="history.back()">
                            <span>↩️</span> Voltar à página anterior
                        </button>
                        @auth
                            <a href="/admin" class="btn">
                                <span>🏠</span> Ir para o painel principal
                            </a>
                        @else
                            <a href="/admin/login" class="btn">
                                <span>🔐</span> Fazer login no sistema
                            </a>
                        @endauth
                    </div>
                </div>

                <div class="panel" aria-hidden="true"></div>
            </div>
        </div>
    </section>

    <section class="cta">
        <div class="container">
            <div class="cta-card">
                <div class="cta-title">🆘 Precisa de ajuda?</div>
                <p class="cta-sub">
                    Se você acredita que deveria ter acesso a esta área, entre em contato com o administrador do sistema 
                    ou solicite que suas permissões sejam revisadas. Certifique-se de que seu usuário está vinculado 
                    ao setor ou escola corretos.
                </p>
            </div>
        </div>
    </section>

    <footer class="footer">
        <div class="container">
            <small>&copy; {{ date('Y') }} Prefeitura Municipal de Umuarama — Sistema de Transporte Escolar</small>
        </div>
    </footer>

    <script>
        // Cabeçalho com sombra ao rolar
        window.addEventListener('scroll', () => {
            const h = document.getElementById('header');
            if (!h) return;
            if (window.scrollY > 100) h.classList.add('scrolled');
            else h.classList.remove('scrolled');
        });

        // Melhor UX no botão voltar
        document.addEventListener('DOMContentLoaded', function() {
            const backButton = document.querySelector('.btn-secondary');
            if (backButton && window.history.length <= 1) {
                backButton.style.display = 'none';
            }
        });
    </script>
</body>

</html