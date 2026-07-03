from pathlib import Path

# AutomationCatalog: manager can view all, only normal user scoped
p = Path('app/Livewire/Pages/OrnamentAmazon/AutomationCatalog.php')
text = p.read_text(encoding='utf-8')
text = text.replace("            ->when(! auth()->user()->is_admin, fn (Builder $query) => $query->where('user_id', auth()->id()))", "            ->when(! auth()->user()->is_admin && ! auth()->user()->isManager(), fn (Builder $query) => $query->where('user_id', auth()->id()))")
text = text.replace("        $query = DataOrnamentAmazon::query()->when(! auth()->user()->is_admin, fn (Builder $query) => $query->where('user_id', auth()->id()));", "        $query = DataOrnamentAmazon::query()->when(! auth()->user()->is_admin && ! auth()->user()->isManager(), fn (Builder $query) => $query->where('user_id', auth()->id()));")
p.write_text(text, encoding='utf-8')

# MarketplaceExports: manager can view all but export only own
p = Path('app/Livewire/Pages/Marketplace/MarketplaceExports.php')
text = p.read_text(encoding='utf-8')
text = text.replace("        $assets = $this->readyQuery()\n            ->whereKey($selectedIds->all())", "        $assets = $this->exportableQuery()\n            ->whereKey($selectedIds->all())")
text = text.replace("        $selectedIds = $this->selectedIds();\n\n        if ($selectedIds->isEmpty()) {", "        $selectedIds = $this->selectedIds();\n\n        if (auth()->user()->isManager()) {\n            $selectedIds = $this->exportableQuery()->whereKey($selectedIds->all())->pluck('id');\n        }\n\n        if ($selectedIds->isEmpty()) {")
# insert exportableQuery before selectedIds maybe near readyQuery methods
marker = "    private function readyQuery(): Builder\n"
if marker in text and "private function exportableQuery(): Builder" not in text:
    insert = "    private function exportableQuery(): Builder\n    {\n        return $this->readyQuery()\n            ->when(auth()->user()->isManager(), fn (Builder $query) => $query->where('user_id', auth()->id()));\n    }\n\n"
    text = text.replace(marker, insert + marker)
# widen view query if current code scopes non-admin; replace patterns broadly
text = text.replace("->when(! auth()->user()->is_admin, fn (Builder $query) => $query->where('user_id', auth()->id()))", "->when(! auth()->user()->is_admin && ! auth()->user()->isManager(), fn (Builder $query) => $query->where('user_id', auth()->id()))")
p.write_text(text, encoding='utf-8')

# DriveUploads: manager can view all, export by id only own when manager
p = Path('app/Livewire/Pages/Drive/DriveUploads.php')
text = p.read_text(encoding='utf-8')
text = text.replace("            ->when(! auth()->user()->is_admin, fn (Builder $query) => $query->where('user_id', auth()->id()))", "            ->when(! auth()->user()->is_admin && ! auth()->user()->isManager(), fn (Builder $query) => $query->where('user_id', auth()->id()))")
text = text.replace("            ->when(! auth()->user()->is_admin, fn (Builder $query) => $query->where('user_id', auth()->id()));", "            ->when(! auth()->user()->is_admin && ! auth()->user()->isManager(), fn (Builder $query) => $query->where('user_id', auth()->id()));")
# export action guard: if manager, enforce own asset
text = text.replace("            $count = app(ApprovedAssetDriveExportService::class)->exportAssetById(", "            if (auth()->user()->isManager() && $upload->user_id !== auth()->id()) {\n                throw new \\RuntimeException('Manager chỉ được export dữ liệu của chính mình.');\n            }\n\n            $count = app(ApprovedAssetDriveExportService::class)->exportAssetById(")
p.write_text(text, encoding='utf-8')
