<?php
// 1. Silencia notices e warnings de permissão de pasta temporária do PHP
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', 0);

// 2. Define o fuso horário oficial de Brasília
date_default_timezone_set('America/Sao_Paulo');

// 3. Inicia a sessão com o operador de supressão @
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}
require_once __DIR__ . '/config/conexao.php';

// Sincroniza fuso horário no MySQL
if (isset($pdo)) {
    try {
        $pdo->exec("SET time_zone = '-03:00'");
    } catch (Throwable $e) {}
}

/*
|--------------------------------------------------------------------------
| VALIDAÇÃO DE E-MAIL VIA TOKEN DIRETO (SE HOUVER NA URL)
|--------------------------------------------------------------------------
*/
if (isset($_GET['validar_token']) && !empty($_GET['validar_token'])) {
    $token = trim($_GET['validar_token']);
    try {
        $stmtVal = $pdo->prepare("SELECT id, token_expira FROM usuarios WHERE token_verificacao = ? LIMIT 1");
        $stmtVal->execute([$token]);
        $userVal = $stmtVal->fetch(PDO::FETCH_ASSOC);

        if ($userVal) {
            if (!empty($userVal['token_expira']) && strtotime($userVal['token_expira']) < time()) {
                header("Location: login.php?erro=token_expirado");
                exit;
            }
            $upVal = $pdo->prepare("UPDATE usuarios SET email_verificado = 1, token_verificacao = NULL, token_expira = NULL WHERE id = ?");
            $upVal->execute([$userVal['id']]);
            header("Location: login.php?msg=email_confirmado");
            exit;
        } else {
            header("Location: login.php?erro=token_invalido");
            exit;
        }
    } catch (Throwable $e) {
        header("Location: login.php?erro=falha_validacao");
        exit;
    }
}

