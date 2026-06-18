<section class="min-h-[calc(100vh-4rem)] bg-[#f3f4f6] px-4 py-6 text-slate-950 sm:px-6 lg:px-8">
    <div class="mx-auto grid max-w-5xl gap-5 lg:grid-cols-[minmax(0,1fr)_22rem]">
        <form wire:submit.prevent="send" class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-5 py-4">
                <p class="text-xs font-bold uppercase tracking-wide text-cyan-600">Admin</p>
                <h1 class="mt-1 text-lg font-bold text-slate-950">Send Mail Test</h1>
                <p class="mt-1 text-sm text-slate-500">Gui email test qua SMTP hien tai de kiem tra Inbox/Spam.</p>
            </div>

            <div class="grid gap-4 px-5 py-5">
                @if ($successMessage)
                    <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">
                        {{ $successMessage }}
                    </div>
                @endif

                @if ($errorMessage)
                    <div class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">
                        {{ $errorMessage }}
                    </div>
                @endif

                <div>
                    <label for="mailTestTo" class="text-sm font-semibold text-slate-700">Email nhan</label>
                    <input
                        id="mailTestTo"
                        wire:model="to"
                        type="email"
                        class="mt-1 h-11 w-full rounded-md border-slate-300 bg-white text-sm text-slate-950 shadow-sm focus:border-cyan-500 focus:ring-cyan-500"
                        placeholder="name@example.com"
                    >
                    @error('to') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="mailTestSubject" class="text-sm font-semibold text-slate-700">Subject</label>
                    <input
                        id="mailTestSubject"
                        wire:model="subject"
                        type="text"
                        class="mt-1 h-11 w-full rounded-md border-slate-300 bg-white text-sm text-slate-950 shadow-sm focus:border-cyan-500 focus:ring-cyan-500"
                    >
                    @error('subject') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="mailTestBody" class="text-sm font-semibold text-slate-700">Noi dung</label>
                    <textarea
                        id="mailTestBody"
                        wire:model="body"
                        rows="8"
                        class="mt-1 w-full rounded-md border-slate-300 bg-white text-sm text-slate-950 shadow-sm focus:border-cyan-500 focus:ring-cyan-500"
                    ></textarea>
                    @error('body') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="flex items-center justify-end border-t border-slate-200 bg-slate-50 px-5 py-4">
                <button
                    type="submit"
                    wire:loading.attr="disabled"
                    wire:target="send"
                    class="inline-flex h-10 items-center justify-center rounded-md bg-slate-900 px-5 text-sm font-bold text-white transition hover:bg-slate-700 disabled:cursor-wait disabled:opacity-60"
                >
                    <span wire:loading.remove wire:target="send">Gui mail test</span>
                    <span wire:loading wire:target="send">Dang gui...</span>
                </button>
            </div>
        </form>

        <aside class="h-fit rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-sm font-bold text-slate-950">Mail config dang doc</h2>
            <dl class="mt-4 space-y-3 text-sm">
                @foreach ($mailConfig as $label => $value)
                    <div>
                        <dt class="text-xs font-bold uppercase tracking-wide text-slate-400">{{ $label }}</dt>
                        <dd class="mt-1 break-all font-medium text-slate-700">{{ $value ?: '-' }}</dd>
                    </div>
                @endforeach
            </dl>
            <p class="mt-5 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-xs font-medium text-amber-700">
                Neu trang bao gui thanh cong nhung Gmail khong thay Inbox, hay check Spam/All Mail va log mail server.
            </p>
        </aside>
    </div>
</section>
