<div>
@if ($isOpen)
    <div class="fixed inset-0 z-[90] flex items-center justify-center p-4">
        <button type="button" class="absolute inset-0 bg-slate-950/40" wire:click="close" aria-label="Close"></button>
        <div class="relative z-[91] flex max-h-[90vh] w-full max-w-[98vw] flex-col overflow-hidden rounded-xl border border-slate-200 bg-white shadow-2xl">
            <div class="flex items-center justify-between border-b border-slate-200 px-3 py-3">
                <div>
                    <h3 class="text-base font-bold text-slate-950">Dữ liệu tổng kết kỳ {{ $monthLabel }}</h3>
                    <p class="mt-1 text-xs text-slate-500">Nhận dữ liệu của toàn bộ nhân viên</p>
                </div>
                <button type="button" wire:click="close" class="rounded-md p-2 text-slate-400 hover:bg-red-700 hover:text-gray-600">x</button>
            </div>

            <div class="overflow-auto px-2 py-3">
                <table class="w-full table-fixed divide-y divide-slate-200 text-[10px] leading-tight">
                    <thead class="sticky top-0 bg-slate-50 text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-1 py-2 text-center whitespace-normal">Nhân viên</th>
                            <th class="px-1 py-2 text-center whitespace-normal">Điểm hiệu suất</th>
                            <th class="px-1 py-2 text-center whitespace-normal">Đi trễ (phút)</th>
                            <th class="px-1 py-2 text-center whitespace-normal">Xin nghỉ</th>
                            <th class="px-1 py-2 text-center whitespace-normal">Số ngày được nghỉ</th>
                            <th class="px-1 py-2 text-center whitespace-normal">Thưởng ngày</th>
                            <th class="px-1 py-2 text-center whitespace-normal">Bổ sung</th>
                            <th class="px-1 py-2 text-center whitespace-normal">Tiền khác</th>
                            <th class="px-1 py-2 text-center whitespace-normal">Note</th>
                            <th class="px-1 py-2 text-center whitespace-normal">Tổng lương</th>
                            <th class="px-1 py-2 text-center whitespace-normal">Thực nhận</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($rows as $index => $row)
                            <tr wire:key="month-summary-row-{{ $salaryMonth }}-{{ $row['employee_id'] }}-{{ $index }}">
                                <td class="px-1 py-2 truncate font-semibold text-slate-900">{{ $row['employee_name'] }}</td>
                                <td class="px-1.5 py-2"><input type="text" inputmode="decimal" wire:model.blur="rows.{{ $index }}.performance_score" class="w-full rounded-md border border-slate-200 px-1 py-1 text-center tabular-nums"></td>
                                <td class="px-1.5 py-2"><input type="text" inputmode="numeric" wire:model.blur="rows.{{ $index }}.late_minutes" class="w-full rounded-md border border-slate-200 px-1 py-1 text-center tabular-nums"></td>
                                <td class="px-1.5 py-2"><input type="text" inputmode="numeric" wire:model.blur="rows.{{ $index }}.leave_days" class="w-full rounded-md border border-slate-200 px-1 py-1 text-center tabular-nums"></td>
                                <td class="px-1.5 py-2"><input type="text" inputmode="numeric" wire:model.blur="rows.{{ $index }}.allowed_leave_days" class="w-full rounded-md border border-slate-200 px-1 py-1 text-center tabular-nums"></td>
                                <td class="px-1.5 py-2"><input type="text" inputmode="numeric" value="{{ $row['daily_bonus'] }}" wire:model.blur="rows.{{ $index }}.daily_bonus" oninput="this.value = this.value.replace(/\D/g, '').replace(/\B(?=(\d{3})+(?!\d))/g, '.')" class="w-full rounded-md border border-slate-200 px-1 py-1 text-center tabular-nums"></td>
                                <td class="px-1.5 py-2"><input type="text" inputmode="numeric" value="{{ $row['supplement'] }}" wire:model.blur="rows.{{ $index }}.supplement" oninput="this.value = this.value.replace(/\D/g, '').replace(/\B(?=(\d{3})+(?!\d))/g, '.')" class="w-full rounded-md border border-slate-200 px-1 py-1 text-center tabular-nums"></td>
                                <td class="px-1.5 py-2"><input type="text" inputmode="numeric" value="{{ $row['other_money'] }}" wire:model.blur="rows.{{ $index }}.other_money" oninput="this.value = this.value.replace(/\D/g, '').replace(/\B(?=(\d{3})+(?!\d))/g, '.')" class="w-full rounded-md border border-slate-200 px-1 py-1 text-center tabular-nums"></td>
                                <td class="px-1.5 py-2"><textarea wire:model.blur="rows.{{ $index }}.note" class="w-full rounded-md border border-slate-200 px-1.5 py-1 text-[10px]"></textarea></td>
                                <td class="px-1.5 py-2 text-right tabular-nums font-semibold">{{ number_format((float) $row['total_salary'], 0, ',', '.') }}</td>
                                <td class="px-1.5 py-2 text-right tabular-nums font-bold text-emerald-600">{{ number_format((float) $row['net_received'], 0, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="flex items-center justify-end gap-3 border-t border-slate-200 px-3 py-3">
                <button type="button" wire:click="close" class="inline-flex h-10 items-center justify-center rounded-md border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">Close</button>
                <button type="button" wire:click="save" class="inline-flex h-10 items-center justify-center rounded-md bg-blue-500 px-4 text-sm font-bold text-white hover:bg-blue-600">Save</button>
            </div>
        </div>
    </div>
@endif
</div>
