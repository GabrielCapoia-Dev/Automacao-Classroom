<!-- $view = 'components.layouts.list-alunos' -->
<x-filament::page>
    <div class="grid grid-cols-1 gap-4 items-start {{ $alunoSelecionado ? 'lg:grid-60-40' : 'lg:grid-cols-1' }}">
        {{-- Tabela --}}
        <div class="order-1 {{ $alunoSelecionado ? '' : 'col-span-full' }}">
            {{ $this->table }}
        </div>

        {{-- Painel lateral (DESKTOP) --}}
        @if ($alunoSelecionado)
        <div class="order-2">
            <div class="relative bg-white dark:bg-gray-800 rounded-lg shadow-lg overflow-hidden">
                {{-- Botão X --}}
                <button wire:click="fecharDetalhesAluno"
                    class="absolute top-3 right-3 z-10 inline-flex items-center justify-center rounded-full p-1 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition">
                    <x-heroicon-o-x-mark class="w-5 h-5" />
                </button>

                {{-- Header --}}
                <div class="bg-gradient-to-r from-blue-500 to-blue-600 p-4 text-white text-center rounded-t-lg">
                    <h2 class="text-lg font-semibold">Informações do Aluno</h2>
                </div>

                {{-- Conteúdo --}}
                <div class="p-6 space-y-4">
                    {{-- Avatar + Info --}}
                    <div class="text-center">
                        <img src="{{ $alunoSelecionado->foto ? asset('storage/' . $alunoSelecionado->foto) : 'https://ui-avatars.com/api/?name=' . urlencode($alunoSelecionado->nome) . '&size=120&background=e5e7eb&color=374151' }}"
                            class="w-24 h-24 rounded-full object-cover border-4 border-white shadow-lg mx-auto mb-3">
                        <h3 class="text-lg font-bold text-gray-900 dark:text-white">
                            {{ $alunoSelecionado->nome }}
                        </h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            {{ $alunoSelecionado->sexo }} • {{ \Carbon\Carbon::parse($alunoSelecionado->data_nascimento)->format('d/m/Y') }}
                        </p>
                        <div class="mt-2 inline-flex items-center px-3 py-1 bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200 text-xs rounded-full">
                            CGM: {{ $alunoSelecionado->cgm ?? 'N/A' }}
                        </div>
                        <div class="mt-2 inline-flex items-center px-3 py-1 bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200 text-xs rounded-full">
                            TURNO: {{ $alunoSelecionado->turma->turno ?? 'N/A' }}
                        </div>
                    </div>

                    {{-- Escola / Turma --}}
                    @if ($alunoSelecionado->turma)
                    <div class="text-center text-sm text-gray-600 dark:text-gray-400">
                        <div>{{ $alunoSelecionado->turma->escola->nome ?? '' }}</div>
                        <div>{{ $alunoSelecionado->turma->serie->nome ?? '' }} - {{ $alunoSelecionado->turma->turma ?? '' }}</div>
                    </div>
                    @endif

                    {{-- Contato --}}
                    <div class="border-t border-gray-200 dark:border-gray-700 pt-4">
                        <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2 flex items-center">
                            <x-heroicon-o-phone class="w-4 h-4 mr-2" /> Contato
                        </h4>
                        <p class="text-sm"><span class="font-medium">Responsável:</span> {{ $alunoSelecionado->nome_responsavel ?? 'N/A' }}</p>
                        <p class="text-sm"><span class="font-medium">Telefone:</span> {{ $alunoSelecionado->telefone_responsavel ?? 'N/A' }}</p>
                        @if ($alunoSelecionado->telefone_aluno)
                        <p class="text-sm"><span class="font-medium">Tel. Aluno:</span> {{ $alunoSelecionado->telefone_aluno }}</p>
                        @endif
                    </div>

                    {{-- Endereço --}}
                    <div class="border-t border-gray-200 dark:border-gray-700 pt-4">
                        <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2 flex items-center">
                            <x-heroicon-o-map-pin class="w-4 h-4 mr-2" /> Endereço
                        </h4>
                        <p class="text-sm">{{ $alunoSelecionado->logradouro ?? 'N/A' }}, {{ $alunoSelecionado->numero ?? '' }}</p>
                        <p class="text-sm">{{ $alunoSelecionado->bairro ?? '' }} - {{ $alunoSelecionado->cidade ?? '' }}/{{ $alunoSelecionado->estado ?? '' }}</p>
                        <p class="text-sm">CEP: {{ $alunoSelecionado->cep ?? 'N/A' }}</p>
                    </div>
                </div>
            </div>
        </div>
        @endif
    </div>

    {{-- Modal (MOBILE/TABLET) --}}
    @if ($alunoSelecionado)
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6 lg:hidden" wire:ignore.self>
        <!-- Backdrop -->
        <div class="fixed inset-0 bg-black bg-opacity-50 transition-opacity"
            wire:click="fecharDetalhesAluno"></div>

        <!-- Modal -->
        <div class="relative w-full max-w-md bg-white dark:bg-gray-900 rounded-xl shadow-xl overflow-hidden transform transition-all flex flex-col"
            style="width: 95vw; max-width: 480px; max-height: 85vh;">

            <!-- Header -->
            <div class="flex items-center justify-between px-4 py-3 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 flex-shrink-0">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Informações do Aluno</h3>
                <button wire:click="fecharDetalhesAluno"
                    class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 focus:outline-none p-2 rounded-md hover:bg-gray-100 dark:hover:bg-gray-700 transition">
                    <span class="sr-only">Fechar</span>
                    <x-heroicon-o-x-mark class="h-6 w-6" />
                </button>
            </div>

            <!-- Body -->
            <div class="flex-1 p-4 overflow-y-auto custom-scrollbar space-y-4">
                {{-- Avatar + Info --}}
                <div class="text-center">
                    <img src="{{ $alunoSelecionado->foto ? asset('storage/' . $alunoSelecionado->foto) : 'https://ui-avatars.com/api/?name=' . urlencode($alunoSelecionado->nome) . '&size=120&background=e5e7eb&color=374151' }}"
                        class="w-20 h-20 rounded-full object-cover border-4 border-white shadow-lg mx-auto mb-3">
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white">{{ $alunoSelecionado->nome }}</h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        {{ $alunoSelecionado->sexo }} • {{ \Carbon\Carbon::parse($alunoSelecionado->data_nascimento)->format('d/m/Y') }}
                    </p>
                    <div class="mt-2 inline-flex items-center px-3 py-1 bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200 text-xs rounded-full">
                        CGM: {{ $alunoSelecionado->cgm ?? 'N/A' }}
                    </div>
                    <div class="mt-2 inline-flex items-center px-3 py-1 bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200 text-xs rounded-full">
                        TURNO: {{ $alunoSelecionado->turma->turno ?? 'N/A' }}
                    </div>
                </div>

                @if ($alunoSelecionado->turma)
                <div class="text-center text-sm text-gray-600 dark:text-gray-400">
                    <div>{{ $alunoSelecionado->turma->escola->nome ?? '' }}</div>
                    <div>{{ $alunoSelecionado->turma->serie->nome ?? '' }} - {{ $alunoSelecionado->turma->turma ?? '' }}</div>
                </div>
                @endif

                {{-- Contato --}}
                <div class="border-t border-gray-200 dark:border-gray-700 pt-4">
                    <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2 flex items-center">
                        <x-heroicon-o-phone class="w-4 h-4 mr-2" /> Contato
                    </h4>
                    <p class="text-xs"><span class="font-medium">Responsável:</span> {{ $alunoSelecionado->nome_responsavel ?? 'N/A' }}</p>
                    <p class="text-xs"><span class="font-medium">Telefone:</span> {{ $alunoSelecionado->telefone_responsavel ?? 'N/A' }}</p>
                    @if ($alunoSelecionado->telefone_aluno)
                    <p class="text-xs"><span class="font-medium">Tel. Aluno:</span> {{ $alunoSelecionado->telefone_aluno }}</p>
                    @endif
                </div>

                {{-- Endereço --}}
                <div class="border-t border-gray-200 dark:border-gray-700 pt-4">
                    <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2 flex items-center">
                        <x-heroicon-o-map-pin class="w-4 h-4 mr-2" /> Endereço
                    </h4>
                    <p class="text-xs">{{ $alunoSelecionado->logradouro ?? 'N/A' }}, {{ $alunoSelecionado->numero ?? '' }}</p>
                    <p class="text-xs">{{ $alunoSelecionado->bairro ?? '' }} - {{ $alunoSelecionado->cidade ?? '' }}/{{ $alunoSelecionado->estado ?? '' }}</p>
                    <p class="text-xs">CEP: {{ $alunoSelecionado->cep ?? 'N/A' }}</p>
                </div>
            </div>

            <!-- Footer -->
            <div class="px-4 py-3 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 flex justify-end flex-shrink-0">
                <button wire:click="fecharDetalhesAluno"
                    class="inline-flex items-center justify-center gap-x-1 rounded-lg border border-gray-300 bg-gray-100 px-4 py-2 text-sm font-medium text-gray-900 shadow-sm transition-colors hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 dark:border-gray-600 dark:bg-gray-800 dark:text-white dark:hover:bg-gray-700">
                    Fechar
                </button>
            </div>
        </div>
    </div>
    @endif

    
    @assets
    <style>
        /* Ativa 30%/70% somente quando a classe existir */
        @media (min-width: 1024px) {
            .lg\:grid-60-40 {
                display: grid;
                grid-template-columns: 60% 40% !important;
            }
        }

    </style>
    @endassets

</x-filament::page>