import { access, mkdir, readFile, readdir, writeFile } from "node:fs/promises";
import { constants } from "node:fs";
import path from "node:path";
import { spawn } from "node:child_process";
import { fileURLToPath } from "node:url";
import { PNG } from "pngjs";

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const rootDir = path.resolve(__dirname, "..");
const templatePath = "D:/FFactory/FILE/template/Template_UVDTF.ai";
const imagesDir = "D:/FFactory/FILE/Images";
const runtimeDir = path.join(rootDir, ".runtime");

function runCommand(command: string, args: string[]) {
  return new Promise<void>((resolve, reject) => {
    const child = spawn(command, args, { stdio: "inherit" });
    child.on("error", reject);
    child.on("exit", (code) => {
      if (code === 0) return resolve();
      reject(new Error(`${command} exited with code ${code ?? "unknown"}`));
    });
  });
}

async function ensurePathExists(filePath: string) {
  await access(filePath, constants.F_OK);
}

async function getFirstPngImage(directoryPath: string) {
  const entries = await readdir(directoryPath, { withFileTypes: true });
  const pngFiles = entries
    .filter((entry) => entry.isFile() && entry.name.toLowerCase().endsWith(".png"))
    .map((entry) => entry.name)
    .sort((left, right) => left.localeCompare(right, "en"));

  if (pngFiles.length === 0) {
    throw new Error(`Khong tim thay file .png trong thu muc ${directoryPath}`);
  }

  return path.join(directoryPath, pngFiles[0]);
}

function toJsxPath(filePath: string) {
  return filePath.replace(/\\/g, "/");
}

function parseSideCount(filePath: string) {
  const fileName = path.basename(filePath).toLowerCase();
  const match = fileName.match(/(?:^|[-_])(\d+)-side(?:[-_.]|$)/);
  if (!match) return 1;
  return Number(match[1]);
}

function parseItemSizeInch(filePath: string) {
  const fileName = path.basename(filePath).toLowerCase();
  const match = fileName.match(/(?:^|[-_])(\d+(?:-\d+)?)in(?:[-_.]|$)/);
  if (!match) return 0;
  return Number(match[1].replace(/-/g, '.'));
}

function isVisiblePixel(data: Buffer<ArrayBufferLike>, width: number, x: number, y: number) {
  const offset = (y * width + x) << 2;
  const red = data[offset];
  const green = data[offset + 1];
  const blue = data[offset + 2];
  const alpha = data[offset + 3];
  if (alpha <= 10) return false;
  if (red > 245 && green > 245 && blue > 245) return false;
  return true;
}

