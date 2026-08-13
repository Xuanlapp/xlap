<div class="min-h-[calc(100vh-4rem)] bg-slate-950 px-4 py-8 text-white sm:px-6 lg:px-8">
    <div class="mx-auto max-w-5xl space-y-6">
        <div class="flex items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-black">AI Models</h1>
                <p class="mt-1 text-sm text-white/60">Quan ly model Image/Text cho Suncatcher, v98Store va CheapKeyAI.</p>
            </div>
            <a href="{{ route('offorest.admin.users') }}" wire:navigate class="rounded-lg border border-white/10 px-4 py-2 text-sm font-semibold text-white/75 hover:bg-white/10">User access</a>
        </div>

        <form wire:submit.prevent="addModel" class="rounded-2xl border border-white/10 bg-white/5 p-4 shadow-xl">
            <div class="grid gap-3 md:grid-cols-5">
                <label class="text-sm font-semibold text-white/70">Provider
                    <select wire:model="providerKey" class="mt-1 w-full rounded-lg border-white/10 bg-slate-900 px-3 py-2 text-white">
                        @foreach ($providers as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="text-sm font-semibold text-white/70">Type
                    <select wire:model="modelType" class="mt-1 w-full rounded-lg border-white/10 bg-slate-900 px-3 py-2 text-white">
                        <option value="text">Text</option>
                        <option value="image">Image</option>
                    </select>
                </label>
                <label class="text-sm font-semibold text-white/70 md:col-span-2">Model key
                    <input wire:model="modelKey" placeholder="gpt-4.1-nano" class="mt-1 w-full rounded-lg border-white/10 bg-slate-900 px-3 py-2 font-mono text-white">
                </label>
                <label class="text-sm font-semibold text-white/70">Label
                    <input wire:model="label" placeholder="GPT-4.1 Nano" class="mt-1 w-full rounded-lg border-white/10 bg-slate-900 px-3 py-2 text-white">
                </label>
            </div>
            @error('modelKey') <p class="mt-2 text-sm text-rose-300">{{ $message }}</p> @enderror
            <button type="submit" class="mt-4 rounded-lg bg-cyan-500 px-4 py-2 text-sm font-bold text-white hover:bg-cyan-600">Add / Enable model</button>
        </form>

        <div class="overflow-hidden rounded-2xl border border-white/10 bg-white/5 shadow-xl">
            <table class="min-w-full divide-y divide-white/10 text-sm">
                <thead class="bg-white/5 text-left text-xs uppercase text-white/50">
                    <tr><th class="px-4 py-3">Provider</th><th class="px-4 py-3">Type</th><th class="px-4 py-3">Model</th><th class="px-4 py-3">Label</th><th class="px-4 py-3 text-right">Action</th></tr>
                </thead>
                <tbody class="divide-y divide-white/10">
                    @forelse ($models as $model)
                        <tr>
                            <td class="px-4 py-3 font-bold">{{ $providers[$model->provider_key] ?? $model->provider_key }}</td>
                            <td class="px-4 py-3">{{ ucfirst($model->model_type) }}</td>
                            <td class="px-4 py-3 font-mono text-cyan-200">{{ $model->model_key }}</td>
                            <td class="px-4 py-3">{{ $model->label }}</td>
                            <td class="px-4 py-3 text-right"><button type="button" wire:click="deleteModel({{ $model->id }})" wire:confirm="Xoa model nay?" class="rounded-lg border border-rose-400/40 px-3 py-1 text-xs font-bold text-rose-200 hover:bg-rose-500/10">Delete</button></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-8 text-center text-white/50">Chua co model nao.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
