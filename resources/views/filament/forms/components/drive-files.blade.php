<div class="space-y-2">
    @if(!empty($getState()))
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
            @foreach($getState() as $arquivo)
                <a 
                    href="{{ $arquivo['webViewLink'] ?? '#' }}" 
                    target="_blank"
                    class="flex items-center gap-3 p-3 rounded-lg border border-gray-700 bg-gray-800 hover:bg-gray-750 hover:border-gray-600 transition-all cursor-pointer group"
                >
                    <div class="flex-shrink-0">
                        @php
                            $iconClass = match($arquivo['tipo']) {
                                'pdf' => 'text-red-500',
                                'doc', 'docx' => 'text-blue-500',
                                'xls', 'xlsx' => 'text-green-500',
                                'ppt', 'pptx' => 'text-orange-500',
                                'jpg', 'jpeg', 'png', 'gif' => 'text-purple-500',
                                'mp4', 'mpeg', 'mov' => 'text-pink-500',
                                'folder' => 'text-yellow-500',
                                'zip' => 'text-indigo-500',
                                default => 'text-gray-400'
                            };
                        @endphp
                        <x-filament::icon 
                            :icon="$arquivo['icone']" 
                            class="w-10 h-10 {{ $iconClass }}"
                        />
                    </div>
                    
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-gray-200 truncate group-hover:text-white transition-colors">
                            {{ $arquivo['nome'] }}
                        </p>
                        <p class="text-xs text-gray-400">
                            {{ strtoupper($arquivo['tipo']) }} • {{ $arquivo['tamanho'] }}
                        </p>
                    </div>

                    <div class="flex-shrink-0 opacity-0 group-hover:opacity-100 transition-opacity">
                        <x-filament::icon 
                            icon="heroicon-o-arrow-top-right-on-square" 
                            class="w-4 h-4 text-gray-400"
                        />
                    </div>
                </a>
            @endforeach
        </div>
    @else
        <div class="text-center py-8 text-gray-400">
            <x-filament::icon icon="heroicon-o-folder-open" class="w-12 h-12 mx-auto mb-2 text-gray-600"/>
            <p>Nenhum arquivo carregado</p>
            <p class="text-xs mt-1">Clique no botão "Carregar" para buscar os arquivos do Drive</p>
        </div>
    @endif
</div>