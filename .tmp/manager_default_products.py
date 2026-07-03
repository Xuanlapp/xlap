from pathlib import Path

# User::canAccessProduct manager can access any active product
p = Path('app/Models/User.php')
text = p.read_text(encoding='utf-8')
old = """    public function canAccessProduct(string $slug): bool
    {
        return $this->products()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->exists();
    }
"""
new = """    public function canAccessProduct(string $slug): bool
    {
        if ($this->isManager()) {
            return Product::query()
                ->where('slug', $slug)
                ->where('is_active', true)
                ->exists();
        }

        return $this->products()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->exists();
    }
"""
if old not in text:
    raise SystemExit('User::canAccessProduct pattern not found')
text = text.replace(old, new, 1)
p.write_text(text, encoding='utf-8')

# Navigation: manager gets all active products by default
p = Path('resources/views/livewire/layout/navigation.blade.php')
text = p.read_text(encoding='utf-8')
old = """        $products = $user
            ? $user->products()->where('is_active', true)->orderBy('name')->get()
            : collect();
"""
new = """        $products = $user
            ? ($user->isManager()
                ? \App\Models\Product::query()->where('is_active', true)->orderBy('name')->get()
                : $user->products()->where('is_active', true)->orderBy('name')->get())
            : collect();
"""
if old not in text:
    raise SystemExit('navigation products pattern not found')
text = text.replace(old, new, 1)
p.write_text(text, encoding='utf-8')
