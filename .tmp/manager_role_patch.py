from pathlib import Path

# User model role field + helper
p = Path('app/Models/User.php')
text = p.read_text(encoding='utf-8')
text = text.replace("#[Fillable(['name', 'username', 'email', 'password', 'avatar_path', 'status', 'is_admin', 'can_generate_amazon_listing', 'can_generate_etsy_listing', 'can_access_wali'])]", "#[Fillable(['name', 'username', 'email', 'password', 'avatar_path', 'status', 'role', 'is_admin', 'can_generate_amazon_listing', 'can_generate_etsy_listing', 'can_access_wali'])]")
text = text.replace("            'is_admin' => 'boolean',\n", "            'role' => 'string',\n            'is_admin' => 'boolean',\n")
if "public function isManager(): bool" not in text:
    text = text.replace("    /**\n     * Products this user can access.\n     */\n", "    public function isManager(): bool\n    {\n        return $this->role === 'manager';\n    }\n\n    /**\n     * Products this user can access.\n     */\n")
p.write_text(text, encoding='utf-8')

# Create/update migration for role and admin flags
p = Path('database/migrations/2026_07_03_095000_add_role_to_users_table.php')
p.write_text("""<?php\n\nuse Illuminate\\Database\\Migrations\\Migration;\nuse Illuminate\\Database\\Schema\\Blueprint;\nuse Illuminate\\Support\\Facades\\Schema;\n\nreturn new class extends Migration\n{\n    public function up(): void\n    {\n        Schema::table('users', function (Blueprint $table) {\n            if (! Schema::hasColumn('users', 'role')) {\n                $table->string('role')->default('user')->after('status');\n            }\n        });\n    }\n\n    public function down(): void\n    {\n        Schema::table('users', function (Blueprint $table) {\n            if (Schema::hasColumn('users', 'role')) {\n                $table->dropColumn('role');\n            }\n        });\n    }\n};\n""", encoding='utf-8')

# Admin middleware allow only admin role OR legacy is_admin
p = Path('app/Http/Middleware/EnsureUserIsAdmin.php')
text = p.read_text(encoding='utf-8')
text = text.replace("        abort_unless((bool) $request->user()?->is_admin, 403);", "        $user = $request->user();\n\n        abort_unless($user && ((bool) $user->is_admin || $user->role === 'admin'), 403);")
p.write_text(text, encoding='utf-8')

# Wali middleware no longer grants admin automatically unless explicit can_access_wali, but admin can still if role admin? keep only explicit/admin? user asked Wali only when granted
p = Path('app/Http/Middleware/EnsureUserHasWaliAccess.php')
text = p.read_text(encoding='utf-8')
text = text.replace("        abort_unless($user && ((bool) $user->is_admin || (bool) $user->can_access_wali), 403);", "        abort_unless($user && ((bool) $user->can_access_wali || $user->role === 'admin'), 403);")
p.write_text(text, encoding='utf-8')

# Navigation: admin section only for true admins, use role for manager? and wali visibility per flag
p = Path('resources/views/livewire/layout/navigation.blade.php')
text = p.read_text(encoding='utf-8')
text = text.replace("$canAccessWali = (bool) ($user && ((bool) $user->is_admin || (bool) $user->can_access_wali));", "$canAccessWali = (bool) ($user && ((bool) $user->can_access_wali));")
text = text.replace("$isWaliOnly = (bool) ($user && (bool) $user->can_access_wali && ! (bool) $user->is_admin && $products->isEmpty());", "$isWaliOnly = (bool) ($user && (bool) $user->can_access_wali && ! ((bool) $user->is_admin || $user->role === 'admin') && $products->isEmpty());")
text = text.replace("@if (auth()->user()->is_admin)", "@if (auth()->user()->role === 'admin' || auth()->user()->is_admin)")
p.write_text(text, encoding='utf-8')

