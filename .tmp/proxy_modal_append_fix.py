from pathlib import Path
p = Path('resources/views/livewire/pages/proxy/index.blade.php')
text = p.read_text(encoding='utf-8')
text = text.replace('x-on:click="openingProxyModal = true"', 'x-on:click="openingProxyModal = true; setTimeout(() => openingProxyModal = false, 900)"')
text = text.replace('                    <livewire:modals.proxy.edit-proxy-item />\n', '')
if '<livewire:modals.proxy.edit-proxy-item />' not in text:
    idx = text.rfind('\n</div>')
    text = text[:idx] + '\n    <livewire:modals.proxy.edit-proxy-item />' + text[idx:]
p.write_text(text, encoding='utf-8')
