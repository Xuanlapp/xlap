from pathlib import Path
p = Path('resources/views/livewire/pages/proxy/index.blade.php')
text = p.read_text(encoding='utf-8')
old = """<button type=\"button\" class=\"font-mono text-xs font-semibold text-cyan-700 underline decoration-dotted decoration-cyan-300 underline-offset-2 hover:text-cyan-900\" x-on:click.stop=\"navigator.clipboard.writeText(@js($item->public_ip)).then(() => window.dispatchEvent(new CustomEvent('toast', { detail: { type: 'success', title: 'Copied', message: 'Da copy Public IP vao clipboard.' } })))\">{{ $item->public_ip }}</button>"""
new = """<button
                                                        type=\"button\"
                                                        class=\"font-mono text-xs font-semibold text-cyan-700 underline decoration-dotted decoration-cyan-300 underline-offset-2 hover:text-cyan-900\"
                                                        x-on:click.stop=\"
                                                            const value = @js($item->public_ip);
                                                            const notify = (type, title, message) => window.dispatchEvent(new CustomEvent('toast', { detail: { type, title, message } }));
                                                            const fallbackCopy = () => {
                                                                const input = document.createElement('textarea');
                                                                input.value = value;
                                                                input.setAttribute('readonly', 'readonly');
                                                                input.style.position = 'fixed';
                                                                input.style.left = '-9999px';
                                                                document.body.appendChild(input);
                                                                input.select();
                                                                const copied = document.execCommand('copy');
                                                                document.body.removeChild(input);
                                                                if (copied) {
                                                                    notify('success', 'Copied', 'Da copy Public IP vao clipboard.');
                                                                } else {
                                                                    notify('error', 'Copy failed', 'Trinh duyet khong cho copy clipboard.');
                                                                }
                                                            };
                                                            if (navigator.clipboard && window.isSecureContext) {
                                                                navigator.clipboard.writeText(value)
                                                                    .then(() => notify('success', 'Copied', 'Da copy Public IP vao clipboard.'))
                                                                    .catch(fallbackCopy);
                                                            } else {
                                                                fallbackCopy();
                                                            }
                                                        \"
                                                    >{{ $item->public_ip }}</button>"""
if old not in text:
    raise SystemExit('copy button pattern not found')
text = text.replace(old, new, 1)
p.write_text(text, encoding='utf-8')
