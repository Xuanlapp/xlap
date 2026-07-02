<?php

namespace App\Livewire\Modals\Salary;

use App\Livewire\Pages\Salary\Wali;
use App\Models\DataSalaryZhuzhu;
use App\Models\DataSalaryZhuzhuEmployee;
use App\Models\DataSalaryZhuzhuPeriod;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\WithFileUploads;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Throwable;

class AddEmployee extends Component
{
    use WithFileUploads;

    public bool $isOpen = false;
    public string $employeeName = '';
    public string $baseSalary = '';
    public string $salaryMonth = '';
    public TemporaryUploadedFile|null $avatar = null;

    #[On('openModal')]
    public function openModal(string $component, array $arguments = []): void
    {
        if ($component !== 'modals.salary.add-employee') {
            return;
        }

        $this->open((string) ($arguments['salaryMonth'] ?? now()->format('Y-m')));
    }

    public function open(string $salaryMonth): void
    {
        $this->resetValidation();
        $this->salaryMonth = preg_match('/^\d{4}-\d{2}$/', $salaryMonth) ? $salaryMonth : now()->format('Y-m');
        $this->employeeName = '';
        $this->baseSalary = '';
        $this->avatar = null;
        $this->isOpen = true;
    }

    public function close(): void
    {
        $this->resetValidation();
        $this->reset(['isOpen', 'employeeName', 'baseSalary', 'salaryMonth', 'avatar']);
    }

    public function save(): void
    {
        try {
            $validated = $this->validate([
                'employeeName' => ['required', 'string', 'max:255'],
                'baseSalary' => ['required', 'string'],
                'avatar' => ['nullable', 'image', 'max:4096'],
            ], [
                'employeeName.required' => 'Ten nhan vien la bat buoc.',
                'baseSalary.required' => 'Luong co ban la bat buoc.',
                'baseSalary.string' => 'Luong co ban khong hop le.',
                'avatar.image' => 'Anh nhan vien phai la file hinh.',
                'avatar.max' => 'Anh nhan vien toi da 4MB.',
            ]);

            $avatarPath = $this->storeOptimizedAvatar($this->avatar);

            $employee = DataSalaryZhuzhuEmployee::updateOrCreate(
                ['user_id' => auth()->id(), 'employee_name' => trim($validated['employeeName'])],
                [
                    'base_salary' => $this->parseMoney($validated['baseSalary']),
                    'is_active' => true,
                    'avatar_path' => $avatarPath,
                ]
            );

            if ($avatarPath === null && $employee->avatar_path === null && $this->avatar !== null) {
                $this->dispatch('toast', type: 'warning', title: 'Canh bao', message: 'Khong toi uu duoc anh, vui long thu file khac.');
            }

            $month = CarbonImmutable::createFromFormat('Y-m', $this->salaryMonth)->startOfMonth();

            DataSalaryZhuzhuPeriod::updateOrCreate([
                'user_id' => auth()->id(),
                'salary_month' => $month->toDateString(),
            ]);

            DataSalaryZhuzhu::updateOrCreate(
                [
                    'user_id' => auth()->id(),
                    'employee_id' => $employee->id,
                    'salary_month' => $month->toDateString(),
                ],
                [
                    'user_id' => auth()->id(),
                    'employee_id' => $employee->id,
                    'employee_name' => $employee->employee_name,
                    'salary_month' => $month->toDateString(),
                    'base_salary' => (float) $employee->base_salary,
                ]
            );

            $this->dispatch('toast', type: 'success', title: 'Da them!', message: 'Da tao nhan vien moi thanh cong.');
            $this->dispatch('wali-salary-updated')->to(Wali::class);
            $this->close();
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);
            $this->dispatch('toast', type: 'error', title: 'Loi', message: 'Khong the them nhan vien. Vui long thu lai.');
        }
    }

    public function render(): View
    {
        return view('livewire.modals.salary.add-employee');
    }

    private function parseMoney(mixed $value): float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        $normalized = trim((string) $value);
        $normalized = str_replace(' ', '', $normalized);

        if (str_contains($normalized, ',') && str_contains($normalized, '.')) {
            if (strrpos($normalized, ',') > strrpos($normalized, '.')) {
                $normalized = str_replace('.', '', $normalized);
                $normalized = str_replace(',', '.', $normalized);
            } else {
                $normalized = str_replace(',', '', $normalized);
            }
        } elseif (str_contains($normalized, ',')) {
            $normalized = str_replace('.', '', $normalized);
            $normalized = str_replace(',', '.', $normalized);
        } elseif (preg_match('/^\d{1,3}(\.\d{3})+$/', $normalized)) {
            $normalized = str_replace('.', '', $normalized);
        }

        return is_numeric($normalized) ? (float) $normalized : 0.0;
    }

    private function storeOptimizedAvatar(?TemporaryUploadedFile $file): ?string
    {
        if (! $file) {
            return null;
        }

        $source = $file->getRealPath();
        if (! $source) {
            return null;
        }

        $imageInfo = @getimagesize($source);
        if (! $imageInfo) {
            return null;
        }

        [$width, $height, $imageType] = $imageInfo;

        $image = match ($imageType) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($source),
            IMAGETYPE_PNG => @imagecreatefrompng($source),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($source) : null,
            default => null,
        };

        if (! $image) {
            $extension = strtolower($file->getClientOriginalExtension() ?: 'jpg');
            return $file->storeAs('salary-employees', uniqid('avatar_', true).'.'.$extension, 'public');
        }

        $maxDimension = 1200;
        $ratio = min($maxDimension / max($width, 1), $maxDimension / max($height, 1), 1);
        $targetWidth = max((int) round($width * $ratio), 1);
        $targetHeight = max((int) round($height * $ratio), 1);

        $canvas = imagecreatetruecolor($targetWidth, $targetHeight);
        $white = imagecolorallocate($canvas, 255, 255, 255);
        imagefill($canvas, 0, 0, $white);
        imagecopyresampled($canvas, $image, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

        $relativePath = 'salary-employees/'.uniqid('avatar_', true).'.jpg';
        $fullPath = Storage::disk('public')->path($relativePath);
        $dir = dirname($fullPath);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        imagejpeg($canvas, $fullPath, 78);
        imagedestroy($canvas);
        imagedestroy($image);

        return $relativePath;
    }
}
