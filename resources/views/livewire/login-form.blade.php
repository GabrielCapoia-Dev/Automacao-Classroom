<!-- resources/views/livewire/login-form.blade.php -->

<form wire:submit.prevent="authenticate" class="login-form">

    <div class="form-group">
        <label for="email" class="form-label">E-mail</label>
        <input id="email" type="email" class="form-input" wire:model.defer="email" required autofocus placeholder="Digite seu e-mail" />
        @error('email') <span class="error-message">{{ $message }}</span> @enderror
    </div>

    <div class="form-group">
        <label for="password" class="form-label">Senha</label>
        <input id="password" type="password" class="form-input" wire:model.defer="password" required placeholder="Digite sua senha" />
        @error('password') <span class="error-message">{{ $message }}</span> @enderror
    </div>

    <div class="remember-me mt-4">
        <input type="checkbox" id="remember" class="checkbox" wire:model="remember">
        <label for="remember" class="checkbox-label">Lembrar de mim</label>
    </div>

    <button type="submit" class="login-button" wire:loading.class="loading">
        <span wire:loading.remove>🔐 Entrar no Sistema</span>
        <span wire:loading>Entrando...</span>
    </button>
</form>
