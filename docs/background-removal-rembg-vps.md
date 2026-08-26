# Local rembg on a CPU VPS

This service keeps the AI model out of PHP and returns a PNG with soft alpha edges. It uses `isnet-general-use`, a practical CPU model for Sticker images.

## Install

Run these commands on the VPS from the XLAP project directory:

```bash
sudo apt-get update
sudo apt-get install -y python3-venv
python3 -m venv services/background-removal/.venv
services/background-removal/.venv/bin/pip install --upgrade pip
services/background-removal/.venv/bin/pip install -r services/background-removal/requirements.txt
```

The first start downloads the model into the service user's cache. Keep it private: Laravel calls only `127.0.0.1:8091`.

## Run with Supervisor

Add the following program to the VPS Supervisor configuration, then run `supervisorctl reread`, `supervisorctl update`, and `supervisorctl start xlap-background-removal`.

```ini
[program:xlap-background-removal]
directory=/www/wwwroot/xlap.tech/services/background-removal
command=/www/wwwroot/xlap.tech/services/background-removal/.venv/bin/uvicorn app:app --host 127.0.0.1 --port 8091 --workers 1
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www
redirect_stderr=true
stdout_logfile=/www/wwwroot/xlap.tech/storage/logs/background-removal.log
```

Verify it locally:

```bash
curl http://127.0.0.1:8091/health
```

Set the global switch to enable background removal, then choose `rembg local (AI CPU)` for Sticker in Admin > product background removal. Do not expose the port publicly. `OFFOREST_LOCAL_REMBG_FALLBACK_ENGINE=magic_eraser` makes a stopped service fall back safely.
