<div>
    @if ($isOpen)
        <div
            class="fixed inset-0 z-50 flex h-full w-full items-start justify-center overflow-y-auto bg-slate-950/70 p-4 backdrop-blur-sm"
            role="dialog"
            aria-modal="true"
        >
            <button type="button" class="fixed inset-0 cursor-default focus:outline-none" wire:click="close" aria-label="Close add user modal"></button>

            <form wire:submit.prevent="save" class="relative overflow-y-auto my-6 w-full max-w-7xl overflow-hidden rounded-2xl border border-slate-200 bg-white text-slate-950 shadow-2xl">
                <div class="flex items-start justify-between border-b border-slate-200 px-6 py-5">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-wide text-cyan-600">Admin</p>
                        <h2 class="mt-1 text-xl font-bold">Add user</h2>
                        <p class="mt-1 text-sm text-slate-500">Tao account, gan workspace, tool va API trong mot luong.</p>
                    </div>
                    <button type="button" wire:click="close" class="rounded-full p-2 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700 focus:outline-none">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path d="M18 6 6 18" />
                            <path d="m6 6 12 12" />
                        </svg>
                    </button>
                </div>

                <div class="max-h-[calc(100vh-12rem)] overflow-y-auto px-6 py-5">
                    <div class="grid gap-4 xl:grid-cols-3">
                        <section class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                            <h3 class="text-sm font-bold text-slate-900">Account info</h3>
                            <div class="mt-3 grid gap-2">
                                <div class="rounded-lg bg-white px-3 py-2 shadow-sm">
                                    <label for="addUserName" class="text-xs font-semibold text-slate-500">Ten user</label>
                                    <input id="addUserName" wire:model="name" type="text" class="mt-1 h-9 w-full rounded-md border-slate-200 text-sm text-slate-950 shadow-sm focus:border-cyan-500 focus:ring-cyan-500" autocomplete="name">
                                    @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                </div>
                                <div class="rounded-lg bg-white px-3 py-2 shadow-sm">
                                    <label for="addUserUsername" class="text-xs font-semibold text-slate-500">Ten dang nhap</label>
                                    <input id="addUserUsername" wire:model="username" type="text" class="mt-1 h-9 w-full rounded-md border-slate-200 text-sm text-slate-950 shadow-sm focus:border-cyan-500 focus:ring-cyan-500" autocomplete="username">
                                    @error('username') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                </div>
                                <div class="rounded-lg bg-white px-3 py-2 shadow-sm">
                                    <label for="addUserEmail" class="text-xs font-semibold text-slate-500">Email</label>
                                    <input id="addUserEmail" wire:model="email" type="email" class="mt-1 h-9 w-full rounded-md border-slate-200 text-sm text-slate-950 shadow-sm focus:border-cyan-500 focus:ring-cyan-500" autocomplete="email">
                                    @error('email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                </div>
                                <div class="rounded-lg bg-white px-3 py-2 shadow-sm">
                                    <div class="flex items-center justify-between gap-2">
                                        <label for="addUserPassword" class="text-xs font-semibold text-slate-500">Mat khau tam</label>
                                        <button type="button" wire:click="generatePassword" class="rounded-md border border-cyan-200 bg-cyan-50 px-2 py-1 text-[11px] font-bold text-cyan-700 transition hover:bg-cyan-100">Random</button>
                                    </div>
                                    <input id="addUserPassword" wire:model="password" type="text" class="mt-1 h-9 w-full rounded-md border-slate-200 text-sm text-slate-950 shadow-sm focus:border-cyan-500 focus:ring-cyan-500" autocomplete="new-password">
                                    @error('password') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                </div>
                            </div>
                            <p class="mt-2 text-xs text-slate-500">Mat khau toi thieu 8 ky tu.</p>
                        </section>


                        

                        <section class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                            <h3 class="text-sm font-bold text-slate-900">Account & marketplace</h3>
                            <div class="mt-3 grid gap-2">
                                <label class="flex items-center gap-2 rounded-lg bg-white px-3 py-2 text-sm shadow-sm">
                                    <input wire:model="status" type="radio" value="active" class="border-slate-300 text-cyan-600">
                                    <span>Active</span>
                                </label>
                                <label class="flex items-center gap-2 rounded-lg bg-white px-3 py-2 text-sm shadow-sm">
                                    <input wire:model="status" type="radio" value="inactive" class="border-slate-300 text-cyan-600">
                                    <span>Inactive</span>
                                </label>
                                <div class="rounded-lg bg-white px-3 py-2 shadow-sm">
                                    <label class="text-xs font-semibold text-slate-500">Role</label>
                                    <select wire:model="role" class="mt-1 h-9 w-full rounded-md border-slate-200 text-sm text-slate-950">
                                        <option value="user">User</option>
                                        <option value="manager">Manager</option>
                                        <option value="admin">Admin</option>
                                    </select>
                                </div>
                                
                                <label class="flex items-center gap-2 rounded-lg bg-white px-3 py-2 text-sm shadow-sm">
                                    <input wire:model.live="can_generate_amazon_listing" type="checkbox" class="rounded border-slate-300 text-cyan-600">
                                    <span>Amazon listing metadata</span>
                                </label>
                                <label class="flex items-center gap-2 rounded-lg bg-white px-3 py-2 text-sm shadow-sm">
                                    <input wire:model.live="can_generate_etsy_listing" type="checkbox" class="rounded border-slate-300 text-cyan-600">
                                    <span>Etsy listing metadata</span>
                                </label>
                                <label class="flex items-center gap-2 rounded-lg bg-white px-3 py-2 text-sm shadow-sm">
                                    <input wire:model="can_access_wali" type="checkbox" class="rounded border-slate-300 text-cyan-600">
                                    <span>Wali</span>
                                </label>
                            </div>
                            @error('status') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                            @error('can_generate_amazon_listing') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                            @error('can_generate_etsy_listing') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                            @error('can_access_wali') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                        </section>



                        <section class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                            <h3 class="text-sm font-bold text-slate-900">API credentials</h3>
                            <div class="mt-3 space-y-4">
                                <div class="rounded-xl border border-slate-200 bg-white p-4">
                                    <div class="flex items-center justify-between gap-3">
                                        <div>
                                            <p class="text-sm font-semibold text-slate-900">Vertex image key</p>
                                            <p class="mt-1 text-xs text-slate-500">Them key moi hoac copy tu user khac.</p>
                                        </div>
                                    </div>
                                    <div class="mt-3 grid gap-2 sm:grid-cols-3">
                                        <label class="flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-sm">
                                            <input wire:model.live="vertexMode" type="radio" value="none" class="border-slate-300 text-cyan-600">
                                            <span>Khong them</span>
                                        </label>
                                        <label class="flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-sm">
                                            <input wire:model.live="vertexMode" type="radio" value="new" class="border-slate-300 text-cyan-600">
                                            <span>Add key moi</span>
                                        </label>
                                        <label class="flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-sm">
                                            <input wire:model.live="vertexMode" type="radio" value="copy" class="border-slate-300 text-cyan-600">
                                            <span>Copy tu user khac</span>
                                        </label>
                                    </div>
                                    @if ($vertexMode === 'new')
                                        <div class="mt-3 grid gap-3">
                                            <div>
                                                <label class="text-xs font-semibold text-slate-500">Vertex location</label>
                                                <input wire:model="vertexLocation" type="text" class="mt-1 h-9 w-full rounded-md border-slate-200 text-sm text-slate-950" placeholder="global">
                                                @error('vertexLocation') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                            </div>
                                            <div>
                                                <label class="text-xs font-semibold text-slate-500">Credentials JSON</label>
                                                <textarea wire:model="vertexJson" rows="8" class="mt-1 w-full rounded-md border-slate-200 text-sm text-slate-950" placeholder='{"type":"service_account",...}'></textarea>
                                                @error('vertexJson') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                            </div>
                                        </div>
                                    @endif
                                    @if ($vertexMode === 'copy')
                                        <div class="mt-3 grid gap-3 sm:grid-cols-2">
                                            <div>
                                                <label class="text-xs font-semibold text-slate-500">Copy Vertex tu user</label>
                                                <select wire:model="vertexCopyUserId" class="mt-1 h-9 w-full rounded-md border-slate-200 text-sm text-slate-950">
                                                    <option value="">Chon user</option>
                                                    @foreach ($copyableVertexUsers as $copyUser)
                                                        <option value="{{ $copyUser->id }}">#{{ $copyUser->id }} - {{ $copyUser->name }} ({{ $copyUser->email }})</option>
                                                    @endforeach
                                                </select>
                                                @error('vertexCopyUserId') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                            </div>
                                            <div>
                                                <label class="text-xs font-semibold text-slate-500">Vertex location</label>
                                                <input wire:model="vertexLocation" type="text" class="mt-1 h-9 w-full rounded-md border-slate-200 text-sm text-slate-950" placeholder="global">
                                            </div>
                                        </div>
                                    @endif
                                </div>

                                <div class="rounded-xl border border-slate-200 bg-white p-4">
                                    <div>
                                        <p class="text-sm font-semibold text-slate-900">v98Store key</p>
                                        <p class="mt-1 text-xs text-slate-500">Add API key moi hoac copy tu user khac.</p>
                                    </div>
                                    <div class="mt-3 grid gap-2 sm:grid-cols-3">
                                        <label class="flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-sm">
                                            <input wire:model.live="v98StoreMode" type="radio" value="none" class="border-slate-300 text-cyan-600">
                                            <span>Khong them</span>
                                        </label>
                                        <label class="flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-sm">
                                            <input wire:model.live="v98StoreMode" type="radio" value="new" class="border-slate-300 text-cyan-600">
                                            <span>Add key moi</span>
                                        </label>
                                        <label class="flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-sm">
                                            <input wire:model.live="v98StoreMode" type="radio" value="copy" class="border-slate-300 text-cyan-600">
                                            <span>Copy tu user khac</span>
                                        </label>
                                    </div>
                                    @if ($v98StoreMode === 'new')
                                        <div class="mt-3">
                                            <label class="text-xs font-semibold text-slate-500">v98Store API key</label>
                                            <textarea wire:model="v98StoreApiKey" rows="4" class="mt-1 w-full rounded-md border-slate-200 text-sm text-slate-950" placeholder="Nhap v98Store API key"></textarea>
                                            @error('v98StoreApiKey') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                        </div>
                                    @endif
                                    @if ($v98StoreMode === 'copy')
                                        <div class="mt-3">
                                            <label class="text-xs font-semibold text-slate-500">Copy v98Store tu user</label>
                                            <select wire:model="v98StoreCopyUserId" class="mt-1 h-9 w-full rounded-md border-slate-200 text-sm text-slate-950">
                                                <option value="">Chon user</option>
                                                @foreach ($copyableV98StoreUsers as $copyUser)
                                                    <option value="{{ $copyUser->id }}">#{{ $copyUser->id }} - {{ $copyUser->name }} ({{ $copyUser->email }})</option>
                                                @endforeach
                                            </select>
                                            @error('v98StoreCopyUserId') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </section>

                        <section class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                            <h3 class="text-sm font-bold text-slate-900">Products & tools</h3>
                            <div class="mt-3 grid gap-2 sm:grid-cols-2">
                                @foreach ($products as $product)
                                    <label class="flex items-center gap-2 rounded-lg bg-white px-3 py-2 text-sm shadow-sm">
                                        <input wire:model="selectedProducts" type="checkbox" value="{{ $product->id }}" class="rounded border-slate-300 text-cyan-600">
                                        <span>{{ $product->display_name }}</span>
                                    </label>
                                @endforeach
                            </div>
                            @error('selectedProducts') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                            @error('selectedProducts.*') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                        </section>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3 border-t border-slate-200 bg-slate-50 px-6 py-4">
                    <button type="button" wire:click="close" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-100">
                        Close
                    </button>
                    <button type="submit" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-100" wire:loading.attr="disabled" wire:target="save">
                        <span wire:loading.remove wire:target="save">Create user</span>
                        <span wire:loading wire:target="save">Creating...</span>
                    </button>
                </div>
            </form>
        </div>
    @endif
</div>
