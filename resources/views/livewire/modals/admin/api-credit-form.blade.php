<div>
    @if ($isOpen)
        <div
            x-on:keydown.escape.window="$wire.close()"
            tabindex="-1"
            aria-modal="true"
            role="dialog"
            class="fixed inset-0 z-50 flex h-[calc(100%-1rem)] max-h-full w-full items-center justify-center overflow-y-auto overflow-x-hidden bg-gray-900/50 p-4 md:inset-0"
        >
            <button type="button" class="fixed inset-0 cursor-default" wire:click="close" aria-label="Close API credit modal"></button>

            <div class="relative overflow-y-auto z-10 max-h-full w-full max-w-4xl">
                <form wire:submit.prevent="save" class="relative overflow-hidden rounded-2xl bg-white shadow-2xl">
                    <div class="flex items-center justify-between border-b border-gray-200 p-4 md:p-5">
                        <div>
                            <h3 class="text-xl font-semibold text-gray-900">{{ $creditId ? 'Edit API credit' : 'Add API credit' }}</h3>
                            <p class="mt-1 text-sm text-gray-500">Luu free trial, credit, billing, ngay het han va ghi chu API.</p>
                        </div>

                        <button type="button" wire:click="close" class="inline-flex h-9 w-9 items-center justify-center rounded-full text-gray-400 hover:bg-gray-200 hover:text-gray-900">
                            <span class="text-xl leading-none">&times;</span>
                        </button>
                    </div>

                    <div class="max-h-[75vh] space-y-4 overflow-y-auto p-4 md:p-5">
                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <label class="text-sm font-medium text-gray-900">Name</label>
                                <input wire:model="name" type="text" class="mt-1 w-full rounded-md border-slate-300 text-gray-950" placeholder="Free Trial">
                                @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="text-sm font-medium text-gray-900">Provider</label>
                                <input wire:model="provider" type="text" class="mt-1 w-full rounded-md border-slate-300 text-gray-950" placeholder="Google Cloud / Vertex">
                                @error('provider') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="text-sm font-medium text-gray-900">Account email</label>
                                <input wire:model="accountEmail" type="email" class="mt-1 w-full rounded-md border-slate-300 text-gray-950" placeholder="account@example.com">
                                @error('accountEmail') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="text-sm font-medium text-gray-900">Status</label>
                                <select wire:model="status" class="mt-1 w-full rounded-md border-slate-300 text-gray-950">
                                    @foreach ($statuses as $statusOption)
                                        <option value="{{ $statusOption }}">{{ ucfirst($statusOption) }}</option>
                                    @endforeach
                                </select>
                                @error('status') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="text-sm font-medium text-gray-900">Available %</label>
                                <input wire:model="availabilityPercent" type="number" step="0.01" min="0" max="100" class="mt-1 w-full rounded-md border-slate-300 text-gray-950" placeholder="96">
                                @error('availabilityPercent') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="text-sm font-medium text-gray-900">Currency</label>
                                <input wire:model="currency" type="text" class="mt-1 w-full rounded-md border-slate-300 text-gray-950" placeholder="VND">
                                @error('currency') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="text-sm font-medium text-gray-900">Credit amount</label>
                                <input wire:model="creditAmount" type="number" step="0.01" min="0" class="mt-1 w-full rounded-md border-slate-300 text-gray-950" placeholder="7557450.12">
                                @error('creditAmount') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="text-sm font-medium text-gray-900">List price</label>
                                <input wire:model="listPrice" type="number" step="0.01" min="0" class="mt-1 w-full rounded-md border-slate-300 text-gray-950" placeholder="7900651.00">
                                @error('listPrice') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="text-sm font-medium text-gray-900">Billing type</label>
                                <input wire:model="billingType" type="text" class="mt-1 w-full rounded-md border-slate-300 text-gray-950" placeholder="One-time">
                                @error('billingType') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="text-sm font-medium text-gray-900">Pricing type</label>
                                <input wire:model="pricingType" type="text" class="mt-1 w-full rounded-md border-slate-300 text-gray-950" placeholder="Net pricing">
                                @error('pricingType') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="text-sm font-medium text-gray-900">Start date</label>
                                <input wire:model="startsAt" type="date" class="mt-1 w-full rounded-md border-slate-300 text-gray-950">
                                @error('startsAt') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="text-sm font-medium text-gray-900">Expiry date</label>
                                <input wire:model="expiresAt" type="date" class="mt-1 w-full rounded-md border-slate-300 text-gray-950">
                                @error('expiresAt') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        <div>
                            <label class="text-sm font-medium text-gray-900">Credit / API code</label>
                            <textarea wire:model="creditCode" rows="3" class="mt-1 w-full rounded-md border-slate-300 font-mono text-xs text-gray-950" placeholder="FreeTrialUpgrade:CreditId-FreeTrial:Credit-..."></textarea>
                            @error('creditCode') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="text-sm font-medium text-gray-900">Terms</label>
                            <textarea wire:model="terms" rows="2" class="mt-1 w-full rounded-md border-slate-300 text-gray-950" placeholder="Certain usage; see the terms for free trial."></textarea>
                            @error('terms') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="text-sm font-medium text-gray-900">Notes</label>
                            <textarea wire:model="notes" rows="3" class="mt-1 w-full rounded-md border-slate-300 text-gray-950" placeholder="Ghi chu usage, project, key nao dang dung..."></textarea>
                            @error('notes') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="flex items-center gap-3 border-t border-gray-200 p-4 md:p-5">
                        <x-ui.button color="cyan" type="submit" wire:loading.attr="disabled" wire:target="save">
                            <span wire:loading.remove wire:target="save">Save</span>
                            <span wire:loading wire:target="save">Saving...</span>
                        </x-ui.button>
                        <x-ui.button color="light" type="button" wire:click="close">Cancel</x-ui.button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
