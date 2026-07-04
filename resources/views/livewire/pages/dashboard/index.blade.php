<div class="min-h-[calc(100vh-4rem)] dashboard-surface text-slate-950">
    <div class="mx-auto max-w-[1520px] px-4 py-5 sm:px-6 lg:px-8">
        <section class="dashboard-panel mb-6 overflow-hidden rounded-[28px] border px-5 py-5 sm:px-6 lg:px-7 lg:py-6">
            <div class="flex flex-col gap-5 xl:flex-row xl:items-end xl:justify-between">
                <div class="max-w-2xl">
                    <div class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.28em] text-blue-500/90">
                        <span class="h-1.5 w-1.5 rounded-full bg-cyan-400"></span>
                        Dashboard
                    </div>
                    <h1 class="mt-4 text-2xl font-semibold tracking-tight sm:text-[32px]">Tổng quan hiệu suất Offorest</h1>
                    <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500 dark:text-slate-400">Theo dõi tiến độ duyệt, tổng sản phẩm và hiệu suất user theo phong cách dashboard SaaS hiện đại.</p>
                </div>

                <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3 xl:min-w-[720px]">
                    <label class="dashboard-filter block">
                        <span class="dashboard-filter-label">Tháng</span>
                        <select wire:model.live="selectedMonth" class="dashboard-select h-12 w-full rounded-2xl border px-4 text-sm font-medium shadow-sm outline-none transition">
                            @forelse ($monthOptions as $option)
                                <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                            @empty
                                <option value="{{ now()->format('Y-m') }}">{{ now()->format('m/Y') }}</option>
                            @endforelse
                        </select>
                    </label>

                    @if ($isPrivileged)
                        <label class="dashboard-filter block">
                            <span class="dashboard-filter-label">User</span>
                            <select wire:model.live="selectedUserId" class="dashboard-select h-12 w-full rounded-2xl border px-4 text-sm font-medium shadow-sm outline-none transition">
                                <option value="">Tất cả user</option>
                                <?php foreach ($availableUsers as $user): ?>
                                    <option value="<?= e($user->id) ?>"><?= e($user->name) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    @endif

                    <label class="dashboard-filter block">
                        <span class="dashboard-filter-label">Nhóm page</span>
                        <select wire:model.live="selectedProductSlug" class="dashboard-select h-12 w-full rounded-2xl border px-4 text-sm font-medium shadow-sm outline-none transition">
                            <option value="">Tất cả nhóm page</option>
                            <?php foreach ($visibleProducts as $product): ?>
                                <option value="<?= e($product->slug) ?>"><?= e($product->name) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </div>
            </div>
        </section>

        <section class="grid gap-4 sm:grid-cols-2 2xl:grid-cols-4">
            <?php foreach ($overviewCards as $card): ?>
                <?php
                    $isDown = !empty($card['delta']) && str_starts_with($card['delta'], '-');
                    $overviewLabels = [
                        'Users' => 'User',
                        'Pending' => 'Chưa duyệt',
                        'Approved' => 'Đã duyệt',
                        'Total Items' => 'Tổng sản phẩm',
                    ];
                    $overviewNotes = [
                        'Total system users' => 'Tổng user hệ thống',
                        'Completed in month' => 'Hoàn thành trong tháng',
                        'Pending + approved' => 'Chưa duyệt + Đã duyệt',
                    ];
                    $displayLabel = $overviewLabels[$card['label']] ?? $card['label'];
                    $displayNote = $overviewNotes[$card['note']] ?? str_replace('Need review in', 'Cần xử lý trong', $card['note']);
                    $accentMap = [
                        'slate' => 'from-slate-500/10 via-transparent to-transparent text-slate-500',
                        'amber' => 'from-amber-500/12 via-transparent to-transparent text-amber-500',
                        'emerald' => 'from-emerald-500/12 via-transparent to-transparent text-emerald-500',
                        'blue' => 'from-blue-500/14 via-violet-500/8 to-transparent text-blue-500',
                    ];
                    $accent = $accentMap[$card['tone'] ?? 'slate'] ?? $accentMap['slate'];
                ?>
                <article class="dashboard-stat-card relative overflow-hidden rounded-[24px] border p-5 sm:p-6">
                    <div class="absolute inset-x-0 top-0 h-24 bg-gradient-to-br <?= $accent ?>"></div>
                    <div class="relative flex items-start justify-between gap-3">
                        <div>
                            <p class="text-sm font-semibold text-slate-500 dark:text-slate-400"><?= e($displayLabel) ?></p>
                            <p class="mt-4 text-4xl font-semibold tracking-tight sm:text-[40px] leading-none"><?= e(number_format((int) $card['value'])) ?></p>
                        </div>
                        <?php if (!empty($card['delta'])): ?>
                            <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold <?= $isDown ? 'bg-rose-500/10 text-rose-500 border border-rose-500/20' : 'bg-blue-500/10 text-blue-500 border border-blue-500/20' ?>">
                                <?= e($card['delta']) ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    <p class="relative mt-6 text-sm leading-6 text-slate-500 dark:text-slate-400"><?= e($displayNote) ?></p>
                </article>
            <?php endforeach; ?>
        </section>

        <?php $maxValue = max(1, collect($monthlySeries)->flatMap(function ($point) { return [$point['pending'], $point['approved']]; })->max() ?? 1); ?>

        <section class="mt-6 grid gap-5 xl:grid-cols-[minmax(0,1.8fr)_minmax(340px,1fr)]">
            <article class="dashboard-card rounded-[28px] border p-5 sm:p-6 lg:p-7">
                <div class="mb-6 flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                    <div>
                        <h2 class="text-xl font-semibold tracking-tight sm:text-2xl">Tiến độ duyệt theo tháng</h2>
                        <p class="mt-2 text-sm leading-6 text-slate-500 dark:text-slate-400">Theo dõi nhập và xử lý qua từng tháng. Màu vàng là chưaa duyệt, màu xanh là Đã duyệt.</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-3 text-xs font-medium text-slate-500 dark:text-slate-400">
                        <span class="inline-flex items-center gap-2"><span class="h-2.5 w-2.5 rounded-full bg-amber-400 shadow-[0_0_0_4px_rgba(251,191,36,0.16)]"></span>Chưa duyệt</span>
                        <span class="inline-flex items-center gap-2"><span class="h-2.5 w-2.5 rounded-full bg-cyan-400 shadow-[0_0_0_4px_rgba(34,211,238,0.16)]"></span>Đã duyệt</span>
                    </div>
                </div>

                <div class="flex h-[340px] items-end gap-4 overflow-hidden rounded-[22px] border border-slate-200/80 bg-slate-50/80 px-4 py-5 dark:border-white/8 dark:bg-white/[0.03] sm:px-6">
                    <?php if (empty($monthlySeries)): ?>
                        <div class="flex h-full w-full items-center justify-center rounded-[18px] border border-dashed border-slate-200 text-sm text-slate-400 dark:border-white/10 dark:text-slate-500">Chưa có dữ liệu biểu đồ.</div>
                    <?php else: ?>
                        <?php foreach ($monthlySeries as $point): ?>
                            <?php
                                $pendingHeight = max(10, (int) round(($point['pending'] / $maxValue) * 220));
                                $approvedHeight = max(10, (int) round(($point['approved'] / $maxValue) * 220));
                            ?>
                            <div class="flex min-w-0 flex-1 flex-col items-center justify-end gap-3">
                                <div class="flex h-full w-full items-end justify-center gap-2 rounded-[18px] border border-transparent px-2 py-3 transition hover:border-blue-200/40 hover:bg-white/40 dark:hover:border-white/10 dark:hover:bg-white/[0.03]">
                                    <div class="w-4 rounded-full bg-gradient-to-t from-amber-500 to-amber-300 shadow-[0_10px_30px_rgba(251,191,36,0.22)]" style="height: <?= e($pendingHeight) ?>px" title="Chưa duyệt: <?= e($point['pending']) ?>"></div>
                                    <div class="w-4 rounded-full bg-gradient-to-t from-cyan-500 via-sky-500 to-blue-400 shadow-[0_10px_30px_rgba(59,130,246,0.24)]" style="height: <?= e($approvedHeight) ?>px" title="Đã duyệt: <?= e($point['approved']) ?>"></div>
                                </div>
                                <span class="text-[11px] font-semibold text-slate-500 dark:text-slate-400"><?= e($point['label']) ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </article>

            <?php if ($isPrivileged): ?>
                <article class="dashboard-card rounded-[28px] border p-5 sm:p-6 lg:p-7">
                    <div class="mb-6">
                        <h2 class="text-xl font-semibold tracking-tight sm:text-2xl">Xếp hạng user</h2>
                        <p class="mt-2 text-sm leading-6 text-slate-500 dark:text-slate-400">Xếp hạng theo tổng sản phẩm trong tháng đã chọn.</p>
                    </div>

                    <div class="space-y-3">
                        <?php if (count($topUsers) > 0): ?>
                            <?php foreach ($topUsers as $index => $row): ?>
                                <div class="rounded-[22px] border border-slate-200 bg-white/80 p-4 shadow-sm dark:border-white/8 dark:bg-white/[0.03]">
                                    <div class="flex items-center justify-between gap-4">
                                        <div class="min-w-0">
                                            <p class="truncate text-sm font-semibold">#<?= e($index + 1) ?> <?= e($row['user']) ?></p>
                                            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Chưa duyệt <?= e(number_format($row['pending'])) ?> ? Đã duyệt <?= e(number_format($row['approved'])) ?></p>
                                        </div>
                                        <div class="rounded-2xl bg-slate-950/5 px-3 py-2 text-lg font-semibold tracking-tight dark:bg-white/5"><?= e(number_format($row['total'])) ?></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="rounded-[22px] border border-dashed border-slate-200 px-4 py-10 text-center text-sm text-slate-400 dark:border-white/10 dark:text-slate-500">Chưa có dữ liệu xếp hạng user.</div>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endif; ?>
        </section>

        <section class="mt-6">
            <article class="dashboard-card rounded-[28px] border p-5 sm:p-6 lg:p-7">
                <div class="mb-6 flex flex-col gap-2">
                    <h2 class="text-xl font-semibold tracking-tight sm:text-2xl">Thống kê từng nhóm page</h2>
                    <p class="text-sm leading-6 text-slate-500 dark:text-slate-400">Khi đã chọn user, danh sách nhóm page chỉ hiện những page user đó được phân quyền.</p>
                </div>

                <div class="grid gap-4 md:grid-cols-2 2xl:grid-cols-4">
                    <?php if (empty($productCards)): ?>
                        <div class="col-span-full rounded-[22px] border border-dashed border-slate-200 px-5 py-12 text-center text-sm text-slate-400 dark:border-white/10 dark:text-slate-500">Chưa có nhóm page nào được phân quyền.</div>
                    <?php else: ?>
                        <?php foreach ($productCards as $card): ?>
                            <?php $isDown = !empty($card['delta']) && str_starts_with($card['delta'], '-'); ?>
                            <article class="dashboard-product-card rounded-[24px] border p-5">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="truncate text-base font-semibold"><?= e($card['name']) ?></p>
                                        <p class="mt-1 text-xs font-semibold uppercase tracking-[0.22em] text-slate-400 dark:text-slate-500"><?= e($card['slug']) ?></p>
                                    </div>
                                    <?php if (!empty($card['delta'])): ?>
                                        <span class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-semibold <?= $isDown ? 'bg-rose-500/10 text-rose-500 border border-rose-500/20' : 'bg-blue-500/10 text-blue-500 border border-blue-500/20' ?>">
                                            <?= e($card['delta']) ?>
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <div class="mt-5 grid grid-cols-2 gap-3">
                                    <div class="dashboard-mini-stat rounded-2xl p-3.5">
                                        <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">User</p>
                                        <p class="mt-2 text-2xl font-semibold tracking-tight"><?= e(number_format($card['users'])) ?></p>
                                    </div>
                                    <div class="dashboard-mini-stat rounded-2xl p-3.5">
                                        <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Tổng sản phẩm</p>
                                        <p class="mt-2 text-2xl font-semibold tracking-tight"><?= e(number_format($card['total'])) ?></p>
                                    </div>
                                    <div class="dashboard-mini-stat rounded-2xl p-3.5">
                                        <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Chưa duyệt</p>
                                        <p class="mt-2 text-2xl font-semibold tracking-tight"><?= e(number_format($card['pending'])) ?></p>
                                    </div>
                                    <div class="dashboard-mini-stat rounded-2xl p-3.5">
                                        <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Đã duyệt</p>
                                        <p class="mt-2 text-2xl font-semibold tracking-tight"><?= e(number_format($card['approved'])) ?></p>
                                    </div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </article>
        </section>
    </div>
</div>
