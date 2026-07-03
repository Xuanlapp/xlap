<div>
    @if ($isOpen)
        <div class="fixed inset-0 z-[140] flex items-center justify-center p-4">
            <button type="button" class="absolute inset-0 bg-slate-950/40" wire:click="close" aria-label="Close"></button>

            <div class="relative z-[141] flex max-h-[90vh] w-full max-w-[96vw] flex-col overflow-hidden rounded-xl border border-slate-200 bg-white shadow-2xl">
                <div class="flex items-center justify-between gap-3 border-b border-slate-200 px-3 py-3">
                    <div class="min-w-0">
                        <h3 class="truncate text-base font-bold text-slate-950">Sửa thông tin {{ $row['employee_name'] ?? '' }} kỳ {{ $monthLabel }}</h3>
                        <p class="mt-1 text-xs text-slate-500">Sửa thông tin của nhân viên được chọn.</p>
                    </div>

                    <div class="flex shrink-0 items-center gap-2">
                        <button type="button" wire:click="confirmDelete" class="inline-flex h-9 items-center justify-center rounded-md border border-rose-200 bg-rose-50 px-3 text-xs font-bold text-rose-700 hover:bg-rose-100" wire:loading.attr="disabled" wire:target="confirmDelete,deleteSalaryRow">
                            <span wire:loading.remove wire:target="confirmDelete,deleteSalaryRow">Delete</span>
                            <span wire:loading wire:target="confirmDelete,deleteSalaryRow">...</span>
                        </button>
                        <button type="button" wire:click="close" class="rounded-md p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-700">x</button>
                    </div>
                </div>

                <div class="overflow-auto px-2 py-3">
                    <table class="w-full table-fixed divide-y divide-slate-200 text-[10px] leading-tight">
                        <thead class="sticky top-0 bg-slate-50 text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-1 py-2 text-center whitespace-normal">Nhân viên</th>
                                <th class="px-1 py-2 text-center whitespace-normal">Lương cơ bản</th>
                                <th class="px-1 py-2 text-center whitespace-normal">Điểm hiệu suất</th>
                                <th class="px-1 py-2 text-center whitespace-normal">Đi trễ(Phút)</th>
                                <th class="px-1 py-2 text-center whitespace-normal">Xin nghỉ</th>
                                <th class="px-1 py-2 text-center whitespace-normal">Số ngày được nghỉ</th>
                                <th class="px-1 py-2 text-center whitespace-normal">Thưởng ngày</th>
                                <th class="px-1 py-2 text-center whitespace-normal">Tiền bổ sung</th>
                                <th class="px-1 py-2 text-center whitespace-normal">Tiền khác</th>
                                <th class="px-1 py-2 text-center whitespace-normal">Note</th>
                                <th class="px-1 py-2 text-center whitespace-normal">Tổng lương</th>
                                <th class="px-1 py-2 text-center whitespace-normal">Thực nhận</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr wire:key="edit-employee-salary-{{ $salaryMonth }}-{{ $employeeId }}">
                                <td class="px-1 py-2 truncate font-semibold text-slate-900">{{ $row['employee_name'] ?? '' }}</td>
                                <td class="px-1.5 py-2"><input type="text" inputmode="numeric" wire:model.blur="row.base_salary" oninput="this.value = this.value.replace(/\D/g, '').replace(/\B(?=(\d{3})+(?!\d))/g, '.')" class="w-full rounded-md border border-slate-200 px-1 py-1 text-center tabular-nums"></td>
                                <td class="px-1.5 py-2"><input type="text" inputmode="decimal" wire:model.blur="row.performance_score" oninput="let parts = this.value.replace(/[^\d,]/g, '').split(','); let intPart = (parts.shift() || '').replace(/\D/g, '').replace(/\B(?=(\d{3})+(?!\d))/g, '.'); let decimalPart = parts.join('').replace(/\D/g, '').slice(0, 2); this.value = decimalPart.length ? intPart + ',' + decimalPart : intPart" class="w-full rounded-md border border-slate-200 px-1 py-1 text-center tabular-nums"></td>
                                <td class="px-1.5 py-2"><input type="text" inputmode="numeric" wire:model.blur="row.late_minutes" oninput="this.value = this.value.replace(/\D/g, '').replace(/\B(?=(\d{3})+(?!\d))/g, '.')" class="w-full rounded-md border border-slate-200 px-1 py-1 text-center tabular-nums"></td>
                                <td class="px-1.5 py-2"><input type="text" inputmode="numeric" wire:model.blur="row.leave_days" oninput="let parts = this.value.replace(/[^\d,]/g, '').split(','); let intPart = (parts.shift() || '').replace(/\D/g, '').replace(/\B(?=(\d{3})+(?!\d))/g, '.'); let decimalPart = parts.join('').replace(/\D/g, '').slice(0, 2); this.value = decimalPart.length ? intPart + ',' + decimalPart : intPart" class="w-full rounded-md border border-slate-200 px-1 py-1 text-center tabular-nums"></td>
                                <td class="px-1.5 py-2"><input type="text" inputmode="numeric" wire:model.blur="row.allowed_leave_days" oninput="let parts = this.value.replace(/[^\d,]/g, '').split(','); let intPart = (parts.shift() || '').replace(/\D/g, '').replace(/\B(?=(\d{3})+(?!\d))/g, '.'); let decimalPart = parts.join('').replace(/\D/g, '').slice(0, 2); this.value = decimalPart.length ? intPart + ',' + decimalPart : intPart" class="w-full rounded-md border border-slate-200 px-1 py-1 text-center tabular-nums"></td>
                                <td class="px-1.5 py-2"><input type="text" inputmode="numeric" wire:model.blur="row.daily_bonus" oninput="this.value = this.value.replace(/\D/g, '').replace(/\B(?=(\d{3})+(?!\d))/g, '.')" class="w-full rounded-md border border-slate-200 px-1 py-1 text-center tabular-nums"></td>
                                <td class="px-1.5 py-2"><input type="text" inputmode="numeric" wire:model.blur="row.supplement" oninput="this.value = this.value.replace(/\D/g, '').replace(/\B(?=(\d{3})+(?!\d))/g, '.')" class="w-full rounded-md border border-slate-200 px-1 py-1 text-center tabular-nums"></td>
                                <td class="px-1.5 py-2"><input type="text" inputmode="numeric" wire:model.blur="row.other_money" oninput="this.value = this.value.replace(/\D/g, '').replace(/\B(?=(\d{3})+(?!\d))/g, '.')" class="w-full rounded-md border border-slate-200 px-1 py-1 text-center tabular-nums"></td>
                                <td class="px-1.5 py-2"><textarea wire:model.blur="row.note" class="w-full rounded-md border border-slate-200 px-1.5 py-1 text-[10px]"></textarea></td>
                                <td class="px-1.5 py-2 text-right tabular-nums font-semibold">{{ number_format((float) ($row['total_salary'] ?? 0), 0, ',', '.') }}</td>
                                <td class="px-1.5 py-2 text-right tabular-nums font-bold text-emerald-600">{{ number_format((float) ($row['net_received'] ?? 0), 0, ',', '.') }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="flex items-center justify-end gap-3 border-t border-slate-200 px-3 py-3">
                    <button type="button" wire:click="close" class="inline-flex h-10 items-center justify-center rounded-md border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">Close</button>
                    <button type="button" wire:click="save" class="inline-flex h-10 items-center justify-center rounded-md bg-blue-500 px-4 text-sm font-bold text-white hover:bg-blue-600" wire:loading.attr="disabled" wire:target="save">
                        <span wire:loading.remove wire:target="save">Save</span>
                        <span wire:loading wire:target="save">Saving...</span>
                    </button>
                </div>
            </div>
        </div>

        @if ($confirmingDelete)
            <div class="fixed inset-0 z-[260] flex items-center justify-center p-4">
                <button type="button" class="absolute inset-0 bg-slate-950/70 backdrop-blur-sm" wire:click="cancelDelete" aria-label="Close delete confirm"></button>
                <div class="relative z-[261] w-full max-w-md rounded-xl border border-slate-200 bg-white p-5 shadow-2xl">
                    <h3 class="text-base font-bold text-slate-950">Xác nhận xóa nhân viên</h3>
                    <p class="mt-2 text-sm text-slate-600">
                        {{ auth()->user()->name }} bạn có chắc xóa <span class="font-semibold text-slate-950">{{ $row['employee_name'] ?? '' }}</span> không?
                    </p>
                    <p class="mt-2 text-xs text-slate-400">Chắc chắn xóa nhân viên vào kỳ {{ $monthLabel }}, cân nhắc kỹ lưỡng trước khi xóa nhé.</p>
                    <div class="mt-5 flex items-center justify-end gap-3">
                        <button type="button" wire:click="cancelDelete" class="rounded-md border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50" wire:loading.attr="disabled" wire:target="deleteSalaryRow">No</button>
                        <button type="button" wire:click="deleteSalaryRow" class="rounded-md bg-rose-600 px-4 py-2 text-sm font-bold text-white hover:bg-rose-700" wire:loading.attr="disabled" wire:target="deleteSalaryRow">
                            <span wire:loading.remove wire:target="deleteSalaryRow">Yes</span>
                            <span wire:loading wire:target="deleteSalaryRow">Deleting...</span>
                        </button>
                    </div>
                </div>
            </div>
        @endif
    @endif
</div>
