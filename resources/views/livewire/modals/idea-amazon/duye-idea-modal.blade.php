<div
    x-show="approvalOpen"
    x-transition.opacity
    class="fixed inset-0 z-50 flex h-full w-full items-center justify-center overflow-y-auto bg-slate-950/70 p-4 backdrop-blur-sm"
    style="display: none;"
    tabindex="-1"
    aria-modal="true"
    role="dialog"
>
    <button type="button" class="fixed inset-0 cursor-default" x-on:click="if (!approvalConfirmOpen) closeApproval()" aria-label="Close approval modal"></button>

    <div class="relative z-10 w-full max-w-4xl">
        <div class="relative flex max-h-[86vh] flex-col overflow-hidden rounded-2xl border border-white/70 bg-white shadow-2xl">
            <button
                type="button"
                x-on:click="closeApproval"
                class="absolute right-4 top-4 z-20 inline-flex h-10 w-10 items-center justify-center rounded-full border border-slate-200 bg-white/90 text-slate-500 shadow-sm backdrop-blur transition hover:bg-white hover:text-slate-950"
                aria-label="Close approval modal"
            >
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                    <path d="M18 6 6 18" />
                    <path d="m6 6 12 12" />
                </svg>
            </button>

            <div class="border-b border-slate-200 px-5 py-4 pr-16">
                <div class="flex items-center gap-3">
                    <span class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path d="M20 6 9 17l-5-5" />
                        </svg>
                    </span>
                    <div>
                        <h2 class="text-base font-bold text-slate-950">Duyet item Amazon</h2>
                        <p class="mt-0.5 text-sm text-slate-500">Chi luu keyword va link anh cua item da tich.</p>
                    </div>
                </div>
            </div>

            <div class="min-h-0 flex-1 space-y-4 overflow-y-auto p-5">
                <section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div class="flex items-center justify-between gap-3 border-b border-slate-200 px-4 py-3">
                        <h3 class="text-sm font-bold text-slate-950">Item da chon</h3>
                        <span class="rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-bold text-emerald-700">
                            <span x-text="selectedProducts().length"></span> item
                        </span>
                    </div>

                    <div class="overflow-y-auto" style="max-height: 220px;">
                        <table class="min-w-full table-fixed divide-y divide-slate-200 text-sm">
                            <thead class="sticky top-0 z-10 bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500">
                                <tr>
                                    <th class="w-20 px-4 py-3">Image</th>
                                    <th class="w-[44%] px-4 py-3">Product</th>
                                    <th class="px-4 py-3">Link anh</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 bg-white">
                                <template x-for="product in selectedProducts()" :key="productKey(product)">
                                    <tr class="align-top">
                                        <td class="px-4 py-2">
                                            <img x-bind:src="product.imageUrl" alt="" class="h-10 w-10 rounded-lg object-cover">
                                        </td>
                                        <td class="px-4 py-2">
                                            <p class="truncate font-bold text-slate-900" x-text="product.title || keyword"></p>
                                            <p class="mt-1 font-mono text-xs text-slate-400" x-text="product.listingId || '-'"></p>
                                        </td>
                                        <td class="px-4 py-2">
                                            <p class="truncate font-mono text-xs text-slate-500" x-text="product.imageUrl"></p>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </section>

                <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <h3 class="mb-3 text-sm font-bold text-slate-950">Chon trang dich</h3>
                    <div class="grid gap-2 sm:grid-cols-2">
                        <template x-for="product in targetProducts" :key="product.slug">
                            <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-slate-200 bg-white p-3 transition hover:bg-slate-50 hover:shadow-sm" x-bind:class="approvalTargetSlug === product.slug ? 'border-emerald-300 bg-emerald-50 shadow-sm' : ''">
                                <input type="radio" name="idea_amazon_target_product" x-model="approvalTargetSlug" x-bind:value="product.slug" class="text-emerald-600 focus:ring-emerald-500">
                                <span class="font-bold text-slate-800" x-text="product.name"></span>
                            </label>
                        </template>
                    </div>
                </section>
            </div>

            <div class="shrink-0 border-t border-slate-200 bg-white/95 p-4 shadow-lg shadow-slate-900/5 backdrop-blur">
                <div class="flex justify-end gap-3">
                    <button type="button" x-on:click="closeApproval" class="inline-flex min-w-24 items-center justify-center rounded-xl border border-slate-200 bg-white px-5 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                        Huy
                    </button>
                    <button type="button" x-on:click="saveApproval(false)" x-bind:disabled="approvalSaving || !approvalTargetSlug" class="inline-flex min-w-28 items-center justify-center gap-2 rounded-xl bg-blue-600 px-5 py-3 text-sm font-bold text-white shadow-lg shadow-blue-500/20 transition hover:-translate-y-0.5 hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60">
                        <svg x-show="approvalSaving" class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4z"></path>
                        </svg>
                        <span x-show="!approvalSaving">Save</span>
                        <span x-show="approvalSaving">Saving...</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<div
    x-show="approvalConfirmOpen"
    x-transition.opacity
    class="fixed inset-0 flex h-full w-full items-center justify-center overflow-y-auto bg-slate-950/70 p-4 backdrop-blur-sm"
    style="display: none; z-index: 9999;"
    tabindex="-1"
    aria-modal="true"
    role="dialog"