/*
|--------------------------------------------------------------------------
| 1. SE NÃO ESTIVER LOGADO -> EXIBE LANDING PAGE
|--------------------------------------------------------------------------
*/
if (!isset($_SESSION['usuario_id']) || empty($_SESSION['usuario_id'])) {
    if (isset($_GET['page']) && !empty($_GET['page'])) {
        header("Location: login.php");
        exit;
    }
    ?>
    <!DOCTYPE html>
    <html lang="pt-BR" class="scroll-smooth">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">

        <!-- PWA E MOBILE CONFIGURAÇÕES -->
        <link rel="manifest" href="/manifest.json">
        <meta name="theme-color" content="#09090b">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
        <meta name="apple-mobile-web-app-title" content="Vende+">
        <link rel="apple-touch-icon" href="/assets/img/icon-192.png">

        <!-- FAVICON DA ABA DO NAVEGADOR -->
        <link rel="icon" type="image/svg+xml" href="/assets/img/favicon.svg">

        <!-- SEO BÁSICO -->
        <title>Vende+ | Gestão Financeira e Controle de Vendas</title>
        <meta name="description" content="Controle vendas, estoque e lucro real em um só lugar. Teste grátis por 7 dias.">

        <!-- OPEN GRAPH / FACEBOOK -->
        <meta property="og:site_name" content="Vende+">
        <meta property="og:type" content="website">
        <meta property="og:url" content="https://appvendemais.com.br/">
        <meta property="og:title" content="Vende+ | Gestão Financeira e Controle de Vendas">
        <meta property="og:description" content="Controle vendas, estoque e lucro real em um só lugar. Teste grátis por 7 dias.">
        <meta property="og:image" content="https://i.postimg.cc/7PVZvC6q/og-image.png">
        <meta property="og:image:secure_url" content="https://i.postimg.cc/7PVZvC6q/og-image.png">
        <meta property="og:image:type" content="image/png">
        <meta property="og:image:width" content="1200">
        <meta property="og:image:height" content="630">

        <!-- FALLBACKS SCHEMA -->
        <meta itemprop="name" content="Vende+ | Gestão Financeira e Controle de Vendas">
        <meta itemprop="description" content="Controle vendas, estoque e lucro real em um só lugar. Teste grátis por 7 dias.">
        <meta itemprop="image" content="https://i.postimg.cc/7PVZvC6q/og-image.png">
        <link rel="image_src" type="image/png" href="https://i.postimg.cc/7PVZvC6q/og-image.png">

        <!-- TWITTER CARD -->
        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:title" content="Vende+ | Gestão Financeira e Controle de Vendas">
        <meta name="twitter:description" content="Controle vendas, estoque e lucro real em um só lugar.">
        <meta name="twitter:image" content="https://i.postimg.cc/7PVZvC6q/og-image.png">

        <!-- ESTILOS E SCRIPTS -->
        <meta http-equiv="Content-Security-Policy" content="upgrade-insecure-requests">
        <script src="https://cdn.tailwindcss.com"></script>
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script>
            tailwind.config = {
                darkMode: 'class',
                theme: {
                    extend: {
                        colors: {
                            brand: {
                                500: '#10b981',
                                600: '#059669',
                            },
                            dark: {
                                950: '#060608',
                                900: '#09090b',
                                800: '#121215',
                                700: '#18181b',
                                600: '#27272a',
                            }
                        }
                    }
                }
            }
        </script>
        <style>
            .glow-emerald { box-shadow: 0 0 60px -15px rgba(16, 185, 129, 0.22); }
        </style>
    </head>
    <body class="bg-black text-slate-100 font-sans antialiased selection:bg-emerald-500 selection:text-black overflow-x-hidden">

        <img src="https://i.postimg.cc/7PVZvC6q/og-image.png" alt="Vende+" style="display:none; width:0; height:0; position:absolute;" />

        <!-- NAVBAR -->
        <header class="fixed top-0 left-0 right-0 z-50 bg-black/80 backdrop-blur-md border-b border-zinc-800/80">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
                <div class="flex items-center space-x-3">
                    <a href="index.php" class="flex items-center">
                        <img src="/assets/img/logo.svg" alt="Vende+" class="h-8 w-auto object-contain">
                    </a>
                </div>
                
                <nav class="hidden md:flex items-center space-x-8 text-sm font-medium text-zinc-400">
                    <a href="#recursos" class="hover:text-emerald-400 transition-colors">Recursos</a>
                    <a href="#demonstracao" class="hover:text-emerald-400 transition-colors">Demonstração</a>
                    <a href="#planos" class="hover:text-emerald-400 transition-colors">Planos</a>
                    <a href="#faq" class="hover:text-emerald-400 transition-colors">Dúvidas</a>
                </nav>

                <div class="flex items-center space-x-3 sm:space-x-4">
                    <a href="login.php" class="text-xs sm:text-sm font-medium text-zinc-300 hover:text-white transition-colors">Entrar</a>
                    <a href="cadastro.php" class="text-xs sm:text-sm font-semibold bg-emerald-500 hover:bg-emerald-400 text-black px-3.5 sm:px-4 py-2 rounded-lg transition-all shadow-lg shadow-emerald-500/20 whitespace-nowrap">
                        Testar 7 Dias Grátis
                    </a>
                </div>
            </div>
        </header>

        <!-- HERO SECTION -->
        <section class="relative pt-28 pb-12 md:pt-44 md:pb-20 overflow-hidden">
            <div class="absolute top-1/4 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[340px] sm:w-[600px] h-[200px] sm:h-[300px] bg-emerald-500/15 blur-[100px] sm:blur-[120px] pointer-events-none rounded-full"></div>
            
            <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 text-center relative z-10">
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full border border-emerald-500/30 bg-emerald-500/10 text-emerald-400 text-[11px] sm:text-xs font-semibold uppercase tracking-wider mb-6">
                    <span>Teste gratuito por 7 dias • Sem cartão de crédito</span>
                </div>
                <h1 class="text-3xl sm:text-6xl lg:text-7xl font-extrabold tracking-tight text-white mb-4 sm:mb-6 leading-tight">
                    Controle vendas, estoque e <span class="text-transparent bg-clip-text bg-gradient-to-r from-emerald-400 to-teal-200">lucro real</span> em um só lugar.
                </h1>
                <p class="text-sm sm:text-xl text-zinc-400 max-w-2xl mx-auto mb-8 sm:mb-10 leading-relaxed">
                    Pare de perder tempo com planilhas confusas. O Vende+ entrega clareza sobre o faturamento, custos e crescimento do seu negócio em tempo real.
                </p>
                <div class="flex flex-col sm:flex-row items-center justify-center gap-3.5 sm:gap-4">
                    <a href="cadastro.php" class="w-full sm:w-auto text-sm sm:text-base font-bold bg-emerald-500 hover:bg-emerald-400 text-black px-8 py-3.5 rounded-xl transition-all shadow-xl shadow-emerald-500/20 text-center">
                        Criar Conta e Começar Grátis
                    </a>
                    <a href="#demonstracao" class="w-full sm:w-auto text-sm sm:text-base font-semibold bg-zinc-900 hover:bg-zinc-800 text-zinc-200 border border-zinc-700 px-8 py-3.5 rounded-xl transition-all text-center">
                        Ver Demonstração
                    </a>
                </div>
            </div>
        </section>

        <!-- DEMONSTRAÇÃO INTERATIVA DO PAINEL -->
        <section id="demonstracao" class="pb-16 relative">
            <div class="max-w-5xl mx-auto px-3 sm:px-6 lg:px-8">
                
                <div class="bg-dark-900 border border-zinc-800 rounded-2xl overflow-hidden shadow-2xl glow-emerald">
                    
                    <div class="flex items-center justify-between px-4 sm:px-6 py-3.5 sm:py-4 border-b border-zinc-800 bg-dark-950">
                        <div class="flex items-center space-x-2">
                            <div class="w-2.5 sm:w-3 h-2.5 sm:h-3 rounded-full bg-red-500/80"></div>
                            <div class="w-2.5 sm:w-3 h-2.5 sm:h-3 rounded-full bg-yellow-500/80"></div>
                            <div class="w-2.5 sm:w-3 h-2.5 sm:h-3 rounded-full bg-emerald-500/80"></div>
                            <span class="text-[11px] sm:text-xs text-zinc-500 ml-2 sm:ml-3 font-mono truncate max-w-[140px] sm:max-w-none">painel.vendemais.com.br</span>
                        </div>
                        <span class="text-[11px] sm:text-xs font-semibold px-2.5 py-1 bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 rounded-md">Simulação ao Vivo</span>
                    </div>

                    <div class="p-4 sm:p-8 bg-dark-900">
                        
                        <div class="flex justify-between items-center mb-5 sm:mb-6">
                            <div>
                                <h2 class="text-base sm:text-xl font-bold text-white">Visão Geral</h2>
                                <p class="text-[11px] sm:text-xs text-zinc-400">Acompanhamento financeiro em tempo real</p>
                            </div>
                            <div class="text-[11px] sm:text-xs font-medium bg-zinc-800 text-zinc-300 border border-zinc-700 px-2.5 sm:px-3 py-1.5 rounded-lg">
                                Mês Atual
                            </div>
                        </div>

                        <!-- CARDS DE MÉTRICAS -->
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3.5 sm:gap-4 mb-5 sm:mb-6">
                            
                            <div class="bg-dark-800 border border-zinc-800/90 rounded-xl p-4 sm:p-5 relative">
                                <p class="text-xs font-medium text-zinc-400 mb-1">Faturamento Bruto</p>
                                <p class="text-xl sm:text-2xl font-bold text-white tracking-tight">R$ 14.850,00</p>
                                <div class="mt-1.5 sm:mt-2 text-xs text-emerald-400 font-medium">
                                    ↑ +18% este mês
                                </div>
                            </div>

                            <div class="bg-dark-800 border border-zinc-800/90 rounded-xl p-4 sm:p-5 relative">
                                <p class="text-xs font-medium text-zinc-400 mb-1">Despesas Operacionais</p>
                                <p class="text-xl sm:text-2xl font-bold text-zinc-200 tracking-tight">R$ 3.420,00</p>
                                <div class="mt-1.5 sm:mt-2 text-xs text-zinc-500">
                                    Controle total de custos
                                </div>
                            </div>

                            <div class="bg-dark-800 border border-emerald-500/40 rounded-xl p-4 sm:p-5 relative overflow-hidden">
                                <div class="absolute -right-6 -bottom-6 w-24 h-24 bg-emerald-500/10 rounded-full blur-xl"></div>
                                <p class="text-xs font-semibold text-emerald-400 mb-1">Lucro Líquido Real</p>
                                <p class="text-xl sm:text-2xl font-bold text-emerald-400 tracking-tight">R$ 11.430,00</p>
                                <div class="mt-1.5 sm:mt-2 text-xs text-emerald-500 font-medium">
                                    Margem de 77%
                                </div>
                            </div>
                        </div>

                        <!-- GRÁFICO COMPARATIVO MENSAL -->
                        <div class="bg-dark-800 border border-zinc-800/90 rounded-xl p-4 sm:p-5 mb-5 sm:mb-6">
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 mb-4">
                                <div>
                                    <h3 class="text-xs sm:text-sm font-bold text-white">Comparativo Financeiro Mensal</h3>
                                    <p class="text-[10px] sm:text-[11px] text-zinc-400">Entradas vs. Saídas operacionais</p>
                                </div>
                                <div class="flex items-center gap-3 sm:gap-4 text-xs">
                                    <div class="flex items-center gap-1.5">
                                        <span class="w-2.5 h-2.5 rounded-sm bg-emerald-500"></span>
                                        <span class="text-zinc-300 text-[11px] sm:text-xs">Faturamento</span>
                                    </div>
                                    <div class="flex items-center gap-1.5">
                                        <span class="w-2.5 h-2.5 rounded-sm bg-rose-500"></span>
                                        <span class="text-zinc-300 text-[11px] sm:text-xs">Despesas</span>
                                    </div>
                                </div>
                            </div>
                            <div class="relative h-52 sm:h-60 w-full">
                                <canvas id="graficoDemo"></canvas>
                            </div>
                        </div>

                        <!-- TABELA DE VENDAS -->
                        <div class="bg-dark-800 border border-zinc-800/90 rounded-xl p-4 sm:p-5 overflow-hidden">
                            <div class="flex justify-between items-center mb-3 sm:mb-4">
                                <h3 class="text-[11px] sm:text-xs font-bold text-zinc-400 uppercase tracking-wider">Últimas Vendas Registradas</h3>
                                <span class="text-[11px] sm:text-xs text-zinc-500 font-medium">3 vendas recentes</span>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="w-full text-left text-sm min-w-[400px] sm:min-w-full">
                                    <thead>
                                        <tr class="text-zinc-500 text-xs border-b border-zinc-800">
                                            <th class="pb-2.5 font-medium">Produto</th>
                                            <th class="pb-2.5 font-medium">Qtd</th>
                                            <th class="pb-2.5 font-medium">Canal</th>
                                            <th class="pb-2.5 font-medium text-right">Total</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-zinc-800/60 text-zinc-300 text-xs">
                                        <tr>
                                            <td class="py-3 font-semibold text-white">Fone Bluetooth Pro Max</td>
                                            <td class="py-3 text-zinc-400">2 un.</td>
                                            <td class="py-3">
                                                <span class="px-2.5 py-1 rounded-md bg-zinc-700/60 border border-zinc-600/40 text-zinc-300 text-[11px] font-medium">Mercado Livre</span>
                                            </td>
                                            <td class="py-3 text-right font-bold text-emerald-400">R$ 380,00</td>
                                        </tr>
                                        <tr>
                                            <td class="py-3 font-semibold text-white">Carregador Turbo 30W</td>
                                            <td class="py-3 text-zinc-400">1 un.</td>
                                            <td class="py-3">
                                                <span class="px-2.5 py-1 rounded-md bg-zinc-700/60 border border-zinc-600/40 text-zinc-300 text-[11px] font-medium">Balcão / Loja</span>
                                            </td>
                                            <td class="py-3 text-right font-bold text-emerald-400">R$ 95,00</td>
                                        </tr>
                                        <tr>
                                            <td class="py-3 font-semibold text-white">Smartwatch Sport Pulse</td>
                                            <td class="py-3 text-zinc-400">1 un.</td>
                                            <td class="py-3">
                                                <span class="px-2.5 py-1 rounded-md bg-zinc-700/60 border border-zinc-600/40 text-zinc-300 text-[11px] font-medium">WhatsApp</span>
                                            </td>
                                            <td class="py-3 text-right font-bold text-emerald-400">R$ 220,00</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                    </div>

                </div>
            </div>
        </section>

        <!-- SCRIPT DO GRÁFICO (Chart.js) -->
        <script>
            document.addEventListener("DOMContentLoaded", function () {
                const ctx = document.getElementById('graficoDemo').getContext('2d');

                new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: ['Semana 1', 'Semana 2', 'Semana 3', 'Semana 4'],
                        datasets: [
                            {
                                label: 'Faturamento Bruto',
                                data: [3100, 3950, 4200, 3600],
                                backgroundColor: '#10b981',
                                borderRadius: 6,
                                barPercentage: 0.55,
                                categoryPercentage: 0.7
                            },
                            {
                                label: 'Despesas',
                                data: [850, 920, 950, 700],
                                backgroundColor: '#ef4444',
                                borderRadius: 6,
                                barPercentage: 0.55,
                                categoryPercentage: 0.7
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                backgroundColor: '#18181b',
                                borderColor: '#27272a',
                                borderWidth: 1,
                                titleColor: '#ffffff',
                                bodyColor: '#cbd5e1',
                                callbacks: {
                                    label: function(context) {
                                        return ' ' + context.dataset.label + ': R$ ' + context.parsed.y.toLocaleString('pt-BR', { minimumFractionDigits: 2 });
                                    }
                                }
                            }
                        },
                        scales: {
                            x: {
                                grid: { display: false },
                                ticks: { color: '#71717a', font: { size: 11 } }
                            },
                            y: {
                                grid: { color: 'rgba(255, 255, 255, 0.05)' },
                                ticks: {
                                    color: '#71717a',
                                    font: { size: 11 },
                                    callback: function(value) { return 'R$ ' + value; }
                                }
                            }
                        }
                    }
                });
            });
        </script>

        <!-- FAIXA DE BENEFÍCIOS -->
        <section class="border-y border-zinc-800/80 bg-zinc-950/70 py-7">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="grid grid-cols-2 md:grid-cols-4 gap-6 items-center justify-center text-center">
                    
                    <div class="flex items-center justify-center gap-3">
                        <div class="p-2 rounded-lg bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                            </svg>
                        </div>
                        <span class="text-sm font-medium text-zinc-300">Dados protegidos</span>
                    </div>

                    <div class="flex items-center justify-center gap-3">
                        <div class="p-2 rounded-lg bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4" />
                            </svg>
                        </div>
                        <span class="text-sm font-medium text-zinc-300">Backup automático</span>
                    </div>

                    <div class="flex items-center justify-center gap-3">
                        <div class="p-2 rounded-lg bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 15a4 4 0 004 4h9a5 5 0 10-.1-9.999 5.002 5.002 0 00-9.78 2.096A4.001 4.001 0 003 15z" />
                            </svg>
                        </div>
                        <span class="text-sm font-medium text-zinc-300">Sistema 100% Online</span>
                    </div>

                    <div class="flex items-center justify-center gap-3">
                        <div class="p-2 rounded-lg bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                            </svg>
                        </div>
                        <span class="text-sm font-medium text-zinc-300">Atualizações constantes</span>
                    </div>

                </div>
            </div>
        </section>

        <!-- RECURSOS INTERATIVOS -->
        <section id="recursos" class="py-16 sm:py-24 bg-dark-900/40 relative overflow-hidden">
            <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[700px] h-[350px] bg-emerald-500/5 blur-[140px] pointer-events-none rounded-full"></div>

            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
                
                <div class="text-center max-w-3xl mx-auto mb-12 sm:mb-16">
                    <span class="text-emerald-400 text-xs font-bold uppercase tracking-wider">Recursos Completos</span>
                    <h2 class="text-2xl sm:text-4xl font-extrabold text-white mt-2 mb-4">Tudo que você precisa para crescer</h2>
                    <p class="text-zinc-400 text-sm sm:text-base">Ferramentas práticas e intuitivas feitas sob medida para quem não tem tempo a perder com planilhas.</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">

                    <div class="group relative p-6 sm:p-7 rounded-2xl bg-dark-800/80 border border-zinc-800 hover:border-emerald-500/50 transition-all duration-300 hover:-translate-y-1.5 hover:shadow-xl hover:shadow-emerald-500/10 overflow-hidden">
                        <div class="absolute inset-0 bg-gradient-to-b from-emerald-500/10 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300 pointer-events-none"></div>
                        <div class="w-12 h-12 rounded-xl bg-emerald-500/10 text-emerald-400 flex items-center justify-center mb-5 border border-emerald-500/20 group-hover:scale-110 group-hover:bg-emerald-500/20 transition-all duration-300">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                            </svg>
                        </div>
                        <h3 class="text-base sm:text-lg font-bold text-white mb-2 group-hover:text-emerald-300 transition-colors">Painel Financeiro em Tempo Real</h3>
                        <p class="text-zinc-400 text-xs sm:text-sm leading-relaxed">Acompanhe faturamento bruto, custos operacionais e o lucro líquido apurado automaticamente a cada venda.</p>
                    </div>

                    <div class="group relative p-6 sm:p-7 rounded-2xl bg-dark-800/80 border border-zinc-800 hover:border-emerald-500/50 transition-all duration-300 hover:-translate-y-1.5 hover:shadow-xl hover:shadow-emerald-500/10 overflow-hidden">
                        <div class="absolute inset-0 bg-gradient-to-b from-emerald-500/10 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300 pointer-events-none"></div>
                        <div class="w-12 h-12 rounded-xl bg-emerald-500/10 text-emerald-400 flex items-center justify-center mb-5 border border-emerald-500/20 group-hover:scale-110 group-hover:bg-emerald-500/20 transition-all duration-300">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                            </svg>
                        </div>
                        <h3 class="text-base sm:text-lg font-bold text-white mb-2 group-hover:text-emerald-300 transition-colors">Controle Inteligente de Estoque</h3>
                        <p class="text-zinc-400 text-xs sm:text-sm leading-relaxed">Baixa automática no inventário e avisos visuais imediatos quando seus produtos atingirem o limite mínimo.</p>
                    </div>

                    <div class="group relative p-6 sm:p-7 rounded-2xl bg-dark-800/80 border border-zinc-800 hover:border-emerald-500/50 transition-all duration-300 hover:-translate-y-1.5 hover:shadow-xl hover:shadow-emerald-500/10 overflow-hidden">
                        <div class="absolute inset-0 bg-gradient-to-b from-emerald-500/10 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300 pointer-events-none"></div>
                        <div class="w-12 h-12 rounded-xl bg-emerald-500/10 text-emerald-400 flex items-center justify-center mb-5 border border-emerald-500/20 group-hover:scale-110 group-hover:bg-emerald-500/20 transition-all duration-300">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                            </svg>
                        </div>
                        <h3 class="text-base sm:text-lg font-bold text-white mb-2 group-hover:text-emerald-300 transition-colors">Lançamento Ágil de Pedidos</h3>
                        <p class="text-zinc-400 text-xs sm:text-sm leading-relaxed">Registre vendas em menos de 5 segundos separadas por canais (Balcão, Mercado Livre, Shopee ou WhatsApp).</p>
                    </div>

                    <div class="group relative p-6 sm:p-7 rounded-2xl bg-dark-800/80 border border-zinc-800 hover:border-emerald-500/50 transition-all duration-300 hover:-translate-y-1.5 hover:shadow-xl hover:shadow-emerald-500/10 overflow-hidden">
                        <div class="absolute inset-0 bg-gradient-to-b from-emerald-500/10 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300 pointer-events-none"></div>
                        <div class="w-12 h-12 rounded-xl bg-emerald-500/10 text-emerald-400 flex items-center justify-center mb-5 border border-emerald-500/20 group-hover:scale-110 group-hover:bg-emerald-500/20 transition-all duration-300">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <h3 class="text-base sm:text-lg font-bold text-white mb-2 group-hover:text-emerald-300 transition-colors">Controle Rigoroso de Despesas</h3>
                        <p class="text-zinc-400 text-xs sm:text-sm leading-relaxed">Classifique saídas fixas e variáveis para descobrir exatamente onde cada centavo do seu negócio está sendo gasto.</p>
                    </div>

                    <div class="group relative p-6 sm:p-7 rounded-2xl bg-dark-800/80 border border-zinc-800 hover:border-emerald-500/50 transition-all duration-300 hover:-translate-y-1.5 hover:shadow-xl hover:shadow-emerald-500/10 overflow-hidden">
                        <div class="absolute inset-0 bg-gradient-to-b from-emerald-500/10 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300 pointer-events-none"></div>
                        <div class="w-12 h-12 rounded-xl bg-emerald-500/10 text-emerald-400 flex items-center justify-center mb-5 border border-emerald-500/20 group-hover:scale-110 group-hover:bg-emerald-500/20 transition-all duration-300">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138z" />
                            </svg>
                        </div>
                        <h3 class="text-base sm:text-lg font-bold text-white mb-2 group-hover:text-emerald-300 transition-colors">Cálculo Preciso de Markup</h3>
                        <p class="text-zinc-400 text-xs sm:text-sm leading-relaxed">Defina preços sugeridos comparando preço de custo vs. preço final e garanta margens líquidas saudáveis.</p>
                    </div>

                    <div class="group relative p-6 sm:p-7 rounded-2xl bg-dark-800/80 border border-zinc-800 hover:border-emerald-500/50 transition-all duration-300 hover:-translate-y-1.5 hover:shadow-xl hover:shadow-emerald-500/10 overflow-hidden">
                        <div class="absolute inset-0 bg-gradient-to-b from-emerald-500/10 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300 pointer-events-none"></div>
                        <div class="w-12 h-12 rounded-xl bg-emerald-500/10 text-emerald-400 flex items-center justify-center mb-5 border border-emerald-500/20 group-hover:scale-110 group-hover:bg-emerald-500/20 transition-all duration-300">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z" />
                            </svg>
                        </div>
                        <h3 class="text-base sm:text-lg font-bold text-white mb-2 group-hover:text-emerald-300 transition-colors">Acesse no Celular ou PC</h3>
                        <p class="text-zinc-400 text-xs sm:text-sm leading-relaxed">Design 100% responsivo. Instale como aplicativo direto na tela de início do seu smartphone ou navegador.</p>
                    </div>

                </div>
            </div>
        </section>

        <!-- PLANOS -->
        <section id="planos" class="py-16 sm:py-24 relative border-t border-zinc-800/80">
            <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center max-w-3xl mx-auto mb-12 sm:mb-16">
                    <span class="text-emerald-400 text-xs font-bold uppercase tracking-wider">Planos e Preços</span>
                    <h2 class="text-2xl sm:text-4xl font-extrabold text-white mt-2 mb-4">Comece com 7 dias grátis</h2>
                    <p class="text-zinc-400 text-sm sm:text-base">Crie sua conta sem compromisso e assine apenas quando comprovar os resultados.</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 sm:gap-8 items-center">
                    <div class="p-6 sm:p-8 rounded-2xl bg-dark-800 border border-zinc-800 flex flex-col justify-between h-full">
                        <div>
                            <h3 class="text-lg sm:text-xl font-bold text-white mb-2">Mensal</h3>
                            <p class="text-zinc-400 text-xs sm:text-sm mb-6">Flexibilidade para quem está no início.</p>
                            <div class="flex items-baseline mb-6">
                                <span class="text-3xl sm:text-4xl font-extrabold text-white">R$ 29,90</span>
                                <span class="text-zinc-400 text-xs sm:text-sm ml-2">/mês</span>
                            </div>
                            <ul class="space-y-3 text-xs sm:text-sm text-zinc-300 mb-8">
                                <li class="flex items-center gap-2">✓ Cadastro ilimitado de produtos</li>
                                <li class="flex items-center gap-2">✓ Registro de vendas e despesas</li>
                                <li class="flex items-center gap-2">✓ Painel de lucro apurado ao vivo</li>
                            </ul>
                        </div>
                        <a href="cadastro.php" class="w-full text-center text-xs sm:text-sm font-bold bg-zinc-700 hover:bg-zinc-600 text-white py-3 rounded-xl transition-all block">
                            Testar 7 Dias Grátis
                        </a>
                    </div>

                    <div class="p-6 sm:p-8 rounded-2xl bg-dark-900 border-2 border-emerald-500 relative shadow-2xl glow-emerald flex flex-col justify-between h-full">
                        <div class="absolute -top-3.5 right-6 bg-emerald-500 text-black text-xs font-extrabold px-3 py-1 rounded-full uppercase tracking-wide">
                            Mais Escolhido
                        </div>
                        <div>
                            <h3 class="text-lg sm:text-xl font-bold text-white mb-2">Anual Pro</h3>
                            <p class="text-zinc-400 text-xs sm:text-sm mb-6">Economia de mais de 30% no ano.</p>
                            <div class="flex items-baseline mb-6">
                                <span class="text-3xl sm:text-4xl font-extrabold text-emerald-400">R$ 19,90</span>
                                <span class="text-zinc-400 text-xs sm:text-sm ml-2">/mês (R$ 238,80/ano)</span>
                            </div>
                            <ul class="space-y-3 text-xs sm:text-sm text-zinc-200 mb-8">
                                <li class="flex items-center gap-2 text-emerald-400">✓ Todos os recursos liberados</li>
                                <li class="flex items-center gap-2 text-emerald-400">✓ Suporte prioritário via WhatsApp</li>
                                <li class="flex items-center gap-2 text-emerald-400">✓ 7 dias de garantia incondicional</li>
                            </ul>
                        </div>
                        <a href="cadastro.php" class="w-full text-center text-xs sm:text-sm font-bold bg-emerald-500 hover:bg-emerald-400 text-black py-3.5 rounded-xl transition-all shadow-lg shadow-emerald-500/25 block">
                            Começar 7 Dias Grátis
                        </a>
                    </div>
                </div>
            </div>
        </section>

        <!-- FAQ -->
        <section id="faq" class="py-16 sm:py-20 border-t border-zinc-800/80 bg-dark-900/30">
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
                <h2 class="text-2xl sm:text-3xl font-extrabold text-white text-center mb-8 sm:mb-12">Perguntas Frequentes</h2>
                <div class="space-y-3 sm:space-y-4">
                    <details class="group bg-dark-800 p-4 sm:p-5 rounded-xl border border-zinc-800 cursor-pointer">
                        <summary class="font-semibold text-xs sm:text-sm text-white flex justify-between items-center list-none">
                            Preciso colocar cartão de crédito no teste de 7 dias?
                            <span class="transition group-open:rotate-180">▾</span>
                        </summary>
                        <p class="text-zinc-400 text-xs sm:text-sm mt-3 leading-relaxed">
                            Não! Basta se cadastrar informando seu e-mail e o nome da sua empresa. Você usa todas as funções gratuitamente durante 7 dias.
                        </p>
                    </details>

                    <details class="group bg-dark-800 p-4 sm:p-5 rounded-xl border border-zinc-800 cursor-pointer">
                        <summary class="font-semibold text-xs sm:text-sm text-white flex justify-between items-center list-none">
                            O que acontece após o período de 7 dias?
                            <span class="transition group-open:rotate-180">▾</span>
                        </summary>
                        <p class="text-zinc-400 text-xs sm:text-sm mt-3 leading-relaxed">
                            Seus dados continuam salvos com total segurança. Para continuar cadastrando e gerenciando, basta escolher um dos nossos planos via Pix ou Cartão.
                        </p>
                    </details>
                </div>
            </div>
        </section>

        <footer class="py-8 sm:py-10 border-t border-zinc-800 text-center text-xs text-zinc-500">
            <p>© <?= date('Y') ?> Vende+. Todos os direitos reservados.</p>
        </footer>

        <!-- REGISTRO DO SERVICE WORKER PWA -->
        <script>
            if ('serviceWorker' in navigator) {
                window.addEventListener('load', () => {
                    navigator.serviceWorker.register('/sw.js')
                        .then((reg) => console.log('PWA Service Worker registrado!', reg))
                        .catch((err) => console.log('Erro ao registrar Service Worker:', err));
                });
            }
        </script>
    </body>
    </html>
    <?php
    exit;
}

