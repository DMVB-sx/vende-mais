<!DOCTYPE html>
<html lang="pt-BR" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vende+ | Sistema de Gestão Financeira e Vendas para Pequenos Negócios</title>
    <?php include __DIR__ . '/includes/favicon.php'; ?>
    <meta name="description" content="Controle vendas, estoque, compras e despesas do seu negócio em um só lugar. Sem planilha, sem caderno. Experimente o Vende+.">

    <!-- Open Graph: como o link aparece ao compartilhar no WhatsApp/Instagram -->
    <meta property="og:type" content="website">
    <meta property="og:title" content="Vende+ | Controle de vendas para pequenos negócios">
    <meta property="og:description" content="Cadastre produtos, registre vendas e acompanhe o lucro real do seu negócio em um só lugar.">
    <meta property="og:url" content="https://appvendemais.com.br">
    <!-- Troque pela URL real de uma imagem 1200x630px quando tiver uma pronta -->
    <meta property="og:image" content="https://appvendemais.com.br/assets/og-image.png">
    <meta name="twitter:card" content="summary_large_image">

    <!-- Tailwind CSS CDN para estilização ultra rápida (ok para agora; ver nota de produção) -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        brand: {
                            50: '#ecfdf5',
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
    <style>
        .glow-emerald {
            box-shadow: 0 0 50px -10px rgba(16, 185, 129, 0.25);
        }
        .gradient-border {
            background: linear-gradient(180deg, rgba(255,255,255,0.08) 0%, rgba(255,255,255,0.02) 100%);
        }
    </style>
</head>
<body class="bg-black text-slate-100 font-sans antialiased selection:bg-emerald-500 selection:text-black">

    <!-- NAVBAR -->
    <header class="fixed top-0 left-0 right-0 z-50 bg-black/80 backdrop-blur-md border-b border-zinc-800/80">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <svg width="28" height="28" viewBox="0 0 64 64" xmlns="http://www.w3.org/2000/svg" class="flex-shrink-0" style="margin-right:8px;">
                    <rect width="64" height="64" rx="16" fill="#09090b"/>
                    <path d="M14 22 L26 44 L44 16" fill="none" stroke="#ffffff" stroke-width="7" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M52 32 L52 44 M46 38 L58 38" stroke="#10b981" stroke-width="5.5" stroke-linecap="round"/>
                </svg>
                <span class="text-2xl font-extrabold tracking-tight text-white">vende<span class="text-emerald-500">+</span></span>
            </div>
            
            <nav class="hidden md:flex items-center space-x-8 text-sm font-medium text-zinc-400">
                <a href="#recursos" class="hover:text-emerald-400 transition-colors">Recursos</a>
                <a href="#demonstracao" class="hover:text-emerald-400 transition-colors">Demonstração</a>
                <a href="#planos" class="hover:text-emerald-400 transition-colors">Planos</a>
                <a href="#faq" class="hover:text-emerald-400 transition-colors">Dúvidas</a>
            </nav>

            <div class="flex items-center space-x-4">
                <a href="login.php" class="text-sm font-medium text-zinc-300 hover:text-white transition-colors">Entrar</a>
                <a href="#planos" class="text-sm font-semibold bg-emerald-500 hover:bg-emerald-400 text-black px-4 py-2 rounded-lg transition-all shadow-lg shadow-emerald-500/20">Começar Agora</a>
            </div>
        </div>
    </header>

    <!-- HERO SECTION -->
    <section class="relative pt-32 pb-20 md:pt-44 md:pb-32 overflow-hidden">
        <div class="absolute top-1/4 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[600px] h-[300px] bg-emerald-500/15 blur-[120px] pointer-events-none rounded-full"></div>
        
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 text-center relative z-10">
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full border border-emerald-500/30 bg-emerald-500/10 text-emerald-400 text-xs font-semibold uppercase tracking-wider mb-6">
                <span>⚡ Gestão Simplificada para o seu Comércio</span>
            </div>
            <h1 class="text-4xl sm:text-6xl lg:text-7xl font-extrabold tracking-tight text-white mb-6">
                Controle vendas, estoque e <span class="text-transparent bg-clip-text bg-gradient-to-r from-emerald-400 to-teal-200">lucro real</span> em um só lugar.
            </h1>
            <p class="text-lg sm:text-xl text-zinc-400 max-w-2xl mx-auto mb-10 leading-relaxed">
                Pare de perder dinheiro com anotações manuais e planilhas complexas. O Vende+ é a plataforma definitiva para você gerenciar o dia a dia do seu negócio em segundos.
            </p>
            <div class="flex flex-col sm:flex-row items-center justify-center gap-4">
                <a href="#planos" class="w-full sm:w-auto text-base font-bold bg-emerald-500 hover:bg-emerald-400 text-black px-8 py-3.5 rounded-xl transition-all shadow-xl shadow-emerald-500/20">
                    Assinar Agora
                </a>
                <a href="#demonstracao" class="w-full sm:w-auto text-base font-semibold bg-zinc-900 hover:bg-zinc-800 text-zinc-200 border border-zinc-700 px-8 py-3.5 rounded-xl transition-all">
                    Ver Demonstração
                </a>
            </div>
        </div>
    </section>

    <!-- PREVIEW / DEMONSTRAÇÃO INTERATIVA -->
    <section id="demonstracao" class="py-16 relative">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-dark-900 border border-zinc-800 rounded-2xl p-4 sm:p-8 shadow-2xl glow-emerald">
                <div class="flex items-center justify-between pb-6 border-b border-zinc-800 mb-6">
                    <div class="flex items-center space-x-2">
                        <div class="w-3 h-3 rounded-full bg-red-500/80"></div>
                        <div class="w-3 h-3 rounded-full bg-yellow-500/80"></div>
                        <div class="w-3 h-3 rounded-full bg-emerald-500/80"></div>
                        <span class="text-xs text-zinc-500 ml-2 font-mono">painel.vendemais.com.br</span>
                    </div>
                    <span class="text-xs font-semibold px-2.5 py-1 bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 rounded-md">Simulação ao Vivo</span>
                </div>

                <!-- CARDS DE MÉTRICAS -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
                    <div class="bg-dark-800 border border-zinc-800/80 rounded-xl p-5">
                        <p class="text-xs font-medium text-zinc-400 mb-1">Faturamento Bruto</p>
                        <p class="text-2xl font-bold text-white">R$ 14.850,00</p>
                        <span class="text-xs text-emerald-400 mt-2 inline-block">↑ +18% este mês</span>
                    </div>
                    <div class="bg-dark-800 border border-zinc-800/80 rounded-xl p-5">
                        <p class="text-xs font-medium text-zinc-400 mb-1">Despesas Operacionais</p>
                        <p class="text-2xl font-bold text-zinc-200">R$ 3.420,00</p>
                        <span class="text-xs text-zinc-500 mt-2 inline-block">Controle total de saídas</span>
                    </div>
                    <div class="bg-dark-800 border border-emerald-500/30 rounded-xl p-5 relative overflow-hidden">
                        <div class="absolute -right-4 -bottom-4 w-20 h-20 bg-emerald-500/10 rounded-full blur-xl"></div>
                        <p class="text-xs font-medium text-emerald-400 mb-1">Lucro Líquido Real</p>
                        <p class="text-2xl font-bold text-emerald-400">R$ 11.430,00</p>
                        <span class="text-xs text-emerald-500 mt-2 inline-block">Margem de 77%</span>
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
                                <td class="py-2.5 font-medium text-white">Fone Bluetooth Pro</td>
                                <td class="py-2.5">2 un.</td>
                                <td class="py-2.5"><span class="px-2 py-0.5 rounded bg-zinc-700 text-zinc-300 text-[10px]">Mercado Livre</span></td>
                                <td class="py-2.5 text-right font-semibold text-emerald-400">R$ 380,00</td>
                            </tr>
                            <tr>
                                <td class="py-2.5 font-medium text-white">Carregador Turbo 30W</td>
                                <td class="py-2.5">1 un.</td>
                                <td class="py-2.5"><span class="px-2 py-0.5 rounded bg-zinc-700 text-zinc-300 text-[10px]">Balcão / Loja</span></td>
                                <td class="py-2.5 text-right font-semibold text-emerald-400">R$ 95,00</td>
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
                <h2 class="text-3xl sm:text-4xl font-extrabold text-white mb-4">Tudo que você precisa para crescer</h2>
                <p class="text-zinc-400 text-base">Ferramentas práticas e intuitivas feitas sob medida para quem não tem tempo a perder.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <div class="p-6 rounded-2xl bg-dark-800 border border-zinc-800">
                    <div class="w-12 h-12 rounded-xl bg-emerald-500/10 text-emerald-400 flex items-center justify-center text-2xl mb-5">📊</div>
                    <h3 class="text-lg font-bold text-white mb-2">Painel Financeiro em Tempo Real</h3>
                    <p class="text-zinc-400 text-sm leading-relaxed">Acompanhe entradas, despesas fixas e variáveis e o lucro líquido apurado automaticamente.</p>
                </div>

                <div class="p-6 rounded-2xl bg-dark-800 border border-zinc-800">
                    <div class="w-12 h-12 rounded-xl bg-emerald-500/10 text-emerald-400 flex items-center justify-center text-2xl mb-5">📦</div>
                    <h3 class="text-lg font-bold text-white mb-2">Controle Inteligente de Estoque</h3>
                    <p class="text-zinc-400 text-sm leading-relaxed">Baixa automática ao registrar pedidos e alertas visuais antes que seus produtos esgotem.</p>
                </div>

                <div class="p-6 rounded-2xl bg-dark-800 border border-zinc-800">
                    <div class="w-12 h-12 rounded-xl bg-emerald-500/10 text-emerald-400 flex items-center justify-center text-2xl mb-5">📱</div>
                    <h3 class="text-lg font-bold text-white mb-2">Acesse de Qualquer Lugar</h3>
                    <p class="text-zinc-400 text-sm leading-relaxed">Compatível 100% com celulares, tablets e computadores, direto pelo navegador — sem precisar instalar nada.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- PLANOS E PREÇOS (CAKTO CHECKOUT) -->
    <section id="planos" class="py-24 relative">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center max-w-3xl mx-auto mb-16">
                <span class="text-emerald-400 text-xs font-bold uppercase tracking-wider">Investimento Acessível</span>
                <h2 class="text-3xl sm:text-4xl font-extrabold text-white mt-2 mb-4">Escolha o plano ideal para você</h2>
                <p class="text-zinc-400 text-base">Sem taxas escondidas. Cancele quando quiser.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-8 items-center">
                <!-- PLANO MENSAL -->
                <div class="p-8 rounded-2xl bg-dark-800 border border-zinc-800 flex flex-col justify-between h-full">
                    <div>
                        <h3 class="text-xl font-bold text-white mb-2">Mensal</h3>
                        <p class="text-zinc-400 text-sm mb-6">Flexibilidade para quem está começando agora.</p>
                        <div class="flex items-baseline mb-6">
                            <span class="text-4xl font-extrabold text-white">R$ 29,90</span>
                            <span class="text-zinc-400 text-sm ml-2">/mês</span>
                        </div>
                        <ul class="space-y-3 text-sm text-zinc-300 mb-8">
                            <li class="flex items-center gap-2">✓ Cadastro Ilimitado de Produtos</li>
                            <li class="flex items-center gap-2">✓ Registro de Vendas e Despesas</li>
                            <li class="flex items-center gap-2">✓ Painel de Lucro em Tempo Real</li>
                            <li class="flex items-center gap-2">✓ Suporte via WhatsApp</li>
                        </ul>
                    </div>
                    <!-- SUBSTITUA O LINK ABAIXO PELO SEU LINK DA CAKTO -->
                    <a href="https://cakto.com.br/link-seu-checkout-mensal" target="_blank" class="w-full text-center text-sm font-bold bg-zinc-700 hover:bg-zinc-600 text-white py-3 rounded-xl transition-all">
                        Assinar Mensal
                    </a>
                </div>

                <!-- PLANO ANUAL (DESTAQUE) -->
                <div class="p-8 rounded-2xl bg-dark-900 border-2 border-emerald-500 relative shadow-2xl glow-emerald flex flex-col justify-between h-full">
                    <div class="absolute -top-3.5 right-6 bg-emerald-500 text-black text-xs font-extrabold px-3 py-1 rounded-full uppercase tracking-wide">
                        Economize 30%
                    </div>
                    <div>
                        <h3 class="text-xl font-bold text-white mb-2">Anual Pro</h3>
                        <p class="text-zinc-400 text-sm mb-6">Máximo desconto para quem quer crescer com consistência.</p>
                        <div class="flex items-baseline mb-6">
                            <span class="text-4xl font-extrabold text-emerald-400">R$ 19,90</span>
                            <span class="text-zinc-400 text-sm ml-2">/mês (R$ 238,80/ano)</span>
                        </div>
                        <ul class="space-y-3 text-sm text-zinc-200 mb-8">
                            <li class="flex items-center gap-2 text-emerald-400">✓ Todos os recursos do plano mensal</li>
                            <li class="flex items-center gap-2 text-emerald-400">✓ Relatórios avançados para download</li>
                            <li class="flex items-center gap-2 text-emerald-400">✓ Acesso prioritário a novas funções</li>
                            <li class="flex items-center gap-2 text-emerald-400">✓ Ativação imediata via Pix ou Cartão</li>
                        </ul>
                    </div>
                    <!-- SUBSTITUA O LINK ABAIXO PELO SEU LINK DA CAKTO -->
                    <a href="https://cakto.com.br/link-seu-checkout-anual" target="_blank" class="w-full text-center text-sm font-bold bg-emerald-500 hover:bg-emerald-400 text-black py-3.5 rounded-xl transition-all shadow-lg shadow-emerald-500/25">
                        Garantir Plano Anual
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- FAQ -->
    <section id="faq" class="py-20 border-t border-zinc-800/80 bg-dark-900/30">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="text-3xl font-extrabold text-white text-center mb-12">Perguntas Frequentes</h2>
            
            <div class="space-y-4">
                <details class="group bg-dark-800 p-5 rounded-xl border border-zinc-800 cursor-pointer">
                    <summary class="font-semibold text-white flex justify-between items-center list-none">
                        Como recebo o meu acesso após o pagamento?
                        <span class="transition group-open:rotate-180">▾</span>
                    </summary>
                    <p class="text-zinc-400 text-sm mt-3 leading-relaxed">
                        Assim que o pagamento for confirmado pela Cakto (no Pix é instantâneo), você receberá um e-mail com as instruções e sua conta será liberada para uso imediato no sistema.
                    </p>
                </details>

                <details class="group bg-dark-800 p-5 rounded-xl border border-zinc-800 cursor-pointer">
                    <summary class="font-semibold text-white flex justify-between items-center list-none">
                        Preciso instalar algum programa pesado no computador?
                        <span class="transition group-open:rotate-180">▾</span>
                    </summary>
                    <p class="text-zinc-400 text-sm mt-3 leading-relaxed">
                        Não! O Vende+ funciona 100% em nuvem diretamente pelo navegador de qualquer aparelho (computador, notebook, tablet ou celular).
                    </p>
                </details>

                <details class="group bg-dark-800 p-5 rounded-xl border border-zinc-800 cursor-pointer">
                    <summary class="font-semibold text-white flex justify-between items-center list-none">
                        Posso cancelar quando quiser?
                        <span class="transition group-open:rotate-180">▾</span>
                    </summary>
                    <p class="text-zinc-400 text-sm mt-3 leading-relaxed">
                        Sim, você tem total controle da sua assinatura e pode cancelar a qualquer momento sem burocracia ou multas.
                    </p>
                </details>
            </div>
        </div>
    </section>

    <!-- FOOTER -->
    <footer class="py-10 border-t border-zinc-800 text-center text-xs text-zinc-500">
        <p class="mb-2">© <?= date('Y') ?> Vende+. Todos os direitos reservados.</p>
        <p>Pagamentos 100% seguros processados via Cakto.</p>
    </footer>

</body>
</html>