<div class="space-y-6">
    <div>
        <h3 class="text-lg font-semibold mb-2">Informações da Atividade</h3>
        <dl class="grid grid-cols-2 gap-4">
            <div>
                <dt class="text-sm font-medium text-gray-500">Título</dt>
                <dd class="text-sm text-gray-900">{{ $atividade->titulo }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500">Série</dt>
                <dd class="text-sm text-gray-900">{{ $atividade->serie->nome }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500">Turma</dt>
                <dd class="text-sm text-gray-900">{{ $atividade->turma->nome }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500">Criado em</dt>
                <dd class="text-sm text-gray-900">{{ $atividade->created_at->format('d/m/Y H:i') }}</dd>
            </div>
        </dl>
    </div>

    @if($atividade->descricao)
    <div>
        <h3 class="text-lg font-semibold mb-2">Descrição</h3>
        <p class="text-sm text-gray-700">{{ $atividade->descricao }}</p>
    </div>
    @endif

    <div>
        <h3 class="text-lg font-semibold mb-2">Escolas ({{ $atividade->escolas->count() }})</h3>
        <ul class="space-y-2">
            @foreach($atividade->escolas as $escola)
                <li class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                    <span class="text-sm font-medium">{{ $escola->nome }}</span>
                    <span class="text-xs text-gray-500">
                        ID: {{ $escola->pivot->classroom_coursework_id }}
                    </span>
                </li>
            @endforeach
        </ul>
    </div>

    <div>
        <h3 class="text-lg font-semibold mb-2">Professores ({{ $atividade->professores->count() }})</h3>
        <div class="grid grid-cols-2 gap-2">
            @foreach($atividade->professores as $professor)
                <div class="p-3 bg-gray-50 rounded-lg">
                    <p class="text-sm font-medium">{{ $professor->nome }}</p>
                    <p class="text-xs text-gray-500">{{ $professor->email }}</p>
                </div>
            @endforeach
        </div>
    </div>
</div>