>
    <button type="button" class="fixed inset-0 cursor-default" x-on:click="rejectKeywordSave" aria-label="Close confirm modal"></button>

    <div class="relative z-10 w-full max-w-md">
        <div class="relative overflow-hidden rounded-2xl border border-white/70 bg-white shadow-2xl">
            <button
                type="button"
                x-on:click="rejectKeywordSave"
                class="absolute right-4 top-4 z-20 inline-flex h-10 w-10 items-center justify-center rounded-full border border-slate-200 bg-white/90 text-slate-500 shadow-sm backdrop-blur transition hover:bg-white hover:text-slate-950"
                aria-label="Close confirm modal"
            >
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                    <path d="M18 6 6 18" />
                    <path d="m6 6 12 12" />
                </svg>
            </button>

            <div class="border-b border-slate-200 px-5 py-4 pr-16">
                <div class="flex items-center gap-3">
                    <span class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-amber-50 text-amber-600">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path d="M12 9v4" />
                            <path d="M12 17h.01" />
                            <path d="M10.3 3.9 2.5 18a2 2 0 0 0 1.7 3h15.6a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z" />
                        </svg>
                    </span>
                    <div>
                        <h2 class="text-base font-bold text-slate-950">Can xac nhan keyword</h2>
                        <p class="mt-0.5 text-sm text-slate-500">Item khong dung voi trang dang chon.</p>
                    </div>
                </div>
            </div>

            <div class="p-5">
                <div class="rounded-2xl border border-amber-200 bg-amber-50/70 p-4 text-sm leading-6 text-amber-900" x-text="approvalConfirmMessage"></div>
            </div>

            <div class="border-t border-slate-200 bg-white/95 p-4 shadow-lg shadow-slate-900/5 backdrop-blur">
                <div class="flex justify-end gap-3">
                    <button type="button" x-on:click="rejectKeywordSave" x-bind:disabled="approvalSaving" class="inline-flex min-w-24 items-center justify-center rounded-xl border border-slate-200 bg-white px-5 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 disabled:opacity-60">
                        No
                    </button>
                    <button type="button" x-on:click="confirmKeywordSave" x-bind:disabled="approvalSaving" class="inline-flex min-w-24 items-center justify-center rounded-xl bg-blue-600 px-5 py-3 text-sm font-bold text-white shadow-lg shadow-blue-500/20 transition hover:-translate-y-0.5 hover:bg-blue-700 disabled:opacity-60">
                        Yes
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

