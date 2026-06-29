<section class="min-h-[calc(100vh-4rem)] bg-[#f3f4f6] text-slate-950">
    <div class="mx-auto max-w-[1520px] px-4 py-5 sm:px-6 lg:px-8">
        <div class="mb-4 overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-col gap-3 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex min-w-0 items-center gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-cyan-50 text-cyan-600">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 6.75a4.5 4.5 0 1 1-9 0 4.5 4.5 0 0 1 9 0Z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 20.25a7.5 7.5 0 0 1 15 0" />
                        </svg>
                    </div>
                    <div class="min-w-0">
                        <h1 class="text-base font-bold text-slate-950">User access</h1>
                        <p class="mt-0.5 text-xs text-slate-500">Quan ly user va cac chuc nang duoc phep su dung.</p>
                    </div>
                </div>

                <div class="flex flex-col items-start gap-2 sm:items-end">
                <div class="flex flex-wrap gap-2">
                    <a
                        href="{{ route('offorest.admin.google-drive.connect', [], false) }}"
                        class="inline-flex h-9 items-center justify-center rounded-md border border-slate-200 bg-white px-3 text-xs font-semibold text-slate-700 transition hover:border-indigo-200 hover:bg-indigo-50 hover:text-indigo-700"
                    >
                        {{ $googleDriveConnection ? 'Reconnect Google Drive' : 'Connect Google Drive' }}
                    </a>

                    <button
                        type="button"
                        wire:click="uploadApprovedImagesToDrive"
                        wire:loading.attr="disabled"
                        wire:target="uploadApprovedImagesToDrive"
                        class="inline-flex h-9 items-center justify-center rounded-md bg-emerald-500 px-3 text-xs font-bold text-white shadow-sm transition hover:bg-emerald-600 disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        <span wire:loading.remove wire:target="uploadApprovedImagesToDrive">Upload images to Drive</span>
                        <span wire:loading wire:target="uploadApprovedImagesToDrive">Uploading...</span>
                    </button>

                    <button
                        type="button"
                        wire:click="$dispatch('openModal', { component: 'modals.admin.add-user' })"
                        class="inline-flex h-9 items-center justify-center rounded-md bg-cyan-500 px-3 text-xs font-bold text-white shadow-sm transition hover:bg-cyan-600 focus:outline-none focus:ring-4 focus:ring-cyan-200"
                    >
                        Add user
                    </button>
                </div>

                @if ($googleDriveConnection)
                    <p class="text-xs text-emerald-600">
                        Google Drive connected by {{ $googleDriveConnection->user?->email }}.
                    </p>
                @else
                    <p class="text-xs text-amber-600">
                        Chua connect OAuth. Upload se fallback service account neu con cau hinh.
                    </p>
                @endif

                <p class="max-w-xl break-all text-xs text-slate-400">
                    OAuth callback: {{ request()->getSchemeAndHttpHost() }}{{ route('offorest.admin.google-drive.callback', [], false) }}
                </p>
                </div>
            </div>
        </div>

        @if (session('google_drive_status'))
            <div class="mt-4 rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-700">
                {{ session('google_drive_status') }}
            </div>
        @endif

        @if (session('google_drive_error'))
            <div class="mt-4 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
                {{ session('google_drive_error') }}
            </div>
        @endif

        @if ($driveUploadStatus)
            <div class="mt-4 rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-700">
                {{ $driveUploadStatus }}
            </div>
        @endif

        @if ($driveUploadError)
            <div class="mt-4 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
                {{ $driveUploadError }}
            </div>
        @endif

        <div
            x-data="{ success: '', error: '' }"
            x-on:drive-upload-finished.window="success = $event.detail.message; error = ''; setTimeout(() => success = '', 5000)"
            x-on:drive-upload-failed.window="error = $event.detail.message; success = ''"
            class="mt-4 space-y-2"
        >
            <div x-show="success" x-cloak class="rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-700" x-text="success"></div>
            <div x-show="error" x-cloak class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700" x-text="error"></div>
        </div>

        <form wire:submit.prevent="saveMarketplaceVertexCredential" class="mt-4 rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h2 class="text-base font-bold text-slate-950">Vertex API cho title/listing</h2>
                    <p class="mt-1 text-sm text-slate-500">Key nay chi dung de tao Amazon/Etsy title metadata. User khong dung key nay de tao anh.</p>
                </div>

                <span class="inline-flex w-fit rounded-full px-3 py-1 text-xs font-semibold {{ $marketplaceVertexCredential ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' }}">
                    {{ $marketplaceVertexCredential ? 'Configured' : 'Not configured' }}
                </span>
            </div>

            @if ($marketplaceVertexCredential)
                <p class="mt-3 break-all text-xs text-slate-400">
                    Active credential: {{ $marketplaceVertexCredential->client_email }} | {{ $marketplaceVertexCredential->project_id ?: 'no project_id' }} | {{ $marketplaceVertexCredential->location ?: 'global' }}
                </p>
            @endif

            <div class="mt-4 grid gap-4 lg:grid-cols-[16rem_minmax(0,1fr)]">
                <div>
                    <label for="marketplaceVertexLocation" class="text-sm text-slate-600">Location</label>
                    <input id="marketplaceVertexLocation" wire:model="marketplaceVertexLocation" type="text" class="mt-1 w-full rounded-md border-slate-300 bg-white text-gray-950" placeholder="global hoac us-central1">
                    @error('marketplaceVertexLocation') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="marketplaceVertexJson" class="text-sm text-slate-600">Service account JSON rieng cho title/listing</label>
                    <textarea
                        id="marketplaceVertexJson"
                        wire:model="marketplaceVertexJson"
                        rows="5"
                        class="mt-1 w-full rounded-md border-slate-300 bg-white font-mono text-xs text-gray-950"
                        placeholder='{"type":"service_account","project_id":"...","private_key":"-----BEGIN PRIVATE KEY-----\n...","client_email":"..."}'
                    ></textarea>
                    <p class="mt-1 text-xs text-slate-400">Paste JSON moi de thay the key title/listing hien tai. Private key se duoc encrypt.</p>
                    @error('marketplaceVertexJson') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>

            <x-ui.button color="cyan" type="submit" class="mt-4 shadow-lg shadow-cyan-500/20" wire:loading.attr="disabled" wire:target="saveMarketplaceVertexCredential">
                <span wire:loading.remove wire:target="saveMarketplaceVertexCredential">Luu Vertex title/listing</span>
                <span wire:loading wire:target="saveMarketplaceVertexCredential">Saving...</span>
            </x-ui.button>
        </form>


        <div class="mt-4 rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h2 class="text-base font-bold text-slate-950">Import Templates</h2>
                    <p class="mt-1 text-sm text-slate-500">Quan ly file Excel template cho Ornament Amazon va Sticker.</p>
                </div>
            </div>

            <div class="mt-4 overflow-hidden rounded-lg border border-slate-200">
                <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-3">Template</th>
                            <th class="px-4 py-3">File</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3 text-right">Edit</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        @foreach ($importTemplates as $template)
                            @php
                                $templatePath = $template['path'];
                                $exists = \Illuminate\Support\Facades\File::exists($templatePath);
                                $updatedAt = $exists ? \Illuminate\Support\Facades\File::lastModified($templatePath) : null;
                            @endphp
                            <tr>
                                <td class="px-4 py-3 font-semibold text-slate-900">{{ $template['label'] }}</td>
                                <td class="px-4 py-3 text-slate-600">
                                    @if ($exists)
                                        <a href="{{ asset('templates/'.$template['filename']) }}" download="{{ $template['filename'] }}" class="font-semibold text-cyan-600 underline decoration-cyan-200 underline-offset-4 hover:text-cyan-700">{{ $template['filename'] }}</a>
                                    @else
                                        {{ $template['filename'] }}
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    @if ($exists)
                                        <span class="inline-flex rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">Ready</span>
                                        <div class="mt-1 text-[11px] text-slate-400">{{ date('Y-m-d H:i', $updatedAt) }}</div>
                                    @else
                                        <span class="inline-flex rounded-full bg-rose-50 px-2.5 py-1 text-xs font-semibold text-rose-700">Missing</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <button type="button" wire:click="$dispatch('openModal', { component: 'modals.admin.edit-import-template', arguments: { templateKey: '{{ $template['key'] }}' } })" class="inline-flex items-center justify-center rounded-md border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 transition hover:border-cyan-200 hover:bg-cyan-50 hover:text-cyan-700">
                                        Edit
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-4 rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h2 class="text-base font-bold text-slate-950">Tu dong tach nen theo trang</h2>
                    <p class="mt-1 text-sm text-slate-500">Env OFFOREST_REMOVE_VERTEX_BACKGROUND van la cong tac tong. Khi env bat, admin co the chon trang nao tu dong tach nen.</p>
                </div>

                <span class="inline-flex w-fit rounded-full px-3 py-1 text-xs font-semibold {{ config('services.background_removal.enabled') ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' }}">
                    {{ config('services.background_removal.enabled') ? 'Global enabled' : 'Global disabled' }}
                </span>
            </div>

            <div class="mt-4 overflow-hidden rounded-lg border border-slate-200">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-slate-500">
                        <tr>
                            <th class="px-5 py-3 font-medium">Trang</th>
                            <th class="px-5 py-3 text-center font-medium">Auto tach nen</th>
                            <th class="px-5 py-3 text-right font-medium">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 bg-white">
                        @foreach ($products as $product)
                            <tr wire:key="background-removal-product-{{ $product->id }}" class="transition hover:bg-slate-50">
                                <td class="px-5 py-4">
                                    <p class="font-semibold text-slate-950">{{ $product->name }}</p>
                                    <p class="mt-1 text-xs text-slate-400">{{ $product->slug }}</p>
                                </td>
                                <td class="px-5 py-4 text-center">
                                    @if ($product->auto_remove_background)
                                        <span class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-emerald-100 text-emerald-700">
                                            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                <path fill-rule="evenodd" d="M16.704 5.29a1 1 0 0 1 .006 1.414l-7.25 7.32a1 1 0 0 1-1.421 0L3.29 9.23a1 1 0 1 1 1.42-1.408l4.04 4.08 6.54-6.606a1 1 0 0 1 1.414-.006Z" clip-rule="evenodd" />
                                            </svg>
                                        </span>
                                    @else
                                        <span class="text-gray-700">-</span>
                                    @endif
                                </td>
                                <td class="px-5 py-4 text-right">
                                    <button
                                        type="button"
                                        wire:click="$dispatch('openModal', { component: 'modals.admin.edit-product-background-removal', arguments: { productId: {{ $product->id }} } })"
                                        class="inline-flex items-center justify-center rounded-md border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 transition hover:border-cyan-200 hover:bg-cyan-50 hover:text-cyan-700"
                                    >
                                        Edit
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-4 overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-col gap-3 border-b border-slate-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-base font-bold text-slate-950">Danh sach user</h2>
                    <p class="mt-1 text-sm text-slate-500">{{ $users->count() }} user dang duoc quan ly.</p>
                </div>
                <button
                    type="button"
                    wire:click="$dispatch('openModal', { component: 'modals.admin.add-user' })"
                    class="inline-flex h-9 w-fit items-center justify-center rounded-md bg-cyan-500 px-3 text-xs font-bold text-white shadow-sm transition hover:bg-cyan-600"
                >
                    Add user
                </button>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-slate-500">
                        <tr>
                            <th class="px-6 py-4 font-medium">User</th>
                            <th class="px-5 py-4 text-center font-medium">Status</th>
                            <th class="px-5 py-4 text-center font-medium">Admin</th>
                            <th class="px-5 py-4 text-center font-medium">Vertex</th>
                            <th class="px-5 py-4 text-center font-medium">AI provider</th>
                            <th class="px-5 py-4 text-center font-medium">Listing</th>
                            @foreach ($products as $product)
                                <th class="px-5 py-4 text-center font-medium">{{ $product->name }}</th>
                            @endforeach
                            <th class="px-6 py-4 text-right font-medium">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        @forelse ($users as $user)
                            <tr wire:key="user-access-{{ $user->id }}" class="transition hover:bg-slate-50">
                                <td class="px-6 py-5">
                                    <div class="flex min-w-64 items-center gap-3">
                                        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-cyan-50 text-sm font-bold text-cyan-700">
                                            {{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}
                                        </div>
                                        <div class="min-w-0">
                                            <p class="truncate font-semibold text-slate-950">{{ $user->name }}</p>
                                            <p class="mt-1 truncate text-xs text-slate-400">{{ $user->email }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-5 py-5 text-center">
                                    <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $user->status === 'active' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">
                                        {{ $user->status === 'active' ? 'Active' : '-' }}
                                    </span>
                                </td>
                                <td class="px-5 py-5 text-center">
                                    @if ($user->is_admin)
                                        <span class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-cyan-100 text-cyan-700">
                                            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                <path fill-rule="evenodd" d="M16.704 5.29a1 1 0 0 1 .006 1.414l-7.25 7.32a1 1 0 0 1-1.421 0L3.29 9.23a1 1 0 1 1 1.42-1.408l4.04 4.08 6.54-6.606a1 1 0 0 1 1.414-.006Z" clip-rule="evenodd" />
                                            </svg>
                                        </span>
                                    @else
                                        <span class="text-gray-700">-</span>
                                    @endif
                                </td>
                                <td class="px-5 py-5 text-center">
                                    @if ($user->vertexApiCredential)
                                        <span class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-emerald-100 text-emerald-700">
                                            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                <path fill-rule="evenodd" d="M16.704 5.29a1 1 0 0 1 .006 1.414l-7.25 7.32a1 1 0 0 1-1.421 0L3.29 9.23a1 1 0 1 1 1.42-1.408l4.04 4.08 6.54-6.606a1 1 0 0 1 1.414-.006Z" clip-rule="evenodd" />
                                            </svg>
                                        </span>
                                    @else
                                        <span class="text-gray-700">-</span>
                                    @endif
                                </td>
                                <td class="px-5 py-5 text-center">
                                    @php($activeAiProviderKey = $user->activeAiProviderKey())
                                    @if ($activeAiProviderKey)
                                        <span class="inline-flex rounded-full bg-cyan-100 px-2.5 py-1 text-xs font-semibold text-cyan-700">
                                            {{ config("ai_providers.providers.$activeAiProviderKey.label", $activeAiProviderKey) }}
                                        </span>
                                    @else
                                        <span class="text-gray-700">-</span>
                                    @endif
                                </td>
                                <td class="px-5 py-5 text-center">
                                    @if ($user->can_generate_amazon_listing)
                                        <span class="inline-flex rounded-full bg-orange-100 px-2.5 py-1 text-xs font-semibold text-orange-700">Amazon</span>
                                    @elseif ($user->can_generate_etsy_listing)
                                        <span class="inline-flex rounded-full bg-purple-100 px-2.5 py-1 text-xs font-semibold text-purple-700">Etsy</span>
                                    @else
                                        <span class="text-gray-700">-</span>
                                    @endif
                                </td>
                                @foreach ($products as $product)
                                    @php($enabled = $user->products->contains('id', $product->id))
                                    <td class="px-5 py-5 text-center">
                                        @if ($enabled)
                                            <span class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-emerald-100 text-emerald-700">
                                                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                    <path fill-rule="evenodd" d="M16.704 5.29a1 1 0 0 1 .006 1.414l-7.25 7.32a1 1 0 0 1-1.421 0L3.29 9.23a1 1 0 1 1 1.42-1.408l4.04 4.08 6.54-6.606a1 1 0 0 1 1.414-.006Z" clip-rule="evenodd" />
                                                </svg>
                                            </span>
                                        @else
                                            <span class="text-gray-700">-</span>
                                        @endif
                                    </td>
                                @endforeach
                                <td class="px-6 py-5 text-right">
                                    <button
                                        type="button"
                                        wire:click="$dispatch('openModal', { component: 'modals.admin.edit-user', arguments: { userId: {{ $user->id }} } })"
                                        class="inline-flex items-center justify-center rounded-md border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 transition hover:border-cyan-200 hover:bg-cyan-50 hover:text-cyan-700"
                                    >
                                        Edit
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ 7 + $products->count() }}" class="px-5 py-10 text-center text-sm text-slate-400">
                                    Chua co user nao.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <livewire:modals.admin.add-user />
    <livewire:modals.admin.edit-user />
    <livewire:modals.admin.edit-product-background-removal />
    <livewire:modals.admin.edit-import-template />
</section>