async function analyzeColoredComponents(pngPath: string) {
  const buffer = await readFile(pngPath);
  const image = PNG.sync.read(buffer);
  const { width, height, data } = image;
  const visited = new Uint8Array(width * height);
  const components: Array<{ minX: number; minY: number; maxX: number; maxY: number; pixelCount: number; rowExtremes: Record<number, { minX: number; maxX: number }> }> = [];

  for (let y = 0; y < height; y += 1) {
    for (let x = 0; x < width; x += 1) {
      const startIndex = y * width + x;
      if (visited[startIndex] === 1 || !isVisiblePixel(data, width, x, y)) continue;

      const stack: Array<[number, number]> = [[x, y]];
      visited[startIndex] = 1;
      let minX = x;
      let minY = y;
      let maxX = x;
      let maxY = y;
      let pixelCount = 0;
      const rowExtremes: Record<number, { minX: number; maxX: number }> = {};

      while (stack.length > 0) {
        const current = stack.pop();
        if (!current) continue;
        const [cx, cy] = current;
        pixelCount += 1;
        if (!rowExtremes[cy]) rowExtremes[cy] = { minX: cx, maxX: cx };
        if (cx < rowExtremes[cy].minX) rowExtremes[cy].minX = cx;
        if (cx > rowExtremes[cy].maxX) rowExtremes[cy].maxX = cx;
        if (cx < minX) minX = cx;
        if (cy < minY) minY = cy;
        if (cx > maxX) maxX = cx;
        if (cy > maxY) maxY = cy;

        const neighbors: Array<[number, number]> = [[cx - 1, cy], [cx + 1, cy], [cx, cy - 1], [cx, cy + 1]];
        for (const [nx, ny] of neighbors) {
          if (nx < 0 || nx >= width || ny < 0 || ny >= height) continue;
          const nextIndex = ny * width + nx;
          if (visited[nextIndex] === 1 || !isVisiblePixel(data, width, nx, ny)) continue;
          visited[nextIndex] = 1;
          stack.push([nx, ny]);
        }
      }

      components.push({ minX, minY, maxX, maxY, pixelCount, rowExtremes });
    }
  }

  const sorted = components.sort((a, b) => {
    if (a.minY !== b.minY) return a.minY - b.minY;
    return a.minX - b.minX;
  });

  return {
    imageWidthPx: width,
    imageHeightPx: height,
    componentCount: sorted.length,
    components: sorted.map((component) => {
      const ys = Object.keys(component.rowExtremes).map((value) => Number(value)).sort((a, b) => a - b);
      const left = ys.map((y) => ({ x: component.rowExtremes[y].minX, y }));
      const right = ys.slice().reverse().map((y) => ({ x: component.rowExtremes[y].maxX, y }));
      const outline = left.concat(right);
      const sampledOutline = outline.filter((_, index) => index % 4 === 0 || index === outline.length - 1);
      return {
        minX: component.minX,
        minY: component.minY,
        maxX: component.maxX,
        maxY: component.maxY,
        widthPx: component.maxX - component.minX + 1,
        heightPx: component.maxY - component.minY + 1,
        pixelCount: component.pixelCount,
        outline,
        sampledOutline,
      };
    }),
  };
}
async function createRuntimeJsx(jsxTemplatePath: string, selectedImagePath: string, coloredMetrics: unknown, sideCount: number, itemSizeInch: number) {
  await mkdir(runtimeDir, { recursive: true });
  const source = await readFile(jsxTemplatePath, "utf8");
  const imageBaseName = path.basename(selectedImagePath, path.extname(selectedImagePath));
  const imageId = imageBaseName.split("_")[0] || imageBaseName;
  const runtimeSource = [
    `var CODEX_TEMPLATE_PATH = ${JSON.stringify(toJsxPath(templatePath))};`,
    `var CODEX_IMAGE_PATH = ${JSON.stringify(toJsxPath(selectedImagePath))};`,
    `var CODEX_IMAGE_BASENAME = ${JSON.stringify(imageBaseName)};`,
    `var CODEX_IMAGE_ID = ${JSON.stringify(imageId)};`,
    `var CODEX_SIDE_COUNT = ${JSON.stringify(sideCount)};`,
    `var CODEX_ITEM_SIZE_INCH = ${JSON.stringify(itemSizeInch)};`,
    `var CODEX_COLORED_METRICS = ${JSON.stringify(coloredMetrics)};`,
    source,
  ].join("\n");
  const runtimePath = path.join(runtimeDir, "run-import-image.jsx");
  await writeFile(runtimePath, runtimeSource, "utf8");
  return runtimePath;
}

async function main() {
  const vbsPath = path.join(rootDir, "scripts", "launch-illustrator-and-run.vbs");
  const jsxTemplatePath = path.join(rootDir, "scripts", "import-image.jsx");

  await ensurePathExists(vbsPath);
  await ensurePathExists(jsxTemplatePath);
  await ensurePathExists(templatePath);
  await ensurePathExists(imagesDir);

  const imagePath = await getFirstPngImage(imagesDir);
  const sideCount = parseSideCount(imagePath);
  const itemSizeInch = parseItemSizeInch(imagePath);
  const coloredMetrics = await analyzeColoredComponents(imagePath);
  const runtimeJsxPath = await createRuntimeJsx(jsxTemplatePath, imagePath, coloredMetrics, sideCount, itemSizeInch);

  console.log("Dang mo template trong Adobe Illustrator...");
  console.log(`Template: ${templatePath}`);
  console.log(`Anh test: ${imagePath}`);
  console.log(`Side count: ${sideCount}`);
  console.log(`Item size inch: ${itemSizeInch}`);
  console.log(`Colored metrics: componentCount=${coloredMetrics.componentCount}`);

  await runCommand("cscript.exe", ["//nologo", vbsPath, runtimeJsxPath]);
  console.log("Hoan tat lenh test.");
}

main().catch((error) => {
  console.error("Tool chay loi:", error instanceof Error ? error.message : error);
  process.exitCode = 1;
});
