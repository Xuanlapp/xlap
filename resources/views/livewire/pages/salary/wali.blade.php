<div class="min-h-[calc(100vh-4rem)] bg-[#f3f4f6] text-slate-950">
    <div class="mx-auto max-w-[1520px] px-4 py-5 sm:px-6 lg:px-8">
        <div class="mb-4 overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-col gap-3 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex min-w-0 items-center gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-blue-50 text-blue-600">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 2v4" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16 2v4" />
                            <rect x="3" y="5" width="18" height="16" rx="3" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18" />
                        </svg>
                    </div>
                    <div class="min-w-0">
                        <h1 class="text-base font-bold text-slate-950">Wali ZhuZhu</h1>
                        <p class="mt-0.5 text-xs text-slate-500">Quản lý theo tháng</p>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <label class="inline-flex h-9 items-center gap-2 rounded-md border border-slate-200 bg-white px-2.5 text-xs font-semibold text-slate-500">
                        <span>Nam</span>
                        <select
                            wire:model.live="selectedYear"
                            class="h-7 rounded-md border-0 bg-slate-100 py-0 pl-2 pr-7 text-xs font-semibold text-slate-700 focus:ring-1 focus:ring-blue-300"
                        >
                            @foreach ($yearOptions as $year)
                                <option value="{{ $year }}">{{ $year }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="inline-flex h-9 items-center gap-2 rounded-md border border-slate-200 bg-white px-2.5 text-xs font-semibold text-slate-500">
                        <span>Thang</span>
                        <select
                            wire:model.live="selectedMonth"
                            class="h-7 rounded-md border-0 bg-slate-100 py-0 pl-2 pr-7 text-xs font-semibold text-slate-700 focus:ring-1 focus:ring-blue-300"
                        >
                            @foreach ($monthOptions as $month)
                                <option value="{{ $month['value'] }}">{{ $month['label'] }}</option>
                            @endforeach
                        </select>
                    </label>

                    <button
                        type="button"
                        class="inline-flex h-9 items-center justify-center gap-2 rounded-md border border-slate-200 bg-white px-3 text-xs font-semibold text-slate-700 transition hover:border-emerald-200 hover:bg-emerald-50 hover:text-emerald-700"
                    >
                        Xuất excel
                    </button>

                    <button
                        type="button"
                        wire:click="openMonthSummary"
                        wire:loading.attr="disabled"
                        wire:target="openMonthSummary"
                        class="inline-flex h-9 items-center justify-center gap-2 rounded-md border border-slate-200 bg-white px-3 text-xs font-semibold text-slate-700 transition hover:border-violet-200 hover:bg-violet-50 hover:text-violet-700 disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        <svg wire:loading wire:target="openMonthSummary" class="h-3.5 w-3.5 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4z"></path>
                        </svg>
                        <span wire:loading.remove wire:target="openMonthSummary">Tổng kết tháng</span>
                        <span wire:loading wire:target="openMonthSummary">Loading...</span>
                    </button>

                    <button
                        type="button"
                        wire:click="openAddEmployee"
                        wire:loading.attr="disabled"
                        wire:target="openAddEmployee"
                        class="inline-flex h-9 items-center justify-center gap-2 rounded-md bg-blue-500 px-3 text-xs font-bold text-white shadow-sm transition hover:bg-blue-600 focus:outline-none focus:ring-4 focus:ring-blue-200 disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        <svg wire:loading wire:target="openAddEmployee" class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4z"></path>
                        </svg>
                        <span wire:loading.remove wire:target="openAddEmployee">Thêm nhân viên</span>
                        <span wire:loading wire:target="openAddEmployee">Loading...</span>
                    </button>
                </div>
            </div>
        </div>

        <div class="mb-4 grid gap-4 md:grid-cols-3">
            <div class="overflow-hidden rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Nhân viên</p>
                <p class="mt-2 text-2xl font-bold text-slate-950">{{ $summary['employees'] }}</p>
                <p class="mt-1 text-xs text-slate-500">Tổng số nhân viên trong kỳ này</p>
            </div>
            <div class="overflow-hidden rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Tổng lương</p>
                <p class="mt-2 text-2xl font-bold text-violet-600">{{ number_format($summary['total_salary'], 0, ',', '.') }}</p>
                <p class="mt-1 text-xs text-slate-500">Tổng số tiền phải trả</p>
            </div>
            <div class="overflow-hidden rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Thực nhận</p>
                <p class="mt-2 text-2xl font-bold text-emerald-600">{{ number_format($summary['net_received'], 0, ',', '.') }}</p>
                <p class="mt-1 text-xs text-slate-500">Tổng thực nhận</p>
            </div>
        </div>

        <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-4 py-3">
                <h2 class="text-sm font-bold text-slate-950">Danh sách lương {{ $monthLabel }}</h2>
                <p class="mt-1 text-xs text-slate-500">Chi hien thi cac ky luong da co du lieu.</p>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-[10px] leading-tight">
                    <thead class="bg-slate-50 text-[9px] font-semibold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-2 py-2 text-left align-middle">Nhân viên</th>
                            <th class="px-2 py-2 text-right align-middle">Lương cơ bản</th>
                            <th class="px-2 py-2 text-right align-middle">Lương cứng biến động</th>
                            <th class="px-2 py-2 text-right align-middle">Điểm hiệu suất</th>
                            <th class="px-2 py-2 text-right align-middle">Đi trễ( Phút)</th>
                            <th class="px-2 py-2 text-right align-middle">Điểm trừ</th>
                            <th class="px-2 py-2 text-right align-middle">Xin Nghỉ</th>
                            <th class="px-2 py-2 text-right align-middle">Số ngày được nghỉ</th>
                            <th class="px-2 py-2 text-right align-middle">Nghỉ vượt</th>
                            <th class="px-2 py-2 text-right align-middle">Công chuẩn</th>
                            <th class="px-2 py-2 text-right align-middle">Công thực tế</th>
                            <th class="px-2 py-2 text-right align-middle">Điểm tính lương</th>
                            <th class="px-2 py-2 text-right align-middle">Thưởng ngày</th>
                            <th class="px-2 py-2 text-right align-middle">Bổ sung</th>
                            <th class="px-2 py-2 text-right align-middle">Tiền khác</th>
                            <th class="px-2 py-2 text-left align-middle">Note</th>
                            <th class="px-2 py-2 text-right align-middle">Tổng lương</th>
                            <th class="px-2 py-2 text-right align-middle">Tiền điểm lẻ</th>
                            <th class="px-2 py-2 text-right align-middle">Hoa hồng</th>
                            <th class="px-2 py-2 text-right align-middle">Thực nhận</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($rows as $row)
                            <tr wire:key="salary-row-{{ $selectedYear }}-{{ $selectedMonth }}-{{ $row->employee_id ?? $row->id }}" wire:click="openEmployeeSalary({{ $row->employee_id ?? $row->id }})" class="cursor-pointer hover:bg-slate-50">
                                <td class="px-2 py-2 align-top">
                                    <div class="flex items-center gap-3">
                                        <div class="flex h-8 w-8 items-center justify-center rounded-full bg-blue-100 text-[10px] font-bold text-blue-600">
                                            {{ mb_strtoupper(mb_substr((string) $row->employee_name, 0, 2)) }}
                                        </div>
                                        <div>
                                            <p class="font-semibold text-[10px] text-slate-950">{{ $row->employee_name }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="whitespace-nowrap px-2 py-2 text-right tabular-nums text-slate-700">{{ number_format((float) $row->base_salary, 0, ',', '.') }}</td>
                                <td class="whitespace-nowrap px-2 py-2 text-right tabular-nums text-slate-700">{{ number_format((float) $row->variable_salary, 0, ',', '.') }}</td>
                                <td class="whitespace-nowrap px-2 py-2 text-right tabular-nums text-slate-700">{{ number_format((float) $row->performance_score, 0, ',', '.') }}</td>
                                <td class="whitespace-nowrap px-2 py-2 text-right tabular-nums text-slate-700">{{ number_format((float) $row->late_minutes, 0, ',', '.') }}</td>
                                <td class="whitespace-nowrap px-3 py-4 text-right tabular-nums text-rose-600">{{ number_format((float) $row->late_days, 0, ',', '.') }}</td>
                                <td class="whitespace-nowrap px-2 py-2 text-right tabular-nums text-slate-700">{{ number_format((float) $row->leave_days, 0, ',', '.') }}</td>
                                <td class="whitespace-nowrap px-2 py-2 text-right tabular-nums text-slate-700">{{ number_format((float) $row->allowed_leave_days, 0, ',', '.') }}</td>
                                <td class="whitespace-nowrap px-2 py-2 text-right tabular-nums text-rose-600">{{ number_format(max(0, (float) $row->leave_days - (float) $row->allowed_leave_days), 0, ',', '.') }}</td>
                                <td class="whitespace-nowrap px-2 py-2 text-right tabular-nums text-slate-700">{{ number_format((float) $row->standard_work_days, 0, ',', '.') }}</td>
                                <td class="whitespace-nowrap px-2 py-2 text-right tabular-nums text-slate-700">{{ number_format((float) $row->actual_work_days, 0, ',', '.') }}</td>
                                <td class="whitespace-nowrap px-3 py-4 text-right tabular-nums font-semibold text-blue-600">{{ number_format((float) $row->score, 0, ',', '.') }}</td>
                                <td class="whitespace-nowrap px-2 py-2 text-right tabular-nums text-slate-700">{{ number_format((float) $row->daily_bonus, 0, ',', '.') }}</td>
                                <td class="whitespace-nowrap px-2 py-2 text-right tabular-nums text-slate-700">{{ number_format((float) $row->supplement, 0, ',', '.') }}</td>
                                <td class="whitespace-nowrap px-2 py-2 text-right tabular-nums text-slate-700">{{ number_format((float) $row->other_money, 0, ',', '.') }}</td>
                                <td class="min-w-[180px] max-w-[220px] px-2 py-2 align-top text-[9px] leading-4 text-slate-500">
                                    <div class="whitespace-pre-line">{{ $row->note ?: '-' }}</div>
                                </td>
                                <td class="whitespace-nowrap px-2 py-2 text-right tabular-nums font-semibold text-slate-950">{{ number_format((float) $row->total_salary, 0, ',', '.') }}</td>
                                <td class="whitespace-nowrap px-2 py-2 text-right tabular-nums text-slate-700">{{ number_format((float) $row->odd_point_money, 0, ',', '.') }}</td>
                                <td class="whitespace-nowrap px-2 py-2 text-right tabular-nums text-slate-700">{{ number_format((float) $row->commission, 0, ',', '.') }}</td>
                                <td class="whitespace-nowrap px-2 py-2 text-right tabular-nums font-bold text-emerald-600">{{ number_format((float) $row->net_received, 0, ',', '.') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="19" class="px-2 py-8 text-center text-[10px] text-slate-400">Chưa có dữ liệu, vui lòng thêm users.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <livewire:modals.salary.month-summary />
    <livewire:modals.salary.add-employee />
    <livewire:modals.salary.edit-employee-salary />
</div>
