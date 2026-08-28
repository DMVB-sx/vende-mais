<?php
// Previne cache do navegador para sempre exibir a versão mais recente
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Se já estiver logado, redireciona para o painel
if (isset($_SESSION['usuario_id']) && !empty($_SESSION['usuario_id'])) {
    header("Location: index.php?page=dashboard");
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>vende+ | Gestão Financeira, Estoque e Lucro Real para Pequenos Negócios</title>
    
    <!-- FAVICON -->
    <link rel="icon" type="image/svg+xml" href="/assets/img/favicon.svg">
    <meta name="description" content="Controle vendas, estoque, compras e crediário em segundos. Descubra seu lucro líquido real sem planilhas ou cadernos. Experimente 7 dias grátis.">

    <!-- Open Graph / Redes Sociais -->
    <meta property="og:type" content="website">
    <meta property="og:title" content="vende+ | Controle total do seu comércio em um só lugar">
    <meta property="og:description" content="Chega de perder dinheiro com fiado e confusão nas contas. Teste o vende+ por 7 dias grátis.">
    <meta property="og:url" content="https://appvendemais.com.br">

    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        brand: {
                            400: '#34d399',
                            500: '#10b981',
                            600: '#059669',
                            700: '#047857',
                        },
                        dark: {
                            950: '#050507',
                            900: '#09090b',
                            850: '#0f0f13',
                            800: '#141418',
                            700: '#1c1c22',
                            600: '#27272a',
                        }
                    },
                    backgroundImage: {
                        'grid-pattern': "radial-gradient(rgba(255, 255, 255, 0.07) 1px, transparent 1px)",
                    },
                    backgroundSize: {
                        'grid-size': '24px 24px',
                    }
                }
            }
        }
    </script>
    
    <!-- LUCIDE ICONS -->
    <script src="https://unpkg.com/lucide@latest"></script>

    <style>
        .glow-emerald {
            box-shadow: 0 0 60px -15px rgba(16, 185, 129, 0.25);
        }
        .glow-subtle {
            box-shadow: 0 10px 40px -10px rgba(0, 0, 0, 0.7);
        }
    </style>
</head>
<!-- Ícone Oficial para iPhone e iPad (iOS) -->
<link rel="apple-touch-icon" sizes="180x180" href="/assets/img/apple-touch-icon.png">
<link rel="apple-touch-icon" href="/assets/img/apple-touch-icon.png">

<!-- Favicon padrão e PWA -->
<link rel="icon" type="image/png" sizes="180x180" href="/assets/img/apple-touch-icon.png">
<link rel="manifest" href="/manifest.json">

<!-- Configurações de exibição no iOS -->
<meta name="apple-mobile-web-app-title" content="Vende+">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="theme-color" content="#09090b">

