"""CPU-only local background-removal endpoint for XLAP."""

from io import BytesIO

from fastapi import FastAPI, File, HTTPException, UploadFile
from fastapi.responses import Response
from PIL import Image
from rembg import new_session, remove

MAX_IMAGE_BYTES = 20 * 1024 * 1024
app = FastAPI(title="XLAP local rembg")
session = new_session("isnet-general-use")


@app.get("/health")
def health() -> dict[str, str]:
    return {"status": "ok", "model": "isnet-general-use"}


@app.post("/remove-background")
async def remove_background(image: UploadFile = File(...)) -> Response:
    image_bytes = await image.read()

    if not image_bytes or len(image_bytes) > MAX_IMAGE_BYTES:
        raise HTTPException(status_code=422, detail="Image must be between 1 byte and 20 MB.")

    try:
        # Convert input to RGBA before inference; rembg returns a PNG with soft alpha edges.
        source = Image.open(BytesIO(image_bytes)).convert("RGBA")
        output = remove(source, session=session)
        buffer = BytesIO()
        output.save(buffer, format="PNG", optimize=True)
    except Exception as exception:
        raise HTTPException(status_code=422, detail="Cannot process image.") from exception

    return Response(content=buffer.getvalue(), media_type="image/png")
