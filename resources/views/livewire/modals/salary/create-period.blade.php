<div>
@if ($isOpen)
    <div class="fixed inset-0 z-[90] flex items-center justify-center p-4">
        <button type="button" class="absolute inset-0 bg-slate-950/40" wire:click="close" aria-label="Close"></button>
        <div class="relative overflow-y-auto z-[91] w-full max-w-3xl overflow-hidden rounded-xl border border-slate-200 bg-white shadow-2xl">
            <div class="flex items-center justify-between border-b border-slate-200 px-3 py-3">
                <div>
                    <h3 class="text-base font-bold text-slate-950">Tạo kỳ lương</h3>
                    <p class="mt-1 text-xs text-slate-500">Chỉ hiển thị các tháng chưa có ngày lương.</p>
                </div>
                <button type="button" wire:click="close" class="rounded-md p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-700">✕</button>
            </div>

            <div class="grid grid-cols-2 gap-4 border-b border-slate-200 px-3 py-3">
                <div>
                    <label class="block text-xs font-semibold text-slate-700">Tháng</label>
                    <select wire:model.live="month" class="mt-1 w-full rounded-md border border-slate-200 px-1.5 py-2 text-sm outline-none focus:border-blue-400 focus:ring-4 focus:ring-blue-100">
                        @forelse ($monthOptions as $option)
                            <option value="{{ $option['value'] }}">{{ $option['value'] }}</option>
                        @empty
                            <option value="">Het thang co the tao</option>
                        @endforelse
                    </select>
                    @error('month') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700">Năm</label>
                    <select wire:model.live="year" class="mt-1 w-full rounded-md border border-slate-200 px-1.5 py-2 text-sm outline-none focus:border-blue-400 focus:ring-4 focus:ring-blue-100">
                        @foreach ($yearOptions as $option)
                            <option value="{{ $option }}">{{ $option }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-[11px] text-slate-400">Năm chỉ được chọn khi ở tháng 12 hoặc tháng 1(-1 <"năm"< +1).</p>
                    @error('year') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="px-3 py-3">
                <div class="mb-3 rounded-lg border border-blue-100 bg-blue-50 px-1.5 py-2 text-xs text-blue-700">
                    {{ $sourceLabel ?: 'Chon ky de tai danh sach nhan vien.' }}
                </div>

                <div class="max-h-[360px] overflow-auto rounded-lg border border-slate-200">
                    <table class="w-full table-auto text-sm">
                        <thead class="bg-slate-50 text-[11px] font-semibold uppercase text-slate-500">
                            <tr>
                                <th class="w-[1%] whitespace-nowrap px-1.5 py-2 text-center whitespace-normal">Chon</th>
                                <th class="px-1.5 py-2 text-center whitespace-normal">Nhan vien</th>
                                <th class="w-[1%] whitespace-nowrap px-1.5 py-2 text-center whitespace-normal">Luong co ban</th>
                                <th class="w-[1%] whitespace-nowrap px-1.5 py-2 text-center whitespace-normal">Ngay nghi phep</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($employees as $employee)
                                <tr>
                                    <td class="px-1.5 py-2 text-center whitespace-normal">
                                        <input type="checkbox" wire:model="selectedEmployees.{{ $employee['id'] }}" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                                    </td>
                                    <td class="px-1.5 py-2 font-medium text-slate-800">{{ $employee['name'] }}</td>
                                    <td class="px-1.5 py-2 text-right tabular-nums text-slate-700">{{ number_format((float) $employee['base_salary'], 0, ',', '.') }}</td>
                                    <td class="px-1.5 py-2 text-center tabular-nums text-slate-700">{{ $employee['allowed_leave_days'] }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-3 py-8 text-center text-xs text-slate-400">Chua co nhan vien de tao ky luong.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @error('selectedEmployees') <p class="mt-2 text-xs text-rose-600">{{ $message }}</p> @enderror
            </div>

            <div class="flex items-center justify-end gap-3 border-t border-slate-200 px-3 py-3">
                <button type="button" wire:click="close" wire:loading.attr="disabled" wire:target="save,year,month" class="inline-flex h-10 items-center justify-center rounded-md border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-60">Dong</button>
                <button type="button" wire:click="save" wire:loading.attr="disabled" wire:target="save,year,month" class="inline-flex h-10 items-center justify-center gap-2 rounded-md bg-blue-500 px-4 text-sm font-bold text-white hover:bg-blue-600 disabled:cursor-not-allowed disabled:opacity-60">
                    <svg wire:loading wire:target="save,year,month" class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4z"></path>
                    </svg>
                    <span wire:loading.remove wire:target="save,year,month">Tao ky</span>
                    <span wire:loading wire:target="save,year,month">Dang tao...</span>
                </button>
            </div>
        </div>
    </div>
@endif
</div>
