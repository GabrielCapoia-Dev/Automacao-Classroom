<x-filament-panels::page>
    <x-filament::section>
        <div class="flex items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <img src="{{ asset('images/google-logo.svg') }}" alt="Google" class="w-8 h-8">

                <div>
                    <p class="text-sm font-medium text-gray-900 dark:text-white">
                        Conta Google Classroom
                    </p>

                    @if($googleAccount)
                        <p class="text-xs text-gray-600 dark:text-gray-400">
                            {{ $googleAccount->email }}
                        </p>
                    @else
                        <p class="text-xs text-gray-500 dark:text-gray-500">
                            Não conectada
                        </p>
                    @endif
                </div>
            </div>

            <div class="flex items-center gap-3">
                @if($googleAccount)
                    <x-filament::button
                        color="info"
                        size="sm"
                        tag="a"
                        href="{{ route('google.main.connect') }}"
                    >
                        Trocar conta
                    </x-filament::button>
                @else
                    <x-filament::button
                        color="primary"
                        size="sm"
                        tag="a"
                        href="{{ route('google.main.connect') }}"
                    >
                        Conectar
                    </x-filament::button>
                @endif
            </div>
        </div>

        @if($googleAccount)
            <p class="mt-3 text-xs text-gray-500">
                Ao trocar a conta, a atual será substituída e a nova passará a ser a conta principal do sistema.
            </p>
        @endif
    </x-filament::section>
</x-filament-panels::page>