# Add role select in add/edit user views and validation fields
for path in ['app/Livewire/Modals/Admin/AddUser.php','app/Livewire/Modals/Admin/EditUser.php']:
    p = Path(path)
    text = p.read_text(encoding='utf-8')
    if 'public string $role = \'user\';' not in text:
        text = text.replace("    public string $status = 'active';\n", "    public string $status = 'active';\n\n    public string $role = 'user';\n")
    text = text.replace("            'status' => ['required', Rule::in(['active', 'inactive'])],\n", "            'status' => ['required', Rule::in(['active', 'inactive'])],\n            'role' => ['required', Rule::in(['user', 'manager', 'admin'])],\n")
    text = text.replace("                'status' => $validated['status'],\n                'is_admin' => (bool) ($validated['is_admin'] ?? false),\n", "                'status' => $validated['status'],\n                'role' => $validated['role'],\n                'is_admin' => (bool) ($validated['is_admin'] ?? false) || $validated['role'] === 'admin',\n")
    if path.endswith('EditUser.php'):
        text = text.replace("        $this->status = $user->status ?: 'active';\n", "        $this->status = $user->status ?: 'active';\n        $this->role = $user->role ?: ((bool) $user->is_admin ? 'admin' : 'user');\n")
        text = text.replace("                'status' => $validated['status'],\n                'is_admin' => (bool) ($validated['is_admin'] ?? false),\n", "                'status' => $validated['status'],\n                'role' => $validated['role'],\n                'is_admin' => (bool) ($validated['is_admin'] ?? false) || $validated['role'] === 'admin',\n")
    else:
        text = text.replace("                'status' => (bool) ($validated['status'] ?? 'active'),\n", "                'status' => $validated['status'],\n")
    text = text.replace("            'status',\n", "            'status',\n            'role',\n")
    p.write_text(text, encoding='utf-8')

# Add role select in add/edit blades, remove proxy access old section remains already removed
for path in ['resources/views/livewire/modals/admin/add-user.blade.php','resources/views/livewire/modals/admin/edit-user.blade.php']:
    p = Path(path)
    text = p.read_text(encoding='utf-8')
    text = text.replace("                                <label class=\"flex items-center gap-2 rounded-lg bg-white px-3 py-2 text-sm shadow-sm\">\n                                    <input wire:model=\"is_admin\" type=\"checkbox\" class=\"rounded border-slate-300 text-cyan-600\">\n                                    <span>Cho phep vao Admin</span>\n                                </label>", "                                <label class=\"flex items-center gap-2 rounded-lg bg-white px-3 py-2 text-sm shadow-sm\">\n                                    <input wire:model=\"is_admin\" type=\"checkbox\" class=\"rounded border-slate-300 text-cyan-600\">\n                                    <span>Cho phep vao Admin</span>\n                                </label>\n                                <div class=\"rounded-lg bg-white px-3 py-2 shadow-sm\">\n                                    <label class=\"text-xs font-semibold text-slate-500\">Role</label>\n                                    <select wire:model=\"role\" class=\"mt-1 h-9 w-full rounded-md border-slate-200 text-sm text-slate-950\">\n                                        <option value=\"user\">User</option>\n                                        <option value=\"manager\">Manager</option>\n                                        <option value=\"admin\">Admin</option>\n                                    </select>\n                                </div>")
    text = text.replace("                                <label class=\"flex items-center gap-2 rounded-lg bg-white px-3 py-2 text-sm shadow-sm\">\n                                    <input wire:model=\"is_admin\" type=\"checkbox\" class=\"rounded border-slate-300 text-cyan-600\">\n                                    <span>Cho phep vao Admin</span>\n                                </label>", "                                <label class=\"flex items-center gap-2 rounded-lg bg-white px-3 py-2 text-sm shadow-sm\">\n                                    <input wire:model=\"is_admin\" type=\"checkbox\" class=\"rounded border-slate-300 text-cyan-600\">\n                                    <span>Cho phep vao Admin</span>\n                                </label>\n                                <div class=\"rounded-lg bg-white px-3 py-2 shadow-sm\">\n                                    <label class=\"text-xs font-semibold text-slate-500\">Role</label>\n                                    <select wire:model=\"role\" class=\"mt-1 h-9 w-full rounded-md border-slate-200 text-sm text-slate-950\">\n                                        <option value=\"user\">User</option>\n                                        <option value=\"manager\">Manager</option>\n                                        <option value=\"admin\">Admin</option>\n                                    </select>\n                                </div>")
    p.write_text(text, encoding='utf-8')
