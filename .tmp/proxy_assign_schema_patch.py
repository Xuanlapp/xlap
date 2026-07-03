from pathlib import Path

# DataHubProxyItem model
p = Path('app/Models/DataHubProxyItem.php')
text = p.read_text(encoding='utf-8')
text = text.replace("use Illuminate\\Database\\Eloquent\\Relations\\BelongsTo;", "use Illuminate\\Database\\Eloquent\\Relations\\BelongsTo;")
text = text.replace("        'note',\n        'public_ip_v6',", "        'note',\n        'assigned_user_id',\n        'public_ip_v6',")
text = text.replace("            'changed_at' => 'datetime',\n", "            'assigned_user_id' => 'integer',\n            'changed_at' => 'datetime',\n")
if "public function assignedUser(): BelongsTo" not in text:
    text = text.replace("    public function proxy(): BelongsTo\n    {\n        return $this->belongsTo(DataHubProxy::class, 'data_hub_proxy_id');\n    }\n", "    public function proxy(): BelongsTo\n    {\n        return $this->belongsTo(DataHubProxy::class, 'data_hub_proxy_id');\n    }\n\n    public function assignedUser(): BelongsTo\n    {\n        return $this->belongsTo(User::class, 'assigned_user_id');\n    }\n")
p.write_text(text, encoding='utf-8')

# Repository: admin all, normal user only assigned items
p = Path('app/Repositories/Proxy/DataHubProxyRepository.php')
text = p.read_text(encoding='utf-8')
old = "'items' => fn ($query) => $query->orderByRaw(\"CASE WHEN ppp REGEXP '^mvlan[0-9]+$' THEN CAST(SUBSTRING(ppp, 6) AS UNSIGNED) ELSE 999999 END\")->orderBy('ppp'),"
text = text.replace(old, "'items' => fn ($query) => $this->orderedItems($query)->with('assignedUser'),", 1)
text = text.replace(old, "'items' => fn ($query) => $this->orderedItems($query)->where('assigned_user_id', $user->id)->with('assignedUser'),", 1)
if "private function orderedItems" not in text:
    text = text.replace("    public function activeOrdered(): Collection\n    {\n        return DataHubProxy::query()\n            ->where('is_active', true)\n            ->orderBy('name')\n            ->get();\n    }\n}", "    public function activeOrdered(): Collection\n    {\n        return DataHubProxy::query()\n            ->where('is_active', true)\n            ->orderBy('name')\n            ->get();\n    }\n\n    private function orderedItems($query)\n    {\n        return $query\n            ->orderByRaw(\"CASE WHEN ppp REGEXP '^mvlan[0-9]+$' THEN CAST(SUBSTRING(ppp, 6) AS UNSIGNED) ELSE 999999 END\")\n            ->orderBy('ppp');\n    }\n}")
p.write_text(text, encoding='utf-8')
