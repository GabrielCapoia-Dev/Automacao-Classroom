{{-- resources/views/filament/modals/drive-files.blade.php --}}
<div class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6" wire:ignore.self>
    <!-- Backdrop -->
    <div class="fixed inset-0 bg-black bg-opacity-50 transition-opacity"></div>

    <!-- Modal -->
    <div class="relative w-full max-w-6xl bg-white dark:bg-gray-900 rounded-xl shadow-xl overflow-hidden transform transition-all
                flex flex-col"
        style="width: 95vw; max-width: 1200px; height: 85vh;">

        <!-- Header -->
        <div
            class="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 flex-shrink-0">
            <div class="flex items-center gap-3">
                <img src="{{ asset('images/google-drive-icon.svg') }}" alt="Google Logo" class="inline-block w-5 h-5 mr-2">
                <h3 class="text-xl font-semibold text-gray-900 dark:text-white">
                    Seletor de Planilhas - Google Drive
                </h3>
            </div>
            <button @click="Livewire.emit('closeModal')"
                class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 focus:outline-none p-2 rounded-md hover:bg-gray-100 dark:hover:bg-gray-700">
                <span class="sr-only">Fechar</span>
                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <!-- Body com barra de rolagem interna -->
        <div class="flex-1 p-6 overflow-hidden flex flex-col gap-4">
            <!-- Lista de arquivos -->
            <div class="flex-1 overflow-y-auto custom-scrollbar">
                <livewire:drive-file-picker :model-class="$modelClass" />
            </div>
        </div>

        <!-- Footer -->
        <div
            class="px-6 py-4 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 flex justify-between items-center flex-shrink-0">
            <a href="{{ route('google.redirect') }}"
                class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium bg-white border border-gray-300 rounded-md hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-700">
                <img src="{{ asset('images/google-logo.svg') }}" alt="Google Logo" class="inline-block w-5 h-5 mr-2">
                Selecionar Outra Conta
            </a>

            <a
                href="{{ route('filament.admin.exportar-modelo', ['model' => \App\Models\Escola::class]) }}"
                class="fi-btn inline-flex items-center justify-center gap-x-1 rounded-lg border border-gray-300 bg-gray-100 px-4 py-2 text-sm font-medium text-gray-900 shadow-sm transition-colors hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 dark:border-gray-600 dark:bg-gray-800 dark:text-white dark:hover:bg-gray-700"
                download>
                Baixar modelo
            </a>
        </div>
    </div>
</div>

<style>
    .custom-scrollbar::-webkit-scrollbar {
        width: 6px;
    }

    .custom-scrollbar::-webkit-scrollbar-track {
        background: #f1f1f1;
    }

    .custom-scrollbar::-webkit-scrollbar-thumb {
        background: #c1c1c1;
        border-radius: 3px;
    }

    .custom-scrollbar::-webkit-scrollbar-thumb:hover {
        background: #a8a8a8;
    }

    /* Cards de arquivo */
    .file-card {
        transition: all 0.2s ease-in-out;
        padding: 0.75rem;
        border: 1px solid #e2e8f0;
        border-radius: 0.5rem;
        background-color: #fff;
    }

    .file-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
    }

    .dark .file-card {
        border-color: #374151;
        background-color: #1f2937;
    }

    .dark .file-card:hover {
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.3);
    }
</style>