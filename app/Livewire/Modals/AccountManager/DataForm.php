<?php

namespace App\Livewire\Modals\AccountManager;

use App\Models\Account;
use App\Models\AccountDetail;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

class DataForm extends Component
{
    public bool $isOpen = false;
    public int $accountId = 0;
    public string $detailType = 'login';
    public array $data = [];

    public const FORMS = [
        'login' => ['Thêm Login Account', ['Email / Username', 'Password', '2FA Type', '2FA Secret Key', 'Ghi chú']],
        'email' => ['Thêm Email Account', ['Email chính', 'Email Password', 'Recovery Email', 'Recovery Phone', 'Nhà cung cấp', 'Ghi chú']],
        'ip_device' => ['Thêm IP / Device', ['IP Address', 'Quốc gia / Khu vực', 'Thành phố', 'Proxy / Provider', 'Device Name', 'Browser / Profile', 'Ngày bắt đầu sử dụng', 'Ngày cập nhật cuối', 'Ghi chú']],
        'identity' => ['Thêm Identity', ['Loại tài khoản', 'Họ và tên', 'Ngày sinh', 'Quốc tịch', 'Địa chỉ', 'ID Type', 'ID Number', 'Ngày hết hạn', 'Ghi chú']],
        'payment_bank' => ['Thêm Bank (Payment)', ['Tên ngân hàng', 'Chủ tài khoản', '4 số đầu tài khoản', '4 số cuối tài khoản', 'Routing / SWIFT', 'Quốc gia', 'Loại tài khoản', 'Currency', 'Ghi chú']],
        'payout_bank' => ['Thêm Bank (Payout)', ['Tên ngân hàng / Ví', 'Chủ tài khoản', 'Email payout (nếu dùng)', '4 số đầu tài khoản', '4 số cuối tài khoản', 'Routing / SWIFT', 'Quốc gia', 'Currency', 'Ghi chú']],
        'card' => ['Thêm Card', ['Loại thẻ', 'Chủ thẻ', 'Số thẻ (chỉ 4 số cuối)', 'Ngày hết hạn', 'Địa chỉ thanh toán', 'Ghi chú']],
    ];

    #[On('openModal')]
    public function openModal(string $component, array $arguments = []): void
    {
        if ($component !== 'modals.account-manager.data-form') return;
        abort_unless((bool) auth()->user()?->is_admin || auth()->user()?->role === 'admin', 403);
        $this->accountId = (int) ($arguments['accountId'] ?? 0);
        $this->detailType = (string) ($arguments['detailType'] ?? 'login');
        abort_unless($this->accountId && isset(self::FORMS[$this->detailType]) && Account::query()->whereKey($this->accountId)->exists(), 404);
        $this->data = array_fill(0, count(self::FORMS[$this->detailType][1]), '');
        $this->isOpen = true;
    }

    public function close(): void { $this->isOpen = false; $this->resetValidation(); }

    public function save(): void
    {
        abort_unless((bool) auth()->user()?->is_admin || auth()->user()?->role === 'admin', 403);
        $rules = ['data' => ['array'], 'data.*' => ['nullable', 'string', 'max:5000']];
        foreach (self::FORMS[$this->detailType][1] as $index => $field) {
            if (in_array($field, ['4 số đầu tài khoản', '4 số cuối tài khoản'], true)) {
                $rules['data.'.$index] = ['required', 'digits:4'];
            }
        }
        $this->validate($rules, [
            'data.*.digits' => 'Số tài khoản chỉ nhập đúng 4 chữ số.',
        ]);
        $payload = collect(self::FORMS[$this->detailType][1])
            ->mapWithKeys(fn (string $field, int $index): array => [$field => $this->data[$index] ?? ''])
            ->filter(fn ($value) => filled($value))
            ->all();
        if ($payload === []) { $this->addError('data', 'Nhập ít nhất một thông tin.'); return; }
        AccountDetail::query()->create(['account_id' => $this->accountId, 'detail_type' => $this->detailType, 'payload' => $payload, 'created_by' => auth()->id()]);
        $this->dispatch('account-manager-updated');
        $this->dispatch('toast', type: 'success', title: 'Đã lưu', message: self::FORMS[$this->detailType][0].' đã được thêm.');
        $this->close();
    }

    public function render(): View { return view('livewire.modals.account-manager.data-form'); }
}
