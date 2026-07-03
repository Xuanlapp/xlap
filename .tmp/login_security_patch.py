from pathlib import Path

# LoginSecurity messages and IP block wording
p = Path('app/Support/LoginSecurity.php')
text = p.read_text(encoding='utf-8')
text = text.replace("private const MAX_IP_ATTEMPTS = 20;", "private const MAX_IP_ATTEMPTS = 10;")
text = text.replace("$errorKey => trans('auth.failed'),", "$errorKey => 'Sai tài khoản hoặc mật khẩu.',")
text = text.replace("$errorKey => 'Vui lòng hoàn tất xác minh bảo mật.',", "$errorKey => 'Vui lòng tích xác minh bảo mật Cloudflare.',")
text = text.replace("    private function throttleMessage(string $key): string\n    {\n        $seconds = RateLimiter::availableIn($key);\n\n        return trans('auth.throttle', [\n            'seconds' => $seconds,\n            'minutes' => ceil($seconds / 60),\n        ]);\n    }", "    private function throttleMessage(string $key): string\n    {\n        $seconds = RateLimiter::availableIn($key);\n        $minutes = max(1, (int) ceil($seconds / 60));\n\n        return \"Bạn đăng nhập sai quá nhiều lần nên IP/tài khoản tạm bị khóa. Vui lòng thử lại sau {$minutes} phút.\";\n    }")
p.write_text(text, encoding='utf-8')

# LoginForm invalid credentials message
p = Path('app/Livewire/Forms/LoginForm.php')
text = p.read_text(encoding='utf-8')
text = text.replace("'form.password' => trans('auth.failed'),", "'form.password' => 'Sai tài khoản hoặc mật khẩu.',")
p.write_text(text, encoding='utf-8')

# LoginController invalid credentials message too (POST fallback)
p = Path('app/Http/Controllers/Auth/LoginController.php')
text = p.read_text(encoding='utf-8')
text = text.replace("'password' => trans('auth.failed'),", "'password' => 'Sai tài khoản hoặc mật khẩu.',")
p.write_text(text, encoding='utf-8')

# Login blade: use Livewire submit, better error grouping and loading on submit/enter
p = Path('resources/views/livewire/pages/auth/login.blade.php')
text = p.read_text(encoding='utf-8')
text = text.replace('<form class="mt-6 space-y-4" method="POST" action="{{ route(\'login\', absolute: false) }}">', '<form class="mt-6 space-y-4" wire:submit.prevent="login">')
text = text.replace("                @csrf\n", "")
text = text.replace("                    $authFailedMessage = trans('auth.failed');\n", "                    $authFailedMessage = 'Sai tài khoản hoặc mật khẩu.';\n")
text = text.replace("                    $turnstileMessages = collect($errors->get('form.turnstileToken'))\n                        ->merge($loginMessages->filter(fn ($message) => str_contains($message, 'xac minh') || str_contains($message, 'xÃƒÂ¡c minh') || str_contains($message, 'security')));", "                    $turnstileMessages = collect($errors->get('form.turnstileToken'))\n                        ->merge($loginMessages->filter(fn ($message) => str_contains($message, 'xác minh') || str_contains($message, 'Cloudflare') || str_contains($message, 'security')));")
text = text.replace('wire:loading.attr="disabled"\n                    wire:target="login"', 'wire:loading.attr="disabled"\n                    wire:target="login"')
# disable fields during login
text = text.replace('wire:model="form.login"\n                            id="login"', 'wire:model="form.login"\n                            wire:loading.attr="disabled"\n                            wire:target="login"\n                            id="login"')
text = text.replace('wire:model="form.password"\n                            id="password"', 'wire:model="form.password"\n                            wire:loading.attr="disabled"\n                            wire:target="login"\n                            id="password"')
text = text.replace('wire:model="form.remember"\n                            id="remember"', 'wire:model="form.remember"\n                            wire:loading.attr="disabled"\n                            wire:target="login"\n                            id="remember"')
p.write_text(text, encoding='utf-8')