/*
|--------------------------------------------------------------------------
| 2. VERIFICAÇÃO DO STATUS DA ASSINATURA / TRIAL (USUÁRIO LOGADO)
|--------------------------------------------------------------------------
*/
$stmtStatus = $pdo->prepare("
    SELECT status_assinatura, trial_expira_em, NOW() AS agora 
    FROM empresas 
    WHERE id = ? 
    LIMIT 1
");
$stmtStatus->execute([$_SESSION['empresa_id']]);
$empresaStatus = $stmtStatus->fetch(PDO::FETCH_ASSOC);

$statusAssinatura = $empresaStatus['status_assinatura'] ?? 'trial';
$trialExpiraEm = $empresaStatus['trial_expira_em'] ?? null;
$agora = $empresaStatus['agora'] ?? date('Y-m-d H:i:s');

$bloqueado = false;
if ($statusAssinatura === 'trial' && $trialExpiraEm && $agora > $trialExpiraEm) {
    $bloqueado = true;
} elseif ($statusAssinatura === 'bloqueado' || $statusAssinatura === 'cancelado') {
    $bloqueado = true;
}

if ($bloqueado) {
    ?>
    <!DOCTYPE html>
    <html lang="pt-BR">
    <head>
        <meta charset="UTF-8">
        <meta http-equiv="Content-Security-Policy" content="upgrade-insecure-requests">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <link rel="icon" type="image/svg+xml" href="/assets/img/favicon.svg">
        <title>Período de Testes Encerrado | Vende+</title>
        <script src="https://cdn.tailwindcss.com"></script>
    </head>
    <body class="bg-black text-white min-h-screen flex items-center justify-center p-4">
        <div class="max-w-md w-full bg-zinc-900 border border-zinc-800 rounded-2xl p-8 text-center shadow-2xl">
            <div class="w-16 h-16 bg-emerald-500/10 text-emerald-400 rounded-2xl flex items-center justify-center text-3xl mx-auto mb-5 border border-emerald-500/20">
                ⏳
            </div>
            <h1 class="text-2xl font-extrabold mb-2">Seus 7 dias grátis terminaram</h1>
            <p class="text-zinc-400 text-sm mb-6 leading-relaxed">
                Seu histórico e produtos estão preservados! Para continuar operando o sistema, ative sua assinatura:
            </p>
            <div class="space-y-3">
                <a href="https://cakto.com.br/link-seu-checkout-mensal" target="_blank" class="block w-full py-3.5 bg-emerald-500 hover:bg-emerald-400 text-black font-bold rounded-xl transition-all text-sm shadow-lg shadow-emerald-500/20">
                    Ativar Assinatura Mensal (R$ 29,90)
                </a>
                <a href="https://cakto.com.br/link-seu-checkout-anual" target="_blank" class="block w-full py-3 bg-zinc-800 hover:bg-zinc-700 text-emerald-400 font-semibold rounded-xl border border-zinc-700 transition-all text-xs">
                    Plano Anual com Desconto (R$ 19,90/mês)
                </a>
                <a href="logout.php" class="block w-full pt-3 text-zinc-500 hover:text-zinc-300 text-xs font-medium transition-colors">
                    Sair da Conta
                </a>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

/*
|--------------------------------------------------------------------------
| 3. ROTEAMENTO DAS PÁGINAS DO PAINEL (USUÁRIO ATIVO)
|--------------------------------------------------------------------------
*/
$pagina = $_GET['page'] ?? 'dashboard';
$page = $pagina;

$paginas_permitidas = [
    'dashboard'  => 'views/dashboard.php',
    'produtos'   => 'views/produtos.php',
    'vendas'     => 'views/vendas.php',
    'a-receber'  => 'views/a-receber.php',
    'compras'    => 'views/compras.php',
    'despesas'   => 'views/despesas.php',
    'perfil'     => 'views/perfil.php',
];

$arquivo_relativo = $paginas_permitidas[$pagina] ?? 'views/dashboard.php';
$arquivo_completo = __DIR__ . '/' . $arquivo_relativo;

if (!file_exists($arquivo_completo)) {
    $arquivo_completo = __DIR__ . '/views/dashboard.php';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Security-Policy" content="upgrade-insecure-requests">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">

    <!-- PWA E MOBILE CONFIGURAÇÕES -->
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#09090b">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Vende+">
    <link rel="apple-touch-icon" href="/assets/img/icon-192.png">

    <!-- FAVICON NA ABA DO PAINEL -->
    <link rel="icon" type="image/svg+xml" href="/assets/img/favicon.svg">
    <title>Painel | Vende+</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="/assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-black text-slate-100 min-h-screen flex flex-col md:flex-row antialiased selection:bg-emerald-500 selection:text-black overflow-x-hidden">

    <?php 
    if (file_exists(__DIR__ . '/includes/sidebar.php')) {
        include __DIR__ . '/includes/sidebar.php';
    }
    ?>

    <main class="flex-1 w-full max-w-full p-4 sm:p-6 md:p-8 overflow-y-auto overflow-x-hidden">
        <?php require_once $arquivo_completo; ?>
    </main>

    <!-- REGISTRO DO SERVICE WORKER PWA -->
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('/sw.js')
                    .then((reg) => console.log('PWA Service Worker registrado com sucesso!', reg))
                    .catch((err) => console.log('Erro ao registrar Service Worker:', err));
            });
        }
    </script>
</body>
</html>