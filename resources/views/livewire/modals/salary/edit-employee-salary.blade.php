<div>
@if ($isOpen)
    <div class="fixed inset-0 z-[90] flex items-center justify-center p-4">
        <button type="button" class="absolute inset-0 bg-slate-950/40" wire:click="close" aria-label="Close"></button>
        <div class="relative z-[91] flex max-h-[90vh] w-full max-w-[1400px] flex-col overflow-hidden rounded-xl border border-slate-200 bg-white shadow-2xl">
            <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
                <div>
                    <h3 class="text-base font-bold text-slate-950">Sửa thông tin {{ $row['employee_name'] ?? '' }} - {{ $monthLabel }}</h3>
                    <p class="mt-1 text-xs text-slate-500">Cập nhập thông tin chi tiết của từng nhân viên.</p>
                </div>
                <button type="button" wire:click="close" class="rounded-md p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-700">x</button>
            </div>

            <div class="overflow-auto px-5 py-4">
                <table class="min-w-full divide-y divide-slate-200 text-[11px]">
                    <thead class="sticky top-0 bg-slate-50 text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="min-w-[180px] px-2 py-2 text-left">Nhân viên</th>
                            <th class="min-w-[130px] px-2 py-2 text-right">Lương cơ bản</th>
                            <th class="min-w-[120px] px-2 py-2 text-right">Điểm số</th>
                            <th class="min-w-[110px] px-2 py-2 text-right">Đi trễ</th>
                            <th class="min-w-[110px] px-2 py-2 text-right">Xin nghỉ</th>
                            <th class="min-w-[130px] px-2 py-2 text-right">Số ngày được nghỉ</th>
                            <th class="min-w-[130px] px-2 py-2 text-right">Thưởng ngày</th>
                            <th class="min-w-[130px] px-2 py-2 text-right">Tiền bổ xung</th>
                            <th class="min-w-[130px] px-2 py-2 text-right">Tiền khác</th>
                            <th class="min-w-[260px] px-2 py-2 text-left">Note</th>
                            <th class="min-w-[140px] px-2 py-2 text-right">Tổng lương</th>
                            <th class="min-w-[140px] px-2 py-2 text-right">Thực nhận</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr wire:key="edit-employee-salary-{{ $salaryMonth }}-{{ $employeeId }}">
                            <td class="px-2 py-2 font-semibold text-slate-900">{{ $row['employee_name'] ?? '' }}</td>
                            <td class="px-2 py-2"><input type="text" inputmode="numeric" wire:model.live.debounce.300ms="row.base_salary" oninput="this.value = this.value.replace(/\D/g, '').replace(/\B(?=(\d{3})+(?!\d))/g, '.')" class="w-32 rounded-md border border-slate-200 px-2 py-1 text-right"></td>
                            <td class="px-2 py-2"><input type="text" inputmode="decimal" wire:model.live.debounce.300ms="row.performance_score" class="w-28 rounded-md border border-slate-200 px-2 py-1 text-right"></td>
                            <td class="px-2 py-2"><input type="text" inputmode="numeric" wire:model.live.debounce.300ms="row.late_minutes" class="w-20 rounded-md border border-slate-200 px-2 py-1 text-right"></td>
                            <td class="px-2 py-2"><input type="text" inputmode="numeric" wire:model.live.debounce.300ms="row.leave_days" class="w-20 rounded-md border border-slate-200 px-2 py-1 text-right"></td>
                            <td class="px-2 py-2"><input type="text" inputmode="numeric" wire:model.live.debounce.300ms="row.allowed_leave_days" class="w-20 rounded-md border border-slate-200 px-2 py-1 text-right"></td>
                            <td class="px-2 py-2"><input type="text" inputmode="numeric" wire:model.blur="row.daily_bonus" oninput="this.value = this.value.replace(/\D/g, '').replace(/\B(?=(\d{3})+(?!\d))/g, '.')" class="w-28 rounded-md border border-slate-200 px-2 py-1 text-right"></td>
                            <td class="px-2 py-2"><input type="text" inputmode="numeric" wire:model.blur="row.supplement" oninput="this.value = this.value.replace(/\D/g, '').replace(/\B(?=(\d{3})+(?!\d))/g, '.')" class="w-28 rounded-md border border-slate-200 px-2 py-1 text-right"></td>
                            <td class="px-2 py-2"><input type="text" inputmode="numeric" wire:model.blur="row.other_money" oninput="this.value = this.value.replace(/\D/g, '').replace(/\B(?=(\d{3})+(?!\d))/g, '.')" class="w-28 rounded-md border border-slate-200 px-2 py-1 text-right"></td>
                            <td class="px-2 py-2"><textarea wire:model.live.debounce.300ms="row.note" class="min-w-[300px] rounded-md border border-slate-200 px-2 py-1 text-[11px]"></textarea></td>
                            <td class="px-2 py-2 text-right tabular-nums font-semibold">{{ number_format((float) ($row['total_salary'] ?? 0), 0, ',', '.') }}</td>
                            <td class="px-2 py-2 text-right tabular-nums font-bold text-emerald-600">{{ number_format((float) ($row['net_received'] ?? 0), 0, ',', '.') }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="flex items-center justify-end gap-3 border-t border-slate-200 px-5 py-4">
                <button type="button" wire:click="close" class="inline-flex h-10 items-center justify-center rounded-md border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">Close</button>
                <button type="button" wire:click="save" class="inline-flex h-10 items-center justify-center rounded-md bg-blue-500 px-4 text-sm font-bold text-white hover:bg-blue-600">Save</button>
            </div>
        </div>
    </div>
@endif
</div>
