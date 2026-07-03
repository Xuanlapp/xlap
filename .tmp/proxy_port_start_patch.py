from pathlib import Path
p = Path('resources/views/livewire/pages/proxy/index.blade.php')
text = p.read_text(encoding='utf-8')
text = text.replace('{{ 9800 + $loop->index }}', '{{ 9801 + $loop->index }}')
p.write_text(text, encoding='utf-8')
