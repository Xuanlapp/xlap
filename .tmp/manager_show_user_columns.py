from pathlib import Path
for path in ['resources/views/livewire/pages/ornament-amazon/automation-catalog.blade.php','resources/views/livewire/pages/marketplace/marketplace-exports.blade.php','resources/views/livewire/pages/drive/drive-uploads.blade.php']:
    p=Path(path)
    text=p.read_text(encoding='utf-8')
    text=text.replace('auth()->user()->is_admin', '(auth()->user()->is_admin || auth()->user()->isManager())')
    p.write_text(text, encoding='utf-8')
