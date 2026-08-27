<?php
// Previne cache do navegador para sempre mostrar a versão atualizada
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
    <title>vende+ | Sistema de Gestão Financeira e Vendas para Pequenos Negócios</title>
    
    <!-- FAVICON -->
    <link rel="icon" type="image/svg+xml" href="/assets/img/favicon.svg">
    <meta name="description" content="Controle vendas, estoque, compras e despesas do seu negócio em um só lugar. Sem planilha, sem caderno. Experimente o vende+ gratuitamente por 7 dias.">

    <!-- Open Graph -->
    <meta property="og:type" content="website">
    <meta property="og:title" content="vende+ | Controle de vendas e lucro real">
    <meta property="og:description" content="Cadastre produtos, registre vendas e acompanhe o lucro real do seu negócio em segundos.">
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
                            500: '#10b981',
                            600: '#059669',
                            700: '#047857',
                        },
                        dark: {
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
    
    <!-- LUCIDE ICONS -->
    <script src="https://unpkg.com/lucide@latest"></script>

    <style>
        .glow-emerald {
            box-shadow: 0 0 50px -10px rgba(16, 185, 129, 0.2);
        }
    </style>
</head>
<body class="bg-black text-slate-100 font-sans antialiased selection:bg-emerald-500 selection:text-black">

    <!-- NAVBAR -->
    <header class="fixed top-0 left-0 right-0 z-50 bg-black/80 backdrop-blur-md border-b border-zinc-800/80">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
            <a href="landing.php" class="flex items-center space-x-3 text-decoration-none">
                <svg width="28" height="28" viewBox="0 0 64 64" xmlns="http://www.w3.org/2000/svg" class="flex-shrink-0">
                    <rect width="64" height="64" rx="16" fill="#09090b"/>
                    <path d="M14 22 L26 44 L44 16" fill="none" stroke="#ffffff" stroke-width="7" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M52 32 L52 44 M46 38 L58 38" stroke="#10b981" stroke-width="5.5" stroke-linecap="round"/>
                </svg>
                <span class="text-2xl font-black tracking-tight text-white">vende<span class="text-emerald-500">+</span></span>
            </a>
            
            <nav class="hidden md:flex items-center space-x-8 text-sm font-medium text-zinc-400">
                <a href="#recursos" class="hover:text-emerald-400 transition-colors">Recursos</a>
                <a href="#demonstracao" class="hover:text-emerald-400 transition-colors">Demonstração</a>
                <a href="#planos" class="hover:text-emerald-400 transition-colors">Planos</a>
                <a href="#faq" class="hover:text-emerald-400 transition-colors">Dúvidas</a>
            </nav>

            <div class="flex items-center space-x-4">
                <a href="login.php" class="text-sm font-medium text-zinc-300 hover:text-white transition-colors">Entrar</a>
                <a href="cadastro.php" class="text-sm font-bold bg-emerald-500 hover:bg-emerald-400 text-black px-4 py-2 rounded-xl transition-all shadow-lg shadow-emerald-500/20 inline-flex items-center gap-1.5">
                    <span>Testar Grátis</span>
                    <i data-lucide="arrow-right" class="w-4 h-4"></i>
                </a>
            </div>
        </div>
    </header>

    <!-- HERO SECTION -->
    <section class="relative pt-32 pb-20 md:pt-44 md:pb-32 overflow-hidden">
        <div class="absolute top-1/4 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[600px] h-[300px] bg-emerald-500/15 blur-[120px] pointer-events-none rounded-full"></div>
        
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 text-center relative z-10">
            <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full border border-emerald-500/30 bg-emerald-500/10 text-emerald-400 text-xs font-bold uppercase tracking-wider mb-6">
                <i data-lucide="zap" class="w-3.5 h-3.5"></i>
                <span>7 Dias de Teste Grátis • Sem Compromisso</span>
            </div>
            
            <h1 class="text-4xl sm:text-6xl lg:text-7xl font-black tracking-tight text-white mb-6 leading-tight">
                Controle vendas, estoque e <span class="text-transparent bg-clip-text bg-gradient-to-r from-emerald-400 to-teal-200">lucro real</span> em um só lugar.
            </h1>
            
            <p class="text-base sm:text-xl text-zinc-400 max-w-2xl mx-auto mb-8 leading-relaxed">
                Pare de perder dinheiro com anotações manuais e planilhas complexas. O vende+ é a plataforma feita sob medida para pequenos comércios e autônomos.
            </p>
            
            <div class="flex flex-col sm:flex-row items-center justify-center gap-4 mb-4">
                <a href="cadastro.php" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 text-base font-bold bg-emerald-500 hover:bg-emerald-400 text-black px-8 py-4 rounded-xl transition-all shadow-xl shadow-emerald-500/25">
                    <span>Testar 7 dias grátis</span>
                    <i data-lucide="arrow-right" class="w-5 h-5"></i>
                </a>
                <a href="#demonstracao" class="w-full sm:w-auto text-base font-semibold bg-zinc-900 hover:bg-zinc-800 text-zinc-200 border border-zinc-800 px-8 py-4 rounded-xl transition-all">
                    Ver Demonstração
                </a>
            </div>
            
            <div class="flex items-center justify-center gap-4 text-xs text-zinc-500 font-medium">
                <span class="inline-flex items-center gap-1">
                    <i data-lucide="check" class="w-3.5 h-3.5 text-emerald-400"></i> Sem cartão de crédito
                </span>
                <span>•</span>
                <span class="inline-flex items-center gap-1">
                    <i data-lucide="check" class="w-3.5 h-3.5 text-emerald-400"></i> Acesso instantâneo
                </span>
            </div>
        </div>
    </section>

    <!-- DEMONSTRAÇÃO INTERATIVA -->
    <section id="demonstracao" class="py-16 relative">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-dark-900 border border-zinc-800/90 rounded-2xl p-4 sm:p-8 shadow-2xl glow-emerald">
                <div class="flex items-center justify-between pb-6 border-b border-zinc-800/80 mb-6">
                    <div class="flex items-center space-x-2">
                        <div class="w-3 h-3 rounded-full bg-rose-500/80"></div>
                        <div class="w-3 h-3 rounded-full bg-amber-500/80"></div>
                        <div class="w-3 h-3 rounded-full bg-emerald-500/80"></div>
                        <span class="text-xs text-zinc-500 ml-2 font-mono">painel.vendemais.com.br</span>
                    </div>
                    <span class="text-xs font-semibold px-2.5 py-1 bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 rounded-lg">Simulação ao Vivo</span>
                </div>

                <!-- CARDS DE MÉTRICAS -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
                    <div class="bg-dark-800 border border-zinc-800/80 rounded-xl p-5">
                        <p class="text-xs font-medium text-zinc-400 mb-1">Faturamento Bruto</p>
                        <p class="text-2xl font-black text-white">R$ 14.850,00</p>
                        <span class="text-xs text-emerald-400 mt-2 inline-block font-semibold">↑ +18% este mês</span>
                    </div>
                    <div class="bg-dark-800 border border-zinc-800/80 rounded-xl p-5">
                        <p class="text-xs font-medium text-zinc-400 mb-1">Despesas Operacionais</p>
                        <p class="text-2xl font-black text-zinc-300">R$ 3.420,00</p>
                        <span class="text-xs text-zinc-500 mt-2 inline-block">Controle total de saídas</span>
                    </div>
                    <div class="bg-dark-800 border border-emerald-500/30 rounded-xl p-5 relative overflow-hidden">
                        <div class="absolute -right-4 -bottom-4 w-20 h-20 bg-emerald-500/10 rounded-full blur-xl"></div>
                        <p class="text-xs font-medium text-emerald-400 mb-1">Lucro Líquido Real</p>
                        <p class="text-2xl font-black text-emerald-400">R$ 11.430,00</p>
                        <span class="text-xs text-emerald-500 mt-2 inline-block font-bold">Margem de 77%</span>
                    </div>
                </div>

                <!-- TABELA DEMO -->
                <div class="bg-dark-800 border border-zinc-800/80 rounded-xl p-4 overflow-x-auto">
                    <div class="text-xs font-bold text-zinc-400 mb-3 uppercase tracking-wider">Últimas Vendas Registradas</div>
                    <table class="w-full text-left text-sm">
                        <thead>
                            <tr class="text-zinc-500 text-xs border-b border-zinc-800">
                                <th class="pb-2">Produto</th>
                                <th class="pb-2">Qtd</th>
                                <th class="pb-2">Canal</th>
                                <th class="pb-2 text-right">Total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-800/50 text-zinc-300 text-xs">
                            <tr>
                                <td class="py-3 font-medium text-white">Fone Bluetooth Pro</td>
                                <td class="py-3">2 un.</td>
                                <td class="py-3"><span class="px-2 py-0.5 rounded bg-zinc-800 text-zinc-300 border border-zinc-700/50 text-[10px]">Mercado Livre</span></td>
                                <td class="py-3 text-right font-bold text-emerald-400">R$ 380,00</td>
                            </tr>
                            <tr>
                                <td class="py-3 font-medium text-white">Carregador Turbo 30W</td>
                                <td class="py-3">1 un.</td>
                                <td class="py-3"><span class="px-2 py-0.5 rounded bg-zinc-800 text-zinc-300 border border-zinc-700/50 text-[10px]">Balcão / Loja</span></td>
                                <td class="py-3 text-right font-bold text-emerald-400">R$ 95,00</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>

    <!-- RECURSOS / BENEFÍCIOS -->
    <section id="recursos" class="py-20 border-t border-zinc-800/80 bg-dark-900/40">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center max-w-3xl mx-auto mb-16">
                <h2 class="text-3xl sm:text-4xl font-black text-white mb-4">Tudo o que você precisa para crescer</h2>
                <p class="text-zinc-400 text-base">Ferramentas práticas e intuitivas feitas sob medida para quem não tem tempo a perder.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <div class="p-6 rounded-2xl bg-dark-800 border border-zinc-800/80 hover:border-zinc-700 transition-colors">
                    <div class="w-12 h-12 rounded-xl bg-emerald-500/10 text-emerald-400 flex items-center justify-center mb-5 border border-emerald-500/20">
                        <i data-lucide="bar-chart-3" class="w-6 h-6"></i>
                    </div>
                    <h3 class="text-lg font-bold text-white mb-2">Painel Financeiro em Tempo Real</h3>
                    <p class="text-zinc-400 text-sm leading-relaxed">Acompanhe entradas, despesas fixas e variáveis e o lucro líquido apurado automaticamente.</p>
                </div>

                <div class="p-6 rounded-2xl bg-dark-800 border border-zinc-800/80 hover:border-zinc-700 transition-colors">
                    <div class="w-12 h-12 rounded-xl bg-emerald-500/10 text-emerald-400 flex items-center justify-center mb-5 border border-emerald-500/20">
                        <i data-lucide="boxes" class="w-6 h-6"></i>
                    </div>
                    <h3 class="text-lg font-bold text-white mb-2">Controle Inteligente de Estoque</h3>
                    <p class="text-zinc-400 text-sm leading-relaxed">Baixa automática ao registrar pedidos e alertas visuais antes que seus produtos esgotem.</p>
                </div>

                <div class="p-6 rounded-2xl bg-dark-800 border border-zinc-800/80 hover:border-zinc-700 transition-colors">
                    <div class="w-12 h-12 rounded-xl bg-emerald-500/10 text-emerald-400 flex items-center justify-center mb-5 border border-emerald-500/20">
                        <i data-lucide="smartphone" class="w-6 h-6"></i>
                    </div>
                    <h3 class="text-lg font-bold text-white mb-2">Acesse de Qualquer Lugar</h3>
                    <p class="text-zinc-400 text-sm leading-relaxed">Compatível 100% com celulares, tablets e computadores, direto pelo navegador — sem precisar instalar nada.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- PLANOS E PREÇOS -->
    <section id="planos" class="py-24 relative">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center max-w-3xl mx-auto mb-16">
                <span class="text-emerald-400 text-xs font-bold uppercase tracking-wider">Investimento Acessível</span>
                <h2 class="text-3xl sm:text-4xl font-black text-white mt-2 mb-4">Escolha o plano ideal após os 7 dias grátis</h2>
                <p class="text-zinc-400 text-base">Teste sem pagar nada hoje. Cancele quando quiser.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-8 items-stretch">
                <!-- PLANO MENSAL -->
                <div class="p-8 rounded-2xl bg-dark-800 border border-zinc-800/80 flex flex-col justify-between h-full">
                    <div>
                        <h3 class="text-xl font-bold text-white mb-2">Mensal</h3>
                        <p class="text-zinc-400 text-sm mb-6">Flexibilidade para quem está começando agora.</p>
                        <div class="flex items-baseline mb-6">
                            <span class="text-4xl font-black text-white">R$ 29,90</span>
                            <span class="text-zinc-400 text-sm ml-2">/mês</span>
                        </div>
                        <ul class="space-y-3 text-sm text-zinc-300 mb-8">
                            <li class="flex items-center gap-2">
                                <i data-lucide="check" class="w-4 h-4 text-emerald-400 shrink-0"></i> Cadastro Ilimitado de Produtos
                            </li>
                            <li class="flex items-center gap-2">
                                <i data-lucide="check" class="w-4 h-4 text-emerald-400 shrink-0"></i> Registro de Vendas e Despesas
                            </li>
                            <li class="flex items-center gap-2">
                                <i data-lucide="check" class="w-4 h-4 text-emerald-400 shrink-0"></i> Painel de Lucro em Tempo Real
                            </li>
                            <li class="flex items-center gap-2">
                                <i data-lucide="check" class="w-4 h-4 text-emerald-400 shrink-0"></i> Suporte via WhatsApp
                            </li>
                        </ul>
                    </div>
                    <a href="cadastro.php" class="w-full text-center text-sm font-bold bg-zinc-800 hover:bg-zinc-700 text-white py-3.5 rounded-xl transition-all block">
                        Começar 7 dias grátis
                    </a>
                </div>

                <!-- PLANO ANUAL (DESTAQUE) -->
                <div class="p-8 rounded-2xl bg-dark-900 border-2 border-emerald-500 relative shadow-2xl glow-emerald flex flex-col justify-between h-full">
                    <div class="absolute -top-3.5 right-6 bg-emerald-500 text-black text-xs font-black px-3 py-1 rounded-full uppercase tracking-wide">
                        Economize 30%
                    </div>
                    <div>
                        <h3 class="text-xl font-bold text-white mb-2">Anual Pro</h3>
                        <p class="text-zinc-400 text-sm mb-6">Máximo desconto para quem quer crescer com consistência.</p>
                        <div class="flex items-baseline mb-6">
                            <span class="text-4xl font-black text-emerald-400">R$ 19,90</span>
                            <span class="text-zinc-400 text-sm ml-2">/mês (R$ 238,80/ano)</span>
                        </div>
                        <ul class="space-y-3 text-sm text-zinc-200 mb-8">
                            <li class="flex items-center gap-2">
                                <i data-lucide="check" class="w-4 h-4 text-emerald-400 shrink-0"></i> Todos os recursos do plano mensal
                            </li>
                            <li class="flex items-center gap-2">
                                <i data-lucide="check" class="w-4 h-4 text-emerald-400 shrink-0"></i> Relatórios avançados para download
                            </li>
                            <li class="flex items-center gap-2">
                                <i data-lucide="check" class="w-4 h-4 text-emerald-400 shrink-0"></i> Acesso prioritário a novas funções
                            </li>
                            <li class="flex items-center gap-2">
                                <i data-lucide="check" class="w-4 h-4 text-emerald-400 shrink-0"></i> 7 dias grátis antes da primeira cobrança
                            </li>
                        </ul>
                    </div>
                    <a href="cadastro.php" class="w-full text-center text-sm font-bold bg-emerald-500 hover:bg-emerald-400 text-black py-3.5 rounded-xl transition-all shadow-lg shadow-emerald-500/25 block">
                        Começar 7 dias grátis
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- FAQ -->
    <section id="faq" class="py-20 border-t border-zinc-800/80 bg-dark-900/30">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="text-3xl font-black text-white text-center mb-12">Perguntas Frequentes</h2>
            
            <div class="space-y-4">
                <details class="group bg-dark-800 p-5 rounded-2xl border border-zinc-800/80 cursor-pointer">
                    <summary class="font-semibold text-white flex justify-between items-center list-none">
                        Como funciona o teste de 7 dias grátis?
                        <i data-lucide="chevron-down" class="w-4 h-4 text-zinc-500 transition-transform group-open:rotate-180"></i>
                    </summary>
                    <p class="text-zinc-400 text-sm mt-3 leading-relaxed">
                        Você cria sua conta em menos de 1 minuto sem precisar cadastrar cartão de crédito. Durante 7 dias, tem acesso completo a todos os recursos da plataforma para testar no seu negócio.
                    </p>
                </details>

                <details class="group bg-dark-800 p-5 rounded-2xl border border-zinc-800/80 cursor-pointer">
                    <summary class="font-semibold text-white flex justify-between items-center list-none">
                        Preciso instalar algum aplicativo no computador ou celular?
                        <i data-lucide="chevron-down" class="w-4 h-4 text-zinc-500 transition-transform group-open:rotate-180"></i>
                    </summary>
                    <p class="text-zinc-400 text-sm mt-3 leading-relaxed">
                        Não! O vende+ funciona 100% em nuvem diretamente pelo navegador de qualquer aparelho (computador, notebook, tablet ou celular).
                    </p>
                </details>

                <details class="group bg-dark-800 p-5 rounded-2xl border border-zinc-800/80 cursor-pointer">
                    <summary class="font-semibold text-white flex justify-between items-center list-none">
                        Posso cancelar quando quiser?
                        <i data-lucide="chevron-down" class="w-4 h-4 text-zinc-500 transition-transform group-open:rotate-180"></i>
                    </summary>
                    <p class="text-zinc-400 text-sm mt-3 leading-relaxed">
                        Sim, você tem total controle da sua assinatura e pode cancelar a qualquer momento sem burocracia ou taxas de fidelidade.
                    </p>
                </details>
            </div>
        </div>
    </section>

    <!-- FOOTER -->
    <footer class="py-10 border-t border-zinc-800 text-center text-xs text-zinc-500">
        <p class="mb-2">© <?= date('Y') ?> vende+. Todos os direitos reservados.</p>
        <p>Plataforma para gestão financeira e comercial simplificada.</p>
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