<body class="bg-dark-950 text-slate-100 font-sans antialiased selection:bg-emerald-500 selection:text-black overflow-x-hidden">

    <!-- BACKGROUND GRID SUTIL -->
    <div class="fixed inset-0 bg-grid-pattern bg-grid-size opacity-40 pointer-events-none z-0"></div>

    <!-- NAVBAR FLUTUANTE -->
    <header class="fixed top-0 left-0 right-0 z-50 bg-dark-950/80 backdrop-blur-md border-b border-zinc-800/80">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
            <a href="index.php" class="flex items-center space-x-2.5 text-decoration-none">
                <svg width="30" height="30" viewBox="0 0 64 64" xmlns="http://www.w3.org/2000/svg" class="flex-shrink-0">
                    <rect width="64" height="64" rx="16" fill="#09090b" stroke="#27272a" stroke-width="2"/>
                    <path d="M14 22 L26 44 L44 16" fill="none" stroke="#ffffff" stroke-width="7" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M52 32 L52 44 M46 38 L58 38" stroke="#10b981" stroke-width="5.5" stroke-linecap="round"/>
                </svg>
                <span class="text-2xl font-black tracking-tight text-white">vende<span class="text-emerald-400">+</span></span>
            </a>
            
            <nav class="hidden md:flex items-center space-x-8 text-sm font-medium text-zinc-400">
                <a href="#problema" class="hover:text-emerald-400 transition-colors">Por que o vende+?</a>
                <a href="#recursos" class="hover:text-emerald-400 transition-colors">Recursos</a>
                <a href="#demonstracao" class="hover:text-emerald-400 transition-colors">Demonstração</a>
                <a href="#planos" class="hover:text-emerald-400 transition-colors">Planos</a>
                <a href="#faq" class="hover:text-emerald-400 transition-colors">Dúvidas</a>
            </nav>

            <div class="flex items-center space-x-3 sm:space-x-4">
                <a href="login.php" class="text-xs sm:text-sm font-semibold text-zinc-300 hover:text-white transition-colors px-3 py-2">Entrar</a>
                <a href="cadastro.php" class="text-xs sm:text-sm font-bold bg-emerald-500 hover:bg-emerald-400 text-black px-4 py-2.5 rounded-xl transition-all shadow-lg shadow-emerald-500/20 inline-flex items-center gap-1.5 no-underline">
                    <span>Testar 7 Dias Grátis</span>
                    <i data-lucide="arrow-right" class="w-4 h-4"></i>
                </a>
            </div>
        </div>
    </header>

    <!-- HERO SECTION -->
    <section class="relative pt-32 pb-16 md:pt-44 md:pb-24 overflow-hidden z-10">
        <!-- Luz de Fundo -->
        <div class="absolute top-1/4 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[650px] h-[320px] bg-emerald-500/15 blur-[130px] pointer-events-none rounded-full"></div>
        
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 text-center relative">
            
            <!-- Badge de Lançamento -->
            <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full border border-emerald-500/30 bg-emerald-500/10 text-emerald-400 text-xs font-bold uppercase tracking-wider mb-6 animate-pulse">
                <i data-lucide="sparkles" class="w-3.5 h-3.5"></i>
                <span>Gestão sem Complicação • 7 Dias Grátis</span>
            </div>
            
            <h1 class="text-4xl sm:text-6xl lg:text-7xl font-black tracking-tight text-white mb-6 leading-[1.1]">
                Saiba exatamente quanto você ganha. <br class="hidden sm:inline">
                <span class="text-transparent bg-clip-text bg-gradient-to-r from-emerald-400 via-teal-300 to-emerald-200">
                    Sem planilhas. Sem caderno.
                </span>
            </h1>
            
            <p class="text-base sm:text-xl text-zinc-400 max-w-2xl mx-auto mb-8 leading-relaxed">
                O <strong>vende+</strong> organiza vendas, estoque, compras e o crediário de quem vende pelo balcão, WhatsApp, Mercado Livre ou Shopee.
            </p>
            
            <div class="flex flex-col sm:flex-row items-center justify-center gap-3.5 mb-5">
                <a href="cadastro.php" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 text-base font-bold bg-emerald-500 hover:bg-emerald-400 text-black px-8 py-4 rounded-xl transition-all shadow-xl shadow-emerald-500/25 no-underline hover:scale-[1.02]">
                    <span>Começar Teste de 7 Dias Grátis</span>
                    <i data-lucide="arrow-right" class="w-5 h-5"></i>
                </a>
                <a href="#demonstracao" class="w-full sm:w-auto text-base font-semibold bg-dark-800 hover:bg-dark-700 text-zinc-200 border border-zinc-700/80 px-7 py-4 rounded-xl transition-all no-underline">
                    Ver Como Funciona
                </a>
            </div>
            
            <div class="flex items-center justify-center gap-4 text-xs text-zinc-500 font-medium">
                <span class="inline-flex items-center gap-1.5">
                    <i data-lucide="check-circle-2" class="w-4 h-4 text-emerald-400"></i> Sem cartão no cadastro
                </span>
                <span>•</span>
                <span class="inline-flex items-center gap-1.5">
                    <i data-lucide="check-circle-2" class="w-4 h-4 text-emerald-400"></i> Acesso liberado na hora
                </span>
                <span>•</span>
                <span class="inline-flex items-center gap-1.5">
                    <i data-lucide="check-circle-2" class="w-4 h-4 text-emerald-400"></i> Cancele quando quiser
                </span>
            </div>
        </div>
    </section>

    <!-- PROVA COMPARATIVA: O FIM DO CADERNO -->
    <section id="problema" class="py-16 bg-dark-900/60 border-y border-zinc-800/80 relative z-10">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center max-w-3xl mx-auto mb-12">
                <span class="text-emerald-400 text-xs font-bold uppercase tracking-wider">CHEGA DE AMADORISMO</span>
                <h2 class="text-2xl sm:text-4xl font-black text-white mt-1 mb-3">Você ainda gerencia seu negócio no escuro?</h2>
                <p class="text-zinc-400 text-sm sm:text-base">Veja a diferença entre continuar anotando no papel versus ter o controle total no vende+.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 items-stretch">
                <!-- O JEITO ANTIGO -->
                <div class="bg-dark-950/80 border border-rose-500/20 rounded-3xl p-6 sm:p-8 flex flex-col justify-between">
                    <div>
                        <div class="flex items-center gap-3 mb-5">
                            <div class="w-10 h-10 rounded-xl bg-rose-500/10 border border-rose-500/20 flex items-center justify-center text-rose-400 font-black">
                                ✕
                            </div>
                            <h3 class="text-lg sm:text-xl font-bold text-white">Caderninho & Planilhas Confusas</h3>
                        </div>
                        <ul class="space-y-3.5 text-xs sm:text-sm text-zinc-400 list-none p-0">
                            <li class="flex items-start gap-2.5">
                                <i data-lucide="x" class="w-4 h-4 text-rose-500 shrink-0 mt-0.5"></i>
                                <span>Você esquece de cobrar clientes que compraram no fiado/a prazo.</span>
                            </li>
                            <li class="flex items-start gap-2.5">
                                <i data-lucide="x" class="w-4 h-4 text-rose-500 shrink-0 mt-0.5"></i>
                                <span>Vende muito, mas no fim do mês não sabe para onde o dinheiro foi.</span>
                            </li>
                            <li class="flex items-start gap-2.5">
                                <i data-lucide="x" class="w-4 h-4 text-rose-500 shrink-0 mt-0.5"></i>
                                <span>Estoque descontrolado: descobre que o produto acabou bem na hora da venda.</span>
                            </li>
                            <li class="flex items-start gap-2.5">
                                <i data-lucide="x" class="w-4 h-4 text-rose-500 shrink-0 mt-0.5"></i>
                                <span>Horas perdidas calculando despesas e somando recibos manualmente.</span>
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- O JEITO VENDE+ -->
                <div class="bg-dark-900 border-2 border-emerald-500/70 rounded-3xl p-6 sm:p-8 flex flex-col justify-between shadow-xl glow-emerald">
                    <div>
                        <div class="flex items-center gap-3 mb-5">
                            <div class="w-10 h-10 rounded-xl bg-emerald-500/10 border border-emerald-500/30 flex items-center justify-center text-emerald-400 font-black">
                                ✓
                            </div>
                            <h3 class="text-lg sm:text-xl font-bold text-white">Com o vende+ no seu dia a dia</h3>
                        </div>
                        <ul class="space-y-3.5 text-xs sm:text-sm text-zinc-200 list-none p-0">
                            <li class="flex items-start gap-2.5">
                                <i data-lucide="check" class="w-4 h-4 text-emerald-400 shrink-0 mt-0.5"></i>
                                <span><strong>Controle de Crediário:</strong> Alertas de quem deve e quanto falta receber.</span>
                            </li>
                            <li class="flex items-start gap-2.5">
                                <i data-lucide="check" class="w-4 h-4 text-emerald-400 shrink-0 mt-0.5"></i>
                                <span><strong>Lucro Líquido Real:</strong> O sistema desconta custos e calcula sua margem real na hora.</span>
                            </li>
                            <li class="flex items-start gap-2.5">
                                <i data-lucide="check" class="w-4 h-4 text-emerald-400 shrink-0 mt-0.5"></i>
                                <span><strong>Baixa Automática no Estoque:</strong> Avisos visuais antes dos produtos esgotarem.</span>
                            </li>
                            <li class="flex items-start gap-2.5">
                                <i data-lucide="check" class="w-4 h-4 text-emerald-400 shrink-0 mt-0.5"></i>
                                <span><strong>Acesse de onde estiver:</strong> No celular, tablet ou computador sem instalar nada.</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- DEMONSTRAÇÃO INTERATIVA DO PAINEL -->
    <section id="demonstracao" class="py-20 relative z-10">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center max-w-3xl mx-auto mb-12">
                <span class="text-emerald-400 text-xs font-bold uppercase tracking-wider">INTERFACE SIMPLES & DIRETA</span>
                <h2 class="text-2xl sm:text-4xl font-black text-white mt-1 mb-3">Tudo que você precisa em uma única tela</h2>
                <p class="text-zinc-400 text-sm sm:text-base">Sem menus confusos ou termos difíceis. Feito para quem precisa de agilidade.</p>
            </div>

            <!-- MOCKUP BROWSER WINDOW -->
            <div class="bg-dark-900 border border-zinc-800 rounded-3xl overflow-hidden shadow-2xl glow-emerald">
                <!-- Barra Superior do Navegador -->
                <div class="flex items-center justify-between px-4 sm:px-6 py-3.5 border-b border-zinc-800 bg-dark-950">
                    <div class="flex items-center space-x-2">
                        <div class="w-3 h-3 rounded-full bg-rose-500/80"></div>
                        <div class="w-3 h-3 rounded-full bg-amber-500/80"></div>
                        <div class="w-3 h-3 rounded-full bg-emerald-500/80"></div>
                        <span class="text-xs text-zinc-500 ml-3 font-mono">appvendemais.com.br/painel</span>
                    </div>
                    <span class="text-xs font-semibold px-2.5 py-1 bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 rounded-md">
                        Simulação em Tempo Real
                    </span>
                </div>

                <div class="p-4 sm:p-8 bg-dark-900">
                    <!-- CARDS DE MÉTRICAS -->
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
                        <div class="bg-dark-800 border border-zinc-800/90 rounded-2xl p-5">
                            <p class="text-xs font-medium text-zinc-400 mb-1">Faturamento Bruto (Mês)</p>
                            <p class="text-2xl font-black text-white">R$ 14.850,00</p>
                            <span class="text-xs text-emerald-400 mt-2 inline-block font-semibold">↑ +18% vs mês anterior</span>
                        </div>
                        <div class="bg-dark-800 border border-zinc-800/90 rounded-2xl p-5">
                            <p class="text-xs font-medium text-zinc-400 mb-1">Despesas & Custos de Compra</p>
                            <p class="text-2xl font-black text-zinc-300">R$ 3.420,00</p>
                            <span class="text-xs text-zinc-500 mt-2 inline-block">100% categorizado</span>
                        </div>
                        <div class="bg-dark-800 border border-emerald-500/40 rounded-2xl p-5 relative overflow-hidden">
                            <div class="absolute -right-4 -bottom-4 w-20 h-20 bg-emerald-500/10 rounded-full blur-xl"></div>
                            <p class="text-xs font-semibold text-emerald-400 mb-1">Lucro Líquido Real</p>
                            <p class="text-2xl font-black text-emerald-400">R$ 11.430,00</p>
                            <span class="text-xs text-emerald-500 mt-2 inline-block font-bold">Margem Líquida de 77%</span>
                        </div>
                    </div>

                    <!-- TABELA DEMO -->
                    <div class="bg-dark-800 border border-zinc-800/90 rounded-2xl p-5 overflow-x-auto">
                        <div class="flex justify-between items-center mb-4">
                            <span class="text-xs font-bold text-zinc-400 uppercase tracking-wider">Últimas Vendas Registradas</span>
                            <span class="text-xs text-zinc-500">Hoje</span>
                        </div>
                        <table class="w-full text-left text-sm min-w-[500px]">
                            <thead>
                                <tr class="text-zinc-500 text-xs border-b border-zinc-800">
                                    <th class="pb-2.5 font-medium">Produto</th>
                                    <th class="pb-2.5 font-medium">Qtd</th>
                                    <th class="pb-2.5 font-medium">Canal</th>
                                    <th class="pb-2.5 font-medium">Pagamento</th>
                                    <th class="pb-2.5 font-medium text-right">Total</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-800/60 text-zinc-300 text-xs">
                                <tr>
                                    <td class="py-3 font-semibold text-white">Fone Bluetooth Pro Max</td>
                                    <td class="py-3 text-zinc-400">2 un.</td>
                                    <td class="py-3"><span class="px-2.5 py-0.5 rounded-md bg-zinc-700/60 text-zinc-300 border border-zinc-600/40 text-[11px]">Mercado Livre</span></td>
                                    <td class="py-3 text-zinc-400">Pix Instantâneo</td>
                                    <td class="py-3 text-right font-bold text-emerald-400">R$ 380,00</td>
                                </tr>
                                <tr>
                                    <td class="py-3 font-semibold text-white">Vestido Midi Floral</td>
                                    <td class="py-3 text-zinc-400">1 un.</td>
                                    <td class="py-3"><span class="px-2.5 py-0.5 rounded-md bg-zinc-700/60 text-zinc-300 border border-zinc-600/40 text-[11px]">WhatsApp</span></td>
                                    <td class="py-3 text-amber-400">Crediário (2x)</td>
                                    <td class="py-3 text-right font-bold text-emerald-400">R$ 180,00</td>
                                </tr>
                                <tr>
                                    <td class="py-3 font-semibold text-white">Carregador Turbo 30W</td>
                                    <td class="py-3 text-zinc-400">1 un.</td>
                                    <td class="py-3"><span class="px-2.5 py-0.5 rounded-md bg-zinc-700/60 text-zinc-300 border border-zinc-600/40 text-[11px]">Balcão / Loja</span></td>
                                    <td class="py-3 text-zinc-400">Cartão de Débito</td>
                                    <td class="py-3 text-right font-bold text-emerald-400">R$ 95,00</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- BENTO GRID DE RECURSOS -->
    <section id="recursos" class="py-20 bg-dark-900/40 border-t border-zinc-800/80 relative z-10">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center max-w-3xl mx-auto mb-16">
                <span class="text-emerald-400 text-xs font-bold uppercase tracking-wider">FERRAMENTAS ESSENCIAIS</span>
                <h2 class="text-3xl sm:text-4xl font-black text-white mt-1 mb-4">Construído para fazer seu negócio lucrar mais</h2>
                <p class="text-zinc-400 text-base">Cada ferramenta foi desenhada para poupar tempo e evitar erros nas suas contas.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                
                <!-- CARD 1 -->
                <div class="p-7 rounded-3xl bg-dark-850 border border-zinc-800 hover:border-emerald-500/40 transition-all group">
                    <div class="w-12 h-12 rounded-2xl bg-emerald-500/10 text-emerald-400 flex items-center justify-center mb-5 border border-emerald-500/20 group-hover:scale-110 transition">
                        <i data-lucide="calculator" class="w-6 h-6"></i>
                    </div>
                    <h3 class="text-lg font-bold text-white mb-2">Cálculo de Lucro Real</h3>
                    <p class="text-zinc-400 text-sm leading-relaxed">
                        Esqueça a dúvida se você está tendo lucro ou prejuízo. O sistema desconta os custos de aquisição e mostra sua margem líquida precisa.
                    </p>
                </div>

                <!-- CARD 2 -->
                <div class="p-7 rounded-3xl bg-dark-850 border border-zinc-800 hover:border-emerald-500/40 transition-all group">
                    <div class="w-12 h-12 rounded-2xl bg-emerald-500/10 text-emerald-400 flex items-center justify-center mb-5 border border-emerald-500/20 group-hover:scale-110 transition">
                        <i data-lucide="wallet-cards" class="w-6 h-6"></i>
                    </div>
                    <h3 class="text-lg font-bold text-white mb-2">Controle de Crediário & Fiado</h3>
                    <p class="text-zinc-400 text-sm leading-relaxed">
                        Saiba exatamente quanto cada cliente te deve, quais parcelas estão atrasadas e envie lembretes rápidos de cobrança pelo WhatsApp.
                    </p>
                </div>

                <!-- CARD 3 -->
                <div class="p-7 rounded-3xl bg-dark-850 border border-zinc-800 hover:border-emerald-500/40 transition-all group">
                    <div class="w-12 h-12 rounded-2xl bg-emerald-500/10 text-emerald-400 flex items-center justify-center mb-5 border border-emerald-500/20 group-hover:scale-110 transition">
                        <i data-lucide="package-search" class="w-6 h-6"></i>
                    </div>
                    <h3 class="text-lg font-bold text-white mb-2">Estoque com Alerta Inteligente</h3>
                    <p class="text-zinc-400 text-sm leading-relaxed">
                        Cadastre variações de produtos, receba baixas automáticas a cada venda e evite surpresas com avisos de produtos acabando.
                    </p>
                </div>

                <!-- CARD 4 -->
                <div class="p-7 rounded-3xl bg-dark-850 border border-zinc-800 hover:border-emerald-500/40 transition-all group">
                    <div class="w-12 h-12 rounded-2xl bg-emerald-500/10 text-emerald-400 flex items-center justify-center mb-5 border border-emerald-500/20 group-hover:scale-110 transition">
                        <i data-lucide="receipt" class="w-6 h-6"></i>
                    </div>
                    <h3 class="text-lg font-bold text-white mb-2">Registro de Compras & Despesas</h3>
                    <p class="text-zinc-400 text-sm leading-relaxed">
                        Lance gastos fixos (aluguel, luz, internet) e variáveis para enxergar onde estão os maiores custos da sua operação.
                    </p>
                </div>

                <!-- CARD 5 -->
                <div class="p-7 rounded-3xl bg-dark-850 border border-zinc-800 hover:border-emerald-500/40 transition-all group">
                    <div class="w-12 h-12 rounded-2xl bg-emerald-500/10 text-emerald-400 flex items-center justify-center mb-5 border border-emerald-500/20 group-hover:scale-110 transition">
                        <i data-lucide="smartphone" class="w-6 h-6"></i>
                    </div>
                    <h3 class="text-lg font-bold text-white mb-2">Instalação Direta no Celular (PWA)</h3>
                    <p class="text-zinc-400 text-sm leading-relaxed">
                        Abra o sistema como um aplicativo nativo na tela inicial do seu celular Android ou iPhone, sem ocupar memória do aparelho.
                    </p>
                </div>

                <!-- CARD 6 -->
                <div class="p-7 rounded-3xl bg-dark-850 border border-zinc-800 hover:border-emerald-500/40 transition-all group">
                    <div class="w-12 h-12 rounded-2xl bg-emerald-500/10 text-emerald-400 flex items-center justify-center mb-5 border border-emerald-500/20 group-hover:scale-110 transition">
                        <i data-lucide="shield-check" class="w-6 h-6"></i>
                    </div>
                    <h3 class="text-lg font-bold text-white mb-2">Nuvem com Backup Diário</h3>
                    <p class="text-zinc-400 text-sm leading-relaxed">
                        Se seu celular quebrar ou for roubado, suas vendas e dados continuam salvos e seguros na nuvem com criptografia de ponta.
                    </p>
                </div>

            </div>
        </div>
    </section>

    <!-- PLANOS E PREÇOS -->
    <section id="planos" class="py-24 relative z-10">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center max-w-3xl mx-auto mb-16">
                <span class="text-emerald-400 text-xs font-bold uppercase tracking-wider">INVESTIMENTO ACESSÍVEL</span>
                <h2 class="text-3xl sm:text-4xl font-black text-white mt-1 mb-3">Comece com 7 dias grátis</h2>
                <p class="text-zinc-400 text-base">Teste sem pagar nada hoje. Assine quando comprovar que o vende+ facilita sua rotina.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-8 items-stretch max-w-4xl mx-auto">
                
                <!-- PLANO MENSAL -->
                <div class="p-8 rounded-3xl bg-dark-900 border border-zinc-800 flex flex-col justify-between h-full hover:border-zinc-700 transition">
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <h3 class="text-2xl font-black text-white">Plano Mensal</h3>
                            <span class="text-xs font-semibold px-2.5 py-1 rounded-lg bg-dark-800 text-zinc-400 border border-zinc-700">Flexível</span>
                        </div>
                        <p class="text-zinc-400 text-xs sm:text-sm mb-6">Ideal para quem quer testar e pagar mês a mês sem compromisso.</p>
                        
                        <div class="flex items-baseline mb-6">
                            <span class="text-4xl font-black text-white">R$ 24,90</span>
                            <span class="text-zinc-400 text-sm ml-2 font-medium">/mês</span>
                        </div>
                        
                        <ul class="space-y-3 text-xs sm:text-sm text-zinc-300 mb-8 list-none p-0">
                            <li class="flex items-center gap-2.5">
                                <i data-lucide="check" class="w-4 h-4 text-emerald-400 shrink-0"></i> <span>Acesso total a todas as funções</span>
                            </li>
                            <li class="flex items-center gap-2.5">
                                <i data-lucide="check" class="w-4 h-4 text-emerald-400 shrink-0"></i> <span>Cadastro ilimitado de produtos e vendas</span>
                            </li>
                            <li class="flex items-center gap-2.5">
                                <i data-lucide="check" class="w-4 h-4 text-emerald-400 shrink-0"></i> <span>Gestão de Crediário e Contas a Receber</span>
                            </li>
                            <li class="flex items-center gap-2.5">
                                <i data-lucide="check" class="w-4 h-4 text-emerald-400 shrink-0"></i> <span>Cálculo automático de Lucro Líquido</span>
                            </li>
                            <li class="flex items-center gap-2.5">
                                <i data-lucide="check" class="w-4 h-4 text-emerald-400 shrink-0"></i> <span>Instalação PWA no Celular e Computador</span>
                            </li>
                        </ul>
                    </div>

                    <div class="space-y-2.5">
                        <a href="https://pay.cakto.com.br/33o9a3t_1068067" target="_blank" 
                           class="w-full text-center text-sm font-bold bg-dark-800 hover:bg-dark-700 text-white py-3.5 rounded-2xl transition-all block no-underline border border-zinc-700">
                            Assinar Plano Mensal
                        </a>
                        <a href="cadastro.php" class="w-full text-center text-xs font-semibold text-zinc-500 hover:text-zinc-300 py-1 transition-all block no-underline">
                            ou testar 7 dias grátis
                        </a>
                    </div>
                </div>

                <!-- PLANO TRIMESTRAL (DESTAQUE MÁXIMO) -->
                <div class="p-8 rounded-3xl bg-dark-900 border-2 border-emerald-500 relative shadow-2xl glow-emerald flex flex-col justify-between h-full">
                    <div class="absolute -top-3.5 right-6 bg-emerald-500 text-black text-[11px] font-black px-3.5 py-1 rounded-full uppercase tracking-wider shadow-md">
                        MAIS ESCOLHIDO • ECONOMIZE 20%
                    </div>
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <h3 class="text-2xl font-black text-white">Plano Trimestral</h3>
                            <span class="text-xs font-bold px-2.5 py-1 rounded-lg bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">3 Meses</span>
                        </div>
                        <p class="text-zinc-400 text-xs sm:text-sm mb-6">O melhor custo-benefício para manter sua empresa organizada.</p>
                        
                        <div class="flex items-baseline mb-1">
                            <span class="text-4xl font-black text-emerald-400">R$ 19,90</span>
                            <span class="text-zinc-400 text-sm ml-2 font-medium">/mês</span>
                        </div>
                        <span class="text-xs text-zinc-500 block mb-6">Cobrado a cada 3 meses por R$ 59,70 (menos de R$ 0,70/dia)</span>
                        
                        <ul class="space-y-3 text-xs sm:text-sm text-zinc-200 mb-8 list-none p-0">
                            <li class="flex items-center gap-2.5">
                                <i data-lucide="check" class="w-4 h-4 text-emerald-400 shrink-0"></i> <strong class="text-white">Tudo do Plano Mensal incluso</strong>
                            </li>
                            <li class="flex items-center gap-2.5">
                                <i data-lucide="check" class="w-4 h-4 text-emerald-400 shrink-0"></i> <span>Economia de 20% no valor mensal</span>
                            </li>
                            <li class="flex items-center gap-2.5">
                                <i data-lucide="check" class="w-4 h-4 text-emerald-400 shrink-0"></i> <span>Suporte prioritário via WhatsApp</span>
                            </li>
                            <li class="flex items-center gap-2.5">
                                <i data-lucide="check" class="w-4 h-4 text-emerald-400 shrink-0"></i> <span>Garantia incondicional de 7 dias</span>
                            </li>
                        </ul>
                    </div>

                    <div class="space-y-2.5">
                        <a href="https://pay.cakto.com.br/mifseqt_1068083" target="_blank" 
                           class="w-full text-center text-sm font-black bg-emerald-500 hover:bg-emerald-400 text-black py-3.5 rounded-2xl transition-all shadow-lg shadow-emerald-500/25 block no-underline">
                            Assinar Trimestral com Desconto
                        </a>
                        <a href="cadastro.php" class="w-full text-center text-xs font-semibold text-zinc-500 hover:text-zinc-300 py-1 transition-all block no-underline">
                            ou começar 7 dias grátis
                        </a>
                    </div>
                </div>

            </div>
        </div>
    </section>

    <!-- FAQ -->
    <section id="faq" class="py-20 border-t border-zinc-800/80 bg-dark-900/30 relative z-10">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-12">
                <span class="text-emerald-400 text-xs font-bold uppercase tracking-wider">TIRE SUAS DÚVIDAS</span>
                <h2 class="text-3xl font-black text-white mt-1">Perguntas Frequentes</h2>
            </div>
            
            <div class="space-y-4">
                <details class="group bg-dark-900 p-5 rounded-2xl border border-zinc-800 cursor-pointer">
                    <summary class="font-semibold text-white flex justify-between items-center list-none">
                        Como funciona o teste de 7 dias grátis?
                        <i data-lucide="chevron-down" class="w-4 h-4 text-zinc-500 transition-transform group-open:rotate-180"></i>
                    </summary>
                    <p class="text-zinc-400 text-sm mt-3 leading-relaxed">
                        Você cria sua conta em menos de 1 minuto sem precisar cadastrar cartão de crédito. Durante 7 dias, você tem acesso completo a todos os recursos da plataforma para testar na prática com seu estoque real.
                    </p>
                </details>

                <details class="group bg-dark-900 p-5 rounded-2xl border border-zinc-800 cursor-pointer">
                    <summary class="font-semibold text-white flex justify-between items-center list-none">
                        Preciso instalar algum aplicativo pesado?
                        <i data-lucide="chevron-down" class="w-4 h-4 text-zinc-500 transition-transform group-open:rotate-180"></i>
                    </summary>
                    <p class="text-zinc-400 text-sm mt-3 leading-relaxed">
                        Não! O vende+ funciona diretamente pelo navegador de qualquer aparelho (computador, notebook, tablet ou celular). Você também pode adicionar um atalho na tela inicial do celular clicando em "Instalar Aplicativo".
                    </p>
                </details>

                <details class="group bg-dark-900 p-5 rounded-2xl border border-zinc-800 cursor-pointer">
                    <summary class="font-semibold text-white flex justify-between items-center list-none">
                        O que acontece se eu não quiser assinar após os 7 dias?
                        <i data-lucide="chevron-down" class="w-4 h-4 text-zinc-500 transition-transform group-open:rotate-180"></i>
                    </summary>
                    <p class="text-zinc-400 text-sm mt-3 leading-relaxed">
                        Nada! Como você não cadastra cartão para testar, nenhuma cobrança surpresa será feita. Seus dados permanecem salvos caso decida assinar mais tarde.
                    </p>
                </details>

                <details class="group bg-dark-900 p-5 rounded-2xl border border-zinc-800 cursor-pointer">
                    <summary class="font-semibold text-white flex justify-between items-center list-none">
                        Posso cancelar minha assinatura a qualquer momento?
                        <i data-lucide="chevron-down" class="w-4 h-4 text-zinc-500 transition-transform group-open:rotate-180"></i>
                    </summary>
                    <p class="text-zinc-400 text-sm mt-3 leading-relaxed">
                        Sim. Não há contratos de fidelidade nem multas. Você cancela quando quiser com apenas um clique.
                    </p>
                </details>
            </div>
        </div>
    </section>

    <!-- FOOTER -->
    <footer class="py-12 border-t border-zinc-800 bg-dark-950 text-center text-xs text-zinc-500 relative z-10">
        <div class="max-w-7xl mx-auto px-4 flex flex-col sm:flex-row items-center justify-between gap-4">
            <div class="flex items-center space-x-2">
                <span class="text-lg font-black text-white">vende<span class="text-emerald-400">+</span></span>
                <span>• Gestão Inteligente para Pequenos Negócios</span>
            </div>
            <p>© <?= date('Y') ?> vende+. Todos os direitos reservados.</p>
        </div>
    </footer>

    <script>
        document.addEventListener("DOMContentLoaded", function () {
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        });
    </script>
</body>
</html>