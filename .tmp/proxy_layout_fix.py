from pathlib import Path
p = Path('resources/views/livewire/pages/proxy/index.blade.php')
text = p.read_text(encoding='utf-8')
# rebuild top portion
start = "<div x-data=\"{ openingProxyModal: false }\" x-on:open-modal.window=\"if ($event.detail.component === 'modals.proxy.edit-proxy-item') { openingProxyModal = true; setTimeout(() => openingProxyModal = false, 900) }\" class=\"min-h-screen bg-slate-100 px-4 py-6 text-slate-950 sm:px-6 lg:px-8\">\n    <div x-show=\"openingProxyModal\" x-cloak class=\"fixed inset-0 z-40 flex items-center justify-center bg-slate-950/35 backdrop-blur-[1px]\">\n        <div class=\"flex items-center gap-3 rounded-2xl bg-white px-5 py-4 text-sm font-semibold text-slate-700 shadow-2xl\">\n            <svg class=\"h-5 w-5 animate-spin text-cyan-600\" viewBox=\"0 0 24 24\" fill=\"none\" aria-hidden=\"true\">\n                <circle class=\"opacity-25\" cx=\"12\" cy=\"12\" r=\"10\" stroke=\"currentColor\" stroke-width=\"4\"></circle>\n                <path class=\"opacity-75\" fill=\"currentColor\" d=\"M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4z\"></path>\n            </svg>\n            Dang mo modal...\n            <livewire:modals.proxy.edit-proxy-item />\n</div>\n    </div>\n    <div class=\"mx-auto max-w-7xl space-y-6\">"
replacement = "<div x-data=\"{ openingProxyModal: false }\" x-on:open-modal.window=\"if ($event.detail.component === 'modals.proxy.edit-proxy-item') { openingProxyModal = true; setTimeout(() => openingProxyModal = false, 900) }\" class=\"min-h-screen bg-slate-100 px-4 py-6 text-slate-950 sm:px-6 lg:px-8\">\n    <div x-show=\"openingProxyModal\" x-cloak class=\"fixed inset-0 z-40 flex items-center justify-center bg-slate-950/35 backdrop-blur-[1px]\">\n        <div class=\"flex items-center gap-3 rounded-2xl bg-white px-5 py-4 text-sm font-semibold text-slate-700 shadow-2xl\">\n            <svg class=\"h-5 w-5 animate-spin text-cyan-600\" viewBox=\"0 0 24 24\" fill=\"none\" aria-hidden=\"true\">\n                <circle class=\"opacity-25\" cx=\"12\" cy=\"12\" r=\"10\" stroke=\"currentColor\" stroke-width=\"4\"></circle>\n                <path class=\"opacity-75\" fill=\"currentColor\" d=\"M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4z\"></path>\n            </svg>\n            Dang mo modal...\n        </div>\n    </div>\n    <div class=\"mx-auto max-w-7xl space-y-6\">"
if start in text:
    text = text.replace(start, replacement, 1)
# ensure modal exists only once and is before final root close
text = text.replace('\n    <livewire:modals.proxy.edit-proxy-item />\n</div>\n', '\n')
if text.count('<livewire:modals.proxy.edit-proxy-item />') == 0:
    text = text.rstrip()[:-6] + "\n    <livewire:modals.proxy.edit-proxy-item />\n</div>\n"
# more robust append if absent at end
if text.rstrip().endswith('</div>') and text.count('<livewire:modals.proxy.edit-proxy-item />') == 0:
    text = text[:-6] + '    <livewire:modals.proxy.edit-proxy-item />\n</div>\n'
# if modal still exists in wrong place, place one before last closing div
text = text.replace('            <livewire:modals.proxy.edit-proxy-item />\n', '')
if '<livewire:modals.proxy.edit-proxy-item />' not in text:
    idx = text.rfind('\n</div>\n')
    if idx != -1:
        text = text[:idx] + '\n    <livewire:modals.proxy.edit-proxy-item />' + text[idx:]
# ensure row click overlay and modal dispatch
text = text.replace("x-on:click=\"openingProxyModal = true; setTimeout(() => openingProxyModal = false, 900)\" wire:click=\"$dispatch('openModal', { component: 'modals.proxy.edit-proxy-item', arguments: { itemId: {{ $item->id }} } })\"", "x-on:click=\"openingProxyModal = true; setTimeout(() => openingProxyModal = false, 900)\" wire:click=\"$dispatch('openModal', { component: 'modals.proxy.edit-proxy-item', arguments: { itemId: {{ $item->id }} } })\"")
p.write_text(text, encoding='utf-8')
