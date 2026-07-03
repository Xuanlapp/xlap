from pathlib import Path
p = Path('app/Models/DataHubProxyItem.php')
text = p.read_text(encoding='utf-8')
text = text.replace("        'public_ip_change',\n        'public_ip_v6',", "        'public_ip_change',\n        'note',\n        'public_ip_v6',")
p.write_text(text, encoding='utf-8